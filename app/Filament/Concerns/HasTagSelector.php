<?php

namespace App\Filament\Concerns;

use App\Enums\TagColor;
use App\Models\AccountPlan;
use App\Models\DailyTransaction;
use App\Models\Tag;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

trait HasTagSelector
{
    use HasTagColorField;

    /**
     * Tag select bound to the record's `tags` relationship.
     */
    public static function tagSelector(): Select
    {
        return self::configureQuickCreate(
            Select::make('tags')
                ->label('Tags')
                ->relationship('tags', 'name', function (Builder $query, AccountPlan|DailyTransaction|null $record): Builder {
                    $selectedTagIds = $record?->exists
                        ? $record->tags()->pluck('tags.id')->all()
                        : [];

                    return Tag::query()
                        ->attachableTo((int) auth()->id(), $selectedTagIds)
                        ->orderBy('tags.normalized_name');
                })
                ->getOptionLabelFromRecordUsing(fn (Tag $tag): HtmlString => self::tagBadge($tag)),
        );
    }

    /**
     * Tag select backed by plain ids, for contexts without a record of their
     * own — such as a repeater item, synced by hand.
     */
    public static function tagIdsSelector(string $name = 'tag_ids', string $label = 'Tags do item'): Select
    {
        return self::configureQuickCreate(
            Select::make($name)
                ->label($label)
                ->multiple()
                ->options(fn (Get $get): array => self::attachableTagOptions(self::tagIdsFrom($get($name))))
                ->getOptionLabelsUsing(fn (array $values): array => self::tagBadgeLabels($values)),
        );
    }

    /**
     * @param  array<int, int>  $selectedTagIds
     * @return array<int, string>
     */
    public static function attachableTagOptions(array $selectedTagIds): array
    {
        return Tag::query()
            ->attachableTo((int) auth()->id(), self::tagIdsFrom($selectedTagIds))
            ->orderBy('tags.normalized_name')
            ->pluck('name', 'tags.id')
            ->all();
    }

    /**
     * @param  array<int, int>  $values
     * @return array<int, HtmlString>
     */
    public static function tagBadgeLabels(array $values): array
    {
        $tagIds = self::tagIdsFrom($values);

        if ($tagIds === []) {
            return [];
        }

        return Tag::query()
            ->attachableTo((int) auth()->id(), $tagIds)
            ->whereKey($tagIds)
            ->get()
            ->mapWithKeys(fn (Tag $tag): array => [(int) $tag->getKey() => self::tagBadge($tag)])
            ->all();
    }

    public static function tagBadge(Tag $tag): HtmlString
    {
        return new HtmlString(
            view('components.tag-badge', ['tag' => $tag])->render(),
        );
    }

    /**
     * Normalise a raw multiselect state into a list of tag ids.
     *
     * @return array<int, int>
     */
    public static function tagIdsFrom(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn (mixed $id): bool => is_numeric($id))
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private static function configureQuickCreate(Select $select): Select
    {
        return $select
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
