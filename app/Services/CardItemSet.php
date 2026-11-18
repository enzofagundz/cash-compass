<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\CardItem;
use App\Models\DailyTransaction;
use App\Models\Tag;
use Illuminate\Validation\ValidationException;

/**
 * Owns the set of purchase items that detail a card transaction.
 *
 * Two invariants live here and nowhere else: a set of items only exists for a
 * card transaction, and a non-empty set has to close exactly, in cents, with
 * the parent amount. The parent amount keeps being the only number that moves
 * the balance, the forecast, the columns and the spending totals.
 *
 * @phpstan-type ItemState array{id: int, description: string, amount: string, tag_ids: list<int>}
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

        $transaction->getConnection()->transaction(function () use ($transaction, $items): void {
            $transaction = $transaction->lockForCardItemMutation();

            $this->validate($transaction->type, $transaction->amount, $items);
            $this->replace($transaction, $items);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int|string, array<string, mixed>>  $items
     */
    public function updateTransaction(DailyTransaction $transaction, array $attributes, array $items, ?string $expectedSignature = null): void
    {
        $transaction->getConnection()->transaction(function () use ($transaction, $attributes, $items, $expectedSignature): void {
            $transaction = $transaction->lockForCardItemMutation();

            if ($expectedSignature !== null && ! hash_equals($this->signature($this->formState($transaction)), $expectedSignature)) {
                throw ValidationException::withMessages([
                    'card_items' => 'Os itens deste cartão mudaram. Feche e reabra a edição antes de salvar.',
                ]);
            }

            $wasCard = $transaction->type === TransactionType::Card;
            $transaction->fill($attributes);

            $this->validate($transaction->type, $transaction->amount, $items);

            if ($wasCard) {
                $this->replace($transaction, $items);
            }

            $transaction->save();

            if (! $wasCard && $items !== []) {
                $this->replace($transaction, $items);
            }
        });
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $items
     */
    private function replace(DailyTransaction $transaction, array $items): void
    {
        if ($items === []) {
            $transaction->cardItems()->delete();

            return;
        }

        $remaining = $transaction->cardItems()->get()
            ->keyBy(fn (CardItem $item): int => (int) $item->getKey());

        foreach ($items as $item) {
            $id = isset($item['id']) && is_numeric($item['id']) ? (int) $item['id'] : null;
            $stored = $id === null ? null : $remaining->pull($id);

            $payload = [
                'description' => (string) ($item['description'] ?? ''),
                'amount' => $this->normalizeAmount($item['amount'] ?? null),
            ];

            $record = $stored instanceof CardItem
                ? tap($stored)->update($payload)
                : $transaction->cardItems()->create($payload);

            $record->tags()->sync($this->resolveTagIds($record, $item['tag_ids'] ?? []));
        }

        $remaining->each(fn (CardItem $item) => $item->delete());
    }

    /**
     * Items of a transaction as repeater-ready state, in insertion order.
     *
     * @return list<ItemState>
     */
    public function formState(DailyTransaction $transaction): array
    {
        $items = $transaction->cardItems()
            ->with('tags')
            ->orderBy('id')
            ->get()
            ->map(fn (CardItem $item): array => [
                'id' => (int) $item->getKey(),
                'description' => $item->description,
                'amount' => $item->amount,
                'tag_ids' => array_values($item->tags->map(fn (Tag $tag): int => $tag->id)->all()),
            ])
            ->all();

        return array_values($items);
    }

    /**
     * @param  list<ItemState>  $items
     */
    public function signature(array $items): string
    {
        foreach ($items as &$item) {
            sort($item['tag_ids'], SORT_NUMERIC);
        }

        return hash('sha256', json_encode($items, JSON_THROW_ON_ERROR));
    }

    /**
     * Keep only tags the account may link, preserving archived tags that were
     * already attached to the item.
     *
     * @return array<int, int>
     *
     * @throws ValidationException
     */
    private function resolveTagIds(CardItem $item, mixed $tagIds): array
    {
        $ids = Tag::idsFrom($tagIds);

        if ($ids === []) {
            return [];
        }

        $allowed = Tag::query()
            ->attachableTo((int) $item->user_id, $item->tags()->pluck('tags.id')->all())
            ->whereKey($ids)
            ->pluck('tags.id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        if (count($allowed) !== count($ids)) {
            throw ValidationException::withMessages([
                'card_items' => 'Uma das tags do item é de outra conta ou está arquivada. Reative-a na área Tags antes de usá-la.',
            ]);
        }

        return $allowed;
    }

    public function deleteAll(DailyTransaction $transaction): void
    {
        $this->sync($transaction, []);
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
