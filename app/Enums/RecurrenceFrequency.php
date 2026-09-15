<?php

namespace App\Enums;

enum RecurrenceFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';
    case Installment = 'installment';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Diária',
            self::Weekly => 'Semanal',
            self::Biweekly => 'Quinzenal',
            self::Monthly => 'Mensal',
            self::Yearly => 'Anual',
            self::Installment => 'Parcelada',
        };
    }
}
