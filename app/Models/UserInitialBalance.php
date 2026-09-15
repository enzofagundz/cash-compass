<?php

namespace App\Models;

use App\Concerns\BelongsToUser;
use Database\Factories\UserInitialBalanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $amount
 * @property Carbon|null $base_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class UserInitialBalance extends Model
{
    use BelongsToUser;

    /** @use HasFactory<UserInitialBalanceFactory> */
    use HasFactory;

    protected $fillable = [
        'amount',
        'base_date',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'base_date' => 'date',
        ];
    }
}
