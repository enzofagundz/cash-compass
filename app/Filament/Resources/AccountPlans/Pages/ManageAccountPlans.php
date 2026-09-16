<?php

namespace App\Filament\Resources\AccountPlans\Pages;

use App\Filament\Resources\AccountPlans\AccountPlanResource;
use App\Models\AccountPlan;
use App\Services\RecurrenceGenerator;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAccountPlans extends ManageRecords
{
    protected static string $resource = AccountPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // The observer generates the occurrences before the tags are
            // attached, so sync the plan tags onto them right after creation.
            CreateAction::make()
                ->after(fn (AccountPlan $record): int => app(RecurrenceGenerator::class)->generate($record)),
        ];
    }
}
