<?php

namespace App\Enums;

enum Month: int
{
    case January = 1;
    case February = 2;
    case March = 3;
    case April = 4;
    case May = 5;
    case June = 6;
    case July = 7;
    case August = 8;
    case September = 9;
    case October = 10;
    case November = 11;
    case December = 12;

    public function label(): string
    {
        return match ($this) {
            self::January => 'Janeiro',
            self::February => 'Fevereiro',
            self::March => 'Março',
            self::April => 'Abril',
            self::May => 'Maio',
            self::June => 'Junho',
            self::July => 'Julho',
            self::August => 'Agosto',
            self::September => 'Setembro',
            self::October => 'Outubro',
            self::November => 'Novembro',
            self::December => 'Dezembro',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::January => 'jan',
            self::February => 'fev',
            self::March => 'mar',
            self::April => 'abr',
            self::May => 'mai',
            self::June => 'jun',
            self::July => 'jul',
            self::August => 'ago',
            self::September => 'set',
            self::October => 'out',
            self::November => 'nov',
            self::December => 'dez',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $month): array => [$month->value => $month->label()])
            ->all();
    }
}
