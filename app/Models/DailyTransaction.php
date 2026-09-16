<?php

namespace App\Models;

use App\Concerns\BelongsToUser;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use Carbon\CarbonImmutable;
use Database\Factories\DailyTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
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
        });
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
        return $this->belongsToMany(Tag::class)->withTimestamps();
    }
}
