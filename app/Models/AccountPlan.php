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
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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

    public function hasRealizedTransactionsUpToToday(): bool
    {
        return $this->dailyTransactions()
            ->where('status', TransactionStatus::Realized)
            ->where('date', '<=', CarbonImmutable::now()->startOfDay()->toDateString())
            ->exists();
    }

    /**
     * Delete generated pending transactions, leaving manual transactions untouched.
     */
    public function cancelPendingRecurringTransactions(?CarbonImmutable $from = null): int
    {
        $query = $this->dailyTransactions()
            ->where('status', TransactionStatus::Pending)
            ->where('is_recurring', true);

        if ($from !== null) {
            $query->where('date', '>=', $from->startOfDay()->toDateString());
        }

        return $query->delete();
    }
}
