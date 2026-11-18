<?php

namespace App\Models;

use App\Concerns\BelongsToUser;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Services\CardItemSet;
use Carbon\CarbonImmutable;
use Database\Factories\DailyTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * @property int $id
 * @property int $user_id
 * @property Carbon $date
 * @property TransactionType $type
 * @property string $amount
 * @property string|null $description
 * @property int|null $account_plan_id
 * @property bool $is_recurring
 * @property TransactionStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class DailyTransaction extends Model
{
    use BelongsToUser;

    /** @use HasFactory<DailyTransactionFactory> */
    use HasFactory;

    protected $fillable = [
        'date',
        'type',
        'amount',
        'description',
        'account_plan_id',
        'is_recurring',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'type' => TransactionType::class,
            'amount' => 'decimal:2',
            'is_recurring' => 'boolean',
            'status' => TransactionStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (DailyTransaction $transaction): void {
            if ((float) $transaction->amount <= 0) {
                throw new InvalidArgumentException('O valor do lançamento deve ser maior que zero.');
            }

            $transaction->assertCardItemsFollowTheParent();
        });
    }

    public function save(array $options = []): bool
    {
        if (! $this->exists || ! $this->isDirty(['type', 'amount'])) {
            return parent::save($options);
        }

        return $this->getConnection()->transaction(function () use ($options): bool {
            $this->lockForCardItemMutation();

            return parent::save($options);
        });
    }

    public function lockForCardItemMutation(): self
    {
        $record = $this->newQuery()
            ->where('user_id', $this->user_id)
            ->whereKey($this->getKey())
            ->lockForUpdate()
            ->first();

        if ($record === null) {
            throw ValidationException::withMessages([
                'card_items' => 'Salve o lançamento antes de detalhar os itens.',
            ]);
        }

        return $record;
    }

    /**
     * A detailed card keeps its items closed against the parent: leaving the
     * card type or moving the amount away from the item sum is refused.
     *
     * @throws ValidationException
     */
    private function assertCardItemsFollowTheParent(): void
    {
        if (! $this->exists || ! $this->isDirty(['type', 'amount'])) {
            return;
        }

        $items = $this->cardItems()->get();

        if ($items->isEmpty()) {
            return;
        }

        if ($this->type !== TransactionType::Card) {
            throw ValidationException::withMessages([
                'type' => 'Remova os itens antes de trocar o tipo do lançamento.',
            ]);
        }

        $difference = app(CardItemSet::class)->differenceCents(
            $this->amount,
            $items->map(fn (CardItem $item): array => ['amount' => $item->amount])->all(),
        );

        if ($difference !== 0) {
            throw ValidationException::withMessages([
                'amount' => 'Ajuste os itens antes de mudar o valor. Diferença: '.app(CardItemSet::class)->formatMoney($difference).'.',
            ]);
        }
    }

    /**
     * List the years that have transactions, plus the current year.
     *
     * @return array<int, string>
     */
    public static function years(): array
    {
        return static::query()
            ->pluck('date')
            ->map(fn (string $date): int => CarbonImmutable::parse($date)->year)
            ->push((int) now()->year)
            ->unique()
            ->sort()
            ->mapWithKeys(fn (int $year): array => [$year => (string) $year])
            ->all();
    }

    /**
     * @return BelongsTo<AccountPlan, $this>
     */
    public function accountPlan(): BelongsTo
    {
        return $this->belongsTo(AccountPlan::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)
            ->withTimestamps()
            ->orderBy('tags.normalized_name');
    }

    /**
     * @return HasMany<CardItem, $this>
     */
    public function cardItems(): HasMany
    {
        return $this->hasMany(CardItem::class)->orderBy('id');
    }

    public function hasCardItems(): bool
    {
        return $this->cardItems()->exists();
    }
}
