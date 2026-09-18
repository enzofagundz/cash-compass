<?php

namespace App\Models;

use App\Concerns\BelongsToUser;
use App\Enums\TagColor;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property TagColor $color
 * @property bool $is_active
 * @property string $normalized_name
 */
class Tag extends Model
{
    use BelongsToUser;

    /** @use HasFactory<TagFactory> */
    use HasFactory;

    protected $attributes = [
        'color' => TagColor::Neutral->value,
        'is_active' => true,
    ];

    protected $fillable = [
        'name',
        'color',
    ];

    protected static function booted(): void
    {
        static::saving(function (Tag $tag): void {
            $tag->name = self::normalizeName($tag->name);
            $tag->normalized_name = self::normalizeNameKey($tag->name);
        });

        static::deleting(function (Tag $tag): bool {
            return ! $tag->hasUsage();
        });
    }

    public static function normalizeName(string $name): string
    {
        return preg_replace('/\s+/u', ' ', trim($name)) ?? trim($name);
    }

    public static function normalizeNameKey(string $name): string
    {
        return mb_strtolower(self::normalizeName($name), 'UTF-8');
    }

    /**
     * @param  Builder<Tag>  $query
     * @return Builder<Tag>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function archive(): bool
    {
        $this->is_active = false;

        return $this->save();
    }

    public function reactivate(): bool
    {
        $this->is_active = true;

        return $this->save();
    }

    public function hasUsage(): bool
    {
        return $this->dailyTransactions()->withoutGlobalScope('user')->exists()
            || $this->accountPlans()->withoutGlobalScope('user')->exists();
    }

    /**
     * @return BelongsToMany<DailyTransaction, $this>
     */
    public function dailyTransactions(): BelongsToMany
    {
        return $this->belongsToMany(DailyTransaction::class)->withTimestamps();
    }

    /**
     * @return BelongsToMany<AccountPlan, $this>
     */
    public function accountPlans(): BelongsToMany
    {
        return $this->belongsToMany(AccountPlan::class)->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'color' => TagColor::class,
            'is_active' => 'boolean',
        ];
    }
}
