<?php

namespace App\Models;

use App\Concerns\BelongsToUser;
use App\Enums\RecurrenceFrequency;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Observers\AccountPlanObserver;
use Carbon\CarbonImmutable;
use Database\Factories\AccountPlanFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property int $user_id
 * @property TransactionType $type
 * @property string $description
 * @property string $expected_amount
 * @property RecurrenceFrequency $frequency
 * @property int $interval
 * @property int|null $day_of_month
 * @property int|null $day_of_week
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property int|null $occurrences
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[ObservedBy(AccountPlanObserver::class)]
class AccountPlan extends Model
{
    use BelongsToUser;

    /** @use HasFactory<AccountPlanFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'interval' => 1,
        'is_active' => true,
    ];

    protected $fillable = [
        'type',
        'description',
        'expected_amount',
        'frequency',
        'interval',
        'day_of_month',
        'day_of_week',
        'starts_at',
        'ends_at',
        'occurrences',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'expected_amount' => 'decimal:2',
            'frequency' => RecurrenceFrequency::class,
            'interval' => 'integer',
            'day_of_month' => 'integer',
            'day_of_week' => 'integer',
            'starts_at' => 'date',
            'ends_at' => 'date',
            'occurrences' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<DailyTransaction, $this>
     */
    public function dailyTransactions(): HasMany
    {
        return $this->hasMany(DailyTransaction::class);
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

    public function hasRealizedTransactionsUpToToday(): bool
    {
        return $this->dailyTransactions()
            ->where('user_id', $this->user_id)
            ->where('status', TransactionStatus::Realized)
            ->where('date', '<=', CarbonImmutable::now()->startOfDay()->toDateString())
            ->exists();
    }

    public function save(array $options = []): bool
    {
        if (! $this->exists) {
            return parent::save($options);
        }

        return $this->getConnection()->transaction(function () use ($options): bool {
            $changes = $this->getDirty();
            $current = $this->lockForMutation();
            $this->setRawAttributes($current->getAttributes(), sync: true);
            $this->setRawAttributes(array_replace($this->getAttributes(), $changes));

            return parent::save($options);
        });
    }

    /**
     * @throws ValidationException If deletion would remove detailed pending occurrences.
     */
    public function delete(): ?bool
    {
        if (! $this->exists) {
            return parent::delete();
        }

        return $this->getConnection()->transaction(function (): ?bool {
            $this->lockForMutation(deleting: true);

            return parent::delete();
        });
    }

    private function lockForMutation(bool $deleting = false): self
    {
        $lock = ! $deleting && $this->getConnection()->getDriverName() === 'pgsql' ? 'for no key update' : true;

        return $this->newQuery()->whereKey($this->getKey())->lock($lock)->firstOrFail();
    }

    /**
     * @param  Builder<DailyTransaction>  $candidates
     *
     * @throws ValidationException
     */
    private function assertNoDetailedPendingOccurrencesToCancel(Builder $candidates): void
    {
        $occurrences = (clone $candidates)->whereHas('cardItems')->orderBy('date')->get();

        if ($occurrences->isEmpty()) {
            return;
        }

        $dates = $occurrences
            ->map(fn (DailyTransaction $transaction): string => $transaction->date->format('d/m/Y'))
            ->implode(', ');

        throw ValidationException::withMessages([
            'is_active' => 'O plano não pode mudar: as ocorrências pendentes '.$dates
                .' têm itens de cartão. Realize-as ou remova o detalhamento antes de editar, desativar ou excluir o plano.',
        ]);
    }

    /**
     * @param  Builder<AccountPlan>  $query
     * @return Builder<AccountPlan>
     */
    public function scopeSelectableFor(Builder $query, User|int $account): Builder
    {
        return $query
            ->forUser($account)
            ->where('is_active', true);
    }

    public function cancelPendingRecurringTransactions(?CarbonImmutable $from = null): bool
    {
        $query = $this->dailyTransactions()->getQuery()
            ->where('user_id', $this->user_id)
            ->where('status', TransactionStatus::Pending)
            ->where('is_recurring', true);

        if ($from !== null) {
            $query->where('date', '>=', $from->startOfDay()->toDateString());
        }

        $ids = (clone $query)->orderBy('id')->lockForUpdate()->pluck('id');
        $candidates = $query->whereIntegerInRaw('id', $ids);

        $this->assertNoDetailedPendingOccurrencesToCancel($candidates);

        if ($from === null && $this->hasRealizedTransactionsUpToToday()) {
            return false;
        }

        $candidates->delete();

        return true;
    }
}
