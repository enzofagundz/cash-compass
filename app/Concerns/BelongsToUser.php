<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToUser
{
    public static function bootBelongsToUser(): void
    {
        static::creating(function (Model $model): void {
            if ($model->getAttribute('user_id') === null && auth()->check()) {
                $model->setAttribute('user_id', auth()->id());
            }
        });

        static::addGlobalScope('user', function (Builder $query): void {
            if (auth()->hasUser()) {
                $query->where($query->getModel()->getTable().'.user_id', auth()->id());
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
