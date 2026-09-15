<?php

namespace App\Filament\Resources\DailyTransactions\Pages;

use App\Filament\Resources\DailyTransactions\DailyTransactionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDailyTransactions extends ManageRecords
{
    protected static string $resource = DailyTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
