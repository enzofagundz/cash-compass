<?php

namespace App\Models;

use App\Concerns\BelongsToUser;
use Database\Factories\DailyForecastFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * @property int $id
 * @property int $user_id
 * @property string $description
 * @property string $amount
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class DailyForecast extends Model
{
    use BelongsToUser;

    /** @use HasFactory<DailyForecastFactory> */
    use HasFactory;

    protected $fillable = [
        'description',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (DailyForecast $forecast): void {
            if ((float) $forecast->amount <= 0) {
                throw new InvalidArgumentException('O valor da previsão deve ser maior que zero.');
            }

            $forecast->description = trim((string) $forecast->description);
        });
    }
}
