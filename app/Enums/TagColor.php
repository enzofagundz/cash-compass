<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TagColor: string implements HasColor, HasLabel
{
    case Neutral = 'neutral';
    case Red = 'red';
    case Orange = 'orange';
    case Yellow = 'yellow';
    case Green = 'green';
    case Teal = 'teal';
    case Blue = 'blue';
    case Indigo = 'indigo';
    case Purple = 'purple';

    public function label(): string
    {
        return match ($this) {
            self::Neutral => 'Neutro',
            self::Red => 'Vermelho',
            self::Orange => 'Laranja',
            self::Yellow => 'Amarelo',
            self::Green => 'Verde',
            self::Teal => 'Turquesa',
            self::Blue => 'Azul',
            self::Indigo => 'Índigo',
            self::Purple => 'Roxo',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function color(): string
    {
        return $this === self::Neutral ? 'gray' : $this->value;
    }

    public function getColor(): string
    {
        return $this->color();
    }
}
