<?php

namespace App\Models;

use App\Concerns\BelongsToUser;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 */
class Tag extends Model
{
    use BelongsToUser;

    /** @use HasFactory<TagFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
    ];

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
}
