<?php

namespace App\Filament\Concerns;

use App\Enums\TagColor;
use Filament\Forms\Components\ToggleButtons;

trait HasTagColorField
{
    public static function tagColorField(): ToggleButtons
    {
        return ToggleButtons::make('color')
            ->label('Cor')
            ->options(TagColor::class)
            ->in(array_map(
                static fn (TagColor $color): string => $color->value,
                TagColor::cases(),
            ))
            ->default(TagColor::Neutral->value)
            ->required()
            ->inline();
    }
}
