<?php

namespace App\Models;

use App\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $tool
 * @property string|null $auditable_type
 * @property int|null $auditable_id
 * @property string $result
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class McpMutationAudit extends Model
{
    use BelongsToUser;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'tool',
        'auditable_type',
        'auditable_id',
        'result',
    ];
}
