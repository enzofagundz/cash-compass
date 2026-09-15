<?php

namespace App\Models;

use App\Concerns\BelongsToUser;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use Database\Factories\DailyTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

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

    /**
     * @return BelongsTo<AccountPlan, $this>
     */
    public function accountPlan(): BelongsTo
    {
        return $this->belongsTo(AccountPlan::class);
    }
}
