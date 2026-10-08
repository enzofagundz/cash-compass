<?php

namespace App\Models;

use App\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $operation_key
 * @property string $tool
 * @property string $arguments_hash
 * @property array<string, mixed> $result
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class McpOperation extends Model
{
    use BelongsToUser;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'operation_key',
        'tool',
        'arguments_hash',
        'result',
    ];

    protected function casts(): array
    {
        return [
            'result' => 'array',
        ];
    }
}
