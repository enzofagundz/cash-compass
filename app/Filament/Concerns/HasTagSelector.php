<?php

namespace App\Filament\Concerns;

use App\Enums\TagColor;
use App\Models\AccountPlan;
use App\Models\DailyTransaction;
use App\Models\Tag;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

trait HasTagSelector
{
    use HasTagColorField;

    public static function tagSelector(): Select
    {
        return Select::make('tags')
            ->label('Tags')
            ->relationship('tags', 'name', function (Builder $query, AccountPlan|DailyTransaction|null $record): Builder {
                $selectedTagIds = $record?->exists
                    ? $record->tags()->pluck('tags.id')->all()
                    : [];

                return $query
                    ->where(function (Builder $query) use ($selectedTagIds): void {
                        $query->whereIn('tags.id', Tag::query()->active()->select('tags.id'));

                        if ($selectedTagIds !== []) {
                            $query->orWhereIn('tags.id', $selectedTagIds);
                        }
                    })
                    ->orderBy('tags.normalized_name');
            })
            ->getOptionLabelFromRecordUsing(fn (Tag $tag): HtmlString => new HtmlString(
                view('components.tag-badge', ['tag' => $tag])->render(),
            ))
            ->multiple()
            ->searchable()
            ->preload()
            ->allowHtml()
            ->createOptionForm([
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255),
                self::tagColorField(),
            ])
            ->createOptionUsing(function (array $data): int {
                $normalizedName = Tag::normalizeNameKey((string) ($data['name'] ?? ''));
                $existingTag = self::findReusableTag($normalizedName);

                if ($existingTag instanceof Tag) {
                    return $existingTag->getKey();
                }

                $rawColor = $data['color'] ?? TagColor::Neutral->value;
                $color = $rawColor instanceof TagColor
                    ? $rawColor
                    : TagColor::tryFrom((string) $rawColor);

                if (! $color instanceof TagColor) {
                    throw ValidationException::withMessages([
                        'color' => 'Escolha uma cor válida.',
                    ]);
                }

                try {
                    return DB::transaction(fn (): int => (int) Tag::create([
                        'name' => (string) ($data['name'] ?? ''),
                        'color' => $color,
                    ])->getKey());
                } catch (UniqueConstraintViolationException $exception) {
                    $existingTag = self::findReusableTag($normalizedName);

                    if (! $existingTag instanceof Tag) {
                        throw $exception;
                    }

                    return $existingTag->getKey();
                }
            });
    }

    private static function findReusableTag(string $normalizedName): ?Tag
    {
        $tag = Tag::query()->where('normalized_name', $normalizedName)->first();

        if (! $tag instanceof Tag) {
            return null;
        }

        if (! $tag->is_active) {
            throw ValidationException::withMessages([
                'name' => 'Essa tag está arquivada. Reative-a na área Tags antes de usá-la.',
            ]);
        }

        return $tag;
    }
}
