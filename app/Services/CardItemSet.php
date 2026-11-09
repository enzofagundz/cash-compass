<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\CardItem;
use App\Models\DailyTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns the set of purchase items that detail a card transaction.
 *
 * Two invariants live here and nowhere else: a set of items only exists for a
 * card transaction, and a non-empty set has to close exactly, in cents, with
 * the parent amount. The parent amount keeps being the only number that moves
 * the balance, the forecast, the columns and the spending totals.
 */
class CardItemSet
{
    /**
     * Sum of the item amounts in cents.
     *
     * @param  array<int|string, array<string, mixed>>  $items
     */
    public function cents(array $items): int
    {
        $total = 0;

        foreach ($items as $item) {
            $total += $this->toCents($item['amount'] ?? null);
        }

        return $total;
    }

    /**
     * Parent amount minus the item sum, in cents. Positive means the items are
     * short of the parent; negative means they add up to more than the parent.
     *
     * @param  array<int|string, array<string, mixed>>  $items
     */
    public function differenceCents(mixed $parentAmount, array $items): int
    {
        return $this->toCents($parentAmount) - $this->cents($items);
    }

    /**
     * Validate a candidate set against the parent type and amount.
     *
     * @param  array<int|string, array<string, mixed>>  $items
     *
     * @throws ValidationException
     */
    public function validate(TransactionType $type, mixed $parentAmount, array $items): void
    {
        if ($items === []) {
            return;
        }

        if ($type !== TransactionType::Card) {
            throw ValidationException::withMessages([
                'card_items' => 'Só lançamentos do tipo Cartão podem ter itens. Remova os itens para trocar o tipo.',
            ]);
        }

        $difference = $this->differenceCents($parentAmount, $items);

        if ($difference !== 0) {
            throw ValidationException::withMessages([
                'card_items' => 'A soma dos itens precisa fechar com o valor do lançamento. Diferença: '.$this->formatMoney($difference).'.',
            ]);
        }
    }

    /**
     * Replace the stored set with the submitted one, keeping the item ids that
     * still belong to the transaction so their tags survive the save.
     *
     * @param  array<int|string, array<string, mixed>>  $items
     *
     * @throws ValidationException
     */
    public function sync(DailyTransaction $transaction, array $items, mixed $parentAmount = null): void
    {
        if (! $transaction->exists) {
            throw ValidationException::withMessages([
                'card_items' => 'Salve o lançamento antes de detalhar os itens.',
            ]);
        }

        if ($items === []) {
            $this->deleteAll($transaction);

            return;
        }

        if ($transaction->is_recurring && $transaction->status === TransactionStatus::Pending) {
            throw ValidationException::withMessages([
                'card_items' => 'Ocorrências recorrentes pendentes só podem ser detalhadas depois de confirmadas ou puladas.',
            ]);
        }

        $this->validate($transaction->type, $parentAmount ?? $transaction->amount, $items);

        DB::transaction(function () use ($transaction, $items): void {
            $existing = $transaction->cardItems()->get()
                ->keyBy(fn (CardItem $item): int => (int) $item->getKey());

            $keptIds = [];

            foreach (array_values($items) as $item) {
                $id = isset($item['id']) && is_numeric($item['id']) ? (int) $item['id'] : null;
                $stored = $id === null ? null : $existing->get($id);

                $payload = [
                    'description' => (string) ($item['description'] ?? ''),
                    'amount' => $this->normalizeAmount($item['amount'] ?? null),
                ];

                if ($stored instanceof CardItem) {
                    $stored->update($payload);
                    $keptIds[] = (int) $stored->getKey();

                    continue;
                }

                $keptIds[] = (int) $transaction->cardItems()->create($payload)->getKey();
            }

            $existing
                ->reject(fn (CardItem $item): bool => in_array((int) $item->getKey(), $keptIds, true))
                ->each(fn (CardItem $item) => $item->delete());
        });
    }

    /**
     * Items of a transaction as repeater-ready state, in insertion order.
     *
     * @return array<int, array<string, mixed>>
     */
    public function formState(DailyTransaction $transaction): array
    {
        return $transaction->cardItems()
            ->orderBy('id')
            ->get()
            ->map(fn (CardItem $item): array => [
                'id' => (int) $item->getKey(),
                'description' => $item->description,
                'amount' => $item->amount,
            ])
            ->all();
    }

    public function deleteAll(DailyTransaction $transaction): void
    {
        $transaction->cardItems()->delete();
    }

    public function formatMoney(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $absolute = abs($cents);

        return $sign.'R$ '.number_format(intdiv($absolute, 100), 0, ',', '.').','
            .str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    private function normalizeAmount(mixed $amount): string
    {
        return number_format($this->toCents($amount) / 100, 2, '.', '');
    }

    private function toCents(mixed $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        $normalized = is_string($amount)
            ? str_replace(['R$', ' '], '', $amount)
            : $amount;

        if (is_string($normalized) && str_contains($normalized, ',')) {
            $normalized = str_replace(['.', ','], ['', '.'], $normalized);
        }

        return (int) round((float) $normalized * 100);
    }
}
