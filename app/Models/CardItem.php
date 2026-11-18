<?php

namespace App\Models;

use App\Concerns\BelongsToUser;
use App\Enums\TransactionType;
use Database\Factories\CardItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Purchase line that details the amount of a card transaction.
 *
 * Items never affect the balance on their own: the parent card amount stays
 * the single source of truth and the whole set must close exactly against it.
 *
 * @property int $id
 * @property int $user_id
 * @property int $daily_transaction_id
 * @property string $description
 * @property string $amount
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CardItem extends Model
{
    use BelongsToUser;

    /** @use HasFactory<CardItemFactory> */
    use HasFactory;

    protected $fillable = [
        'daily_transaction_id',
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
        static::saving(function (CardItem $item): void {
            if (trim((string) $item->description) === '') {
                throw new InvalidArgumentException('A descrição do item é obrigatória.');
            }

            if ((float) $item->amount <= 0) {
                throw new InvalidArgumentException('O valor do item deve ser maior que zero.');
            }

            $parent = $item->parentTransaction();
            $authenticatedId = auth()->id();

            if ($item->exists && (int) $parent->user_id !== (int) $item->user_id) {
                throw new InvalidArgumentException('O item não pode pertencer a outro usuário.');
            }

            if ($authenticatedId !== null && (int) $parent->user_id !== (int) $authenticatedId) {
                throw new InvalidArgumentException('O item não pode pertencer a outro usuário.');
            }

            if ($parent->type !== TransactionType::Card) {
                throw new InvalidArgumentException('Itens só podem detalhar lançamentos do tipo Cartão.');
            }

            $item->user_id = (int) $parent->user_id;
        });
    }

    /**
     * @return BelongsTo<DailyTransaction, $this>
     */
    public function dailyTransaction(): BelongsTo
    {
        return $this->belongsTo(DailyTransaction::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)
            ->withTimestamps()
            ->orderBy('tags.normalized_name');
    }

    /**
     * Parent transaction resolved without the authenticated user scope so the
     * ownership check can compare real owners instead of trusting the scope.
     */
    public function parentTransaction(): DailyTransaction
    {
        $parent = $this->dailyTransaction()->withoutGlobalScope('user')->first();

        if (! $parent instanceof DailyTransaction) {
            throw new InvalidArgumentException('O item precisa de um lançamento-pai.');
        }

        return $parent;
    }
}
