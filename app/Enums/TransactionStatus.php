<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case Pending = 'pending';
    case Realized = 'realized';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::Realized => 'Realizado',
            self::Skipped => 'Pulado',
        };
    }
}
