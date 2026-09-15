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
     * Query the records of a given user, bypassing the authenticated-user scope.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function scopeForUser(Builder $query, User|int $user): Builder
    {
        return $query
            ->withoutGlobalScope('user')
            ->where(
                $query->getModel()->getTable().'.user_id',
                $user instanceof User ? $user->getKey() : $user,
            );
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
