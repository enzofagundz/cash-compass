<?php

namespace App\Models;

use App\Concerns\BelongsToUser;
use Carbon\CarbonImmutable;
use Database\Factories\DayCheckInFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property Carbon $date
 */
class DayCheckIn extends Model
{
    use BelongsToUser;

    /** @use HasFactory<DayCheckInFactory> */
    use HasFactory;

    protected $fillable = [
        'date',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
        ];
    }

    /**
     * Toggle the check-in for the given day, returning the new state.
     */
    public static function toggleFor(User|int $user, CarbonImmutable|string $date): bool
    {
        $userId = $user instanceof User ? $user->getKey() : $user;
        $day = ($date instanceof CarbonImmutable ? $date : CarbonImmutable::parse($date))->toDateString();

        return static::setFor($userId, $day, ! static::forUser($userId)->where('date', $day)->exists());
    }

    /**
     * Set the check-in state of the given day, returning the resulting state.
     */
    public static function setFor(User|int $user, CarbonImmutable|string $date, bool $checkedIn): bool
    {
        $userId = $user instanceof User ? $user->getKey() : $user;
        $day = ($date instanceof CarbonImmutable ? $date : CarbonImmutable::parse($date))->toDateString();

        $existing = static::forUser($userId)->where('date', $day)->first();

        if ($checkedIn) {
            if (! $existing instanceof self) {
                $checkIn = new self(['date' => $day]);
                $checkIn->user_id = $userId;
                $checkIn->save();
            }

            return true;
        }

        if ($existing instanceof self) {
            $existing->delete();
        }

        return false;
    }
}
