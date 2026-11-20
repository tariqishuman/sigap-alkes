<?php

namespace App\Services;

use App\Enums\SparePartMovementType;
use App\Enums\UserRole;
use App\Models\Repair;
use App\Models\SparePart;
use App\Models\SparePartMovement;
use App\Models\User;
use App\Notifications\LowSparePartStockNotification;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SparePartStockService
{
    /**
     * Record incoming stock (penerimaan barang).
     */
    public function stockIn(
        SparePart $sparePart,
        int $quantity,
        string $notes,
        ?int $userId = null,
        ?CarbonInterface $movedAt = null,
    ): SparePartMovement {
        Validator::make(
            ['quantity' => $quantity, 'notes' => $notes],
            [
                'quantity' => ['required', 'integer', 'min:1'],
                'notes' => ['required', 'string', 'max:1000'],
            ],
            [],
            ['quantity' => 'jumlah', 'notes' => 'keterangan'],
        )->validate();

        return DB::transaction(function () use ($sparePart, $quantity, $notes, $userId, $movedAt): SparePartMovement {
            $locked = SparePart::query()->whereKey($sparePart->id)->lockForUpdate()->firstOrFail();
            $locked->forceFill(['stock' => $locked->stock + $quantity])->save();

            return SparePartMovement::create([
                'spare_part_id' => $locked->id,
                'type' => SparePartMovementType::In,
                'quantity' => $quantity,
                'repair_id' => null,
                'user_id' => $userId ?? Auth::id(),
                'notes' => $notes,
                'moved_at' => $movedAt ?? now(),
            ]);
        });
    }

    /**
     * Deduct stock for an active repair. Rejects if stock is insufficient.
     */
    public function consumeForRepair(
        SparePart $sparePart,
        int $quantity,
        Repair $repair,
        ?int $userId = null,
        ?string $notes = null,
        ?CarbonInterface $movedAt = null,
    ): SparePartMovement {
        Validator::make(
            ['quantity' => $quantity],
            ['quantity' => ['required', 'integer', 'min:1']],
            [],
            ['quantity' => 'jumlah'],
        )->validate();

        [$movement, $shouldNotify, $updatedSparePart] = DB::transaction(function () use ($sparePart, $quantity, $repair, $userId, $notes, $movedAt): array {
            $locked = SparePart::query()->whereKey($sparePart->id)->lockForUpdate()->firstOrFail();

            if ($locked->stock < $quantity) {
                throw ValidationException::withMessages([
                    'spare_parts' => "Stok tidak cukup (tersisa {$locked->stock})",
                ]);
            }

            $newStock = $locked->stock - $quantity;
            $locked->forceFill(['stock' => $newStock])->save();

            $movement = SparePartMovement::create([
                'spare_part_id' => $locked->id,
                'type' => SparePartMovementType::Out,
                'quantity' => -$quantity,
                'repair_id' => $repair->id,
                'user_id' => $userId ?? Auth::id(),
                'notes' => $notes ?? "Pemakaian pada perbaikan #{$repair->id}",
                'moved_at' => $movedAt ?? now(),
            ]);

            return [$movement, $newStock <= $locked->min_stock, $locked->fresh()];
        });

        if ($shouldNotify) {
            $this->notifyLowStock($updatedSparePart);
        }

        return $movement;
    }

    /**
     * Return stock when a spare part line is reduced or removed from an active repair.
     */
    public function returnFromRepair(
        SparePart $sparePart,
        int $quantity,
        Repair $repair,
        ?int $userId = null,
        ?string $notes = null,
        ?CarbonInterface $movedAt = null,
    ): SparePartMovement {
        Validator::make(
            ['quantity' => $quantity],
            ['quantity' => ['required', 'integer', 'min:1']],
            [],
            ['quantity' => 'jumlah'],
        )->validate();

        return DB::transaction(function () use ($sparePart, $quantity, $repair, $userId, $notes, $movedAt): SparePartMovement {
            $locked = SparePart::query()->whereKey($sparePart->id)->lockForUpdate()->firstOrFail();
            $locked->forceFill(['stock' => $locked->stock + $quantity])->save();

            return SparePartMovement::create([
                'spare_part_id' => $locked->id,
                'type' => SparePartMovementType::Return,
                'quantity' => $quantity,
                'repair_id' => $repair->id,
                'user_id' => $userId ?? Auth::id(),
                'notes' => $notes ?? "Pengembalian dari perbaikan #{$repair->id}",
                'moved_at' => $movedAt ?? now(),
            ]);
        });
    }

    /**
     * Stock opname adjustment. Target stock must be >= 0 and reason is mandatory.
     */
    public function adjust(
        SparePart $sparePart,
        int $newStock,
        string $reason,
        ?int $userId = null,
        ?CarbonInterface $movedAt = null,
    ): SparePartMovement {
        Validator::make(
            ['new_stock' => $newStock, 'notes' => $reason],
            [
                'new_stock' => ['required', 'integer', 'min:0'],
                'notes' => ['required', 'string', 'max:1000'],
            ],
            [],
            ['new_stock' => 'stok baru', 'notes' => 'alasan'],
        )->validate();

        [$movement, $shouldNotify, $updatedSparePart] = DB::transaction(function () use ($sparePart, $newStock, $reason, $userId, $movedAt): array {
            $locked = SparePart::query()->whereKey($sparePart->id)->lockForUpdate()->firstOrFail();
            $delta = $newStock - $locked->stock;

            if ($delta === 0) {
                throw ValidationException::withMessages([
                    'new_stock' => 'Stok baru sama dengan stok saat ini.',
                ]);
            }

            $locked->forceFill(['stock' => $newStock])->save();

            $movement = SparePartMovement::create([
                'spare_part_id' => $locked->id,
                'type' => SparePartMovementType::Adjustment,
                'quantity' => $delta,
                'repair_id' => null,
                'user_id' => $userId ?? Auth::id(),
                'notes' => $reason,
                'moved_at' => $movedAt ?? now(),
            ]);

            return [$movement, $delta < 0 && $newStock <= $locked->min_stock, $locked->fresh()];
        });

        if ($shouldNotify) {
            $this->notifyLowStock($updatedSparePart);
        }

        return $movement;
    }

    /**
     * Synchronize spare parts for an active repair, applying only net deltas to stock.
     *
     * @param  array<int, array{spare_part_id: int|string, quantity: int|string, unit_price: int|float|string}>  $items
     */
    public function syncRepairSpareParts(Repair $repair, array $items, ?int $userId = null): void
    {
        $oldQuantities = [];
        foreach ($repair->repairSpareParts()->get() as $existing) {
            $oldQuantities[$existing->spare_part_id] = ($oldQuantities[$existing->spare_part_id] ?? 0) + (int) $existing->quantity;
        }

        $newQuantities = [];
        foreach ($items as $item) {
            $spId = (int) $item['spare_part_id'];
            $newQuantities[$spId] = ($newQuantities[$spId] ?? 0) + (int) $item['quantity'];
        }

        $allIds = array_unique(array_merge(array_keys($oldQuantities), array_keys($newQuantities)));
        sort($allIds);

        foreach ($allIds as $sparePartId) {
            $diff = ($newQuantities[$sparePartId] ?? 0) - ($oldQuantities[$sparePartId] ?? 0);

            if ($diff === 0) {
                continue;
            }

            $sparePart = SparePart::findOrFail($sparePartId);

            if ($diff > 0) {
                $this->consumeForRepair($sparePart, $diff, $repair, $userId);
            } else {
                $this->returnFromRepair($sparePart, abs($diff), $repair, $userId);
            }
        }

        $repair->repairSpareParts()->delete();

        foreach ($items as $item) {
            $repair->repairSpareParts()->create([
                'spare_part_id' => (int) $item['spare_part_id'],
                'quantity' => (int) $item['quantity'],
                'unit_price' => bcadd((string) $item['unit_price'], '0', 2),
            ]);
        }
    }

    private function notifyLowStock(SparePart $sparePart): void
    {
        $recipients = User::role(UserRole::AdminIpsrs->value)->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new LowSparePartStockNotification($sparePart));
        }
    }
}
