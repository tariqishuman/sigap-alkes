<?php

namespace App\Filament\Resources\Repairs\Pages;

use App\Filament\Resources\Repairs\RepairResource;
use App\Services\RepairService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EditRepair extends EditRecord
{
    protected static string $resource = RepairResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['spare_parts'] = $this->record->repairSpareParts()
            ->get()
            ->map(fn ($item): array => [
                'spare_part_id' => $item->spare_part_id,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
            ])
            ->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return RepairResource::withFormErrors(
            fn () => app(RepairService::class)->update($record, $data, Auth::id()),
        );
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
