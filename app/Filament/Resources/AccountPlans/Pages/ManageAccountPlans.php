<?php

namespace App\Filament\Resources\AccountPlans\Pages;

use App\Filament\Resources\AccountPlans\AccountPlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAccountPlans extends ManageRecords
{
    protected static string $resource = AccountPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
