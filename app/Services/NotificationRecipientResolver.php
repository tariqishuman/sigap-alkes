<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Equipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class NotificationRecipientResolver
{
    /**
     * @return Collection<int, User>
     */
    public function roomHeadsFor(Equipment $equipment): Collection
    {
        if (! $equipment->room_id) {
            return collect();
        }

        return User::query()
            ->where('room_id', $equipment->room_id)
            ->whereHas('roles', fn (Builder $q) => $q->where('name', UserRole::KepalaRuangan->value))
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    public function ipsrsOperators(): Collection
    {
        return User::query()
            ->whereHas('roles', fn (Builder $q) => $q->whereIn('name', [
                UserRole::Teknisi->value,
                UserRole::AdminIpsrs->value,
            ]))
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    public function damageAndStatusRecipients(Equipment $equipment, ?int $excludeUserId = null): Collection
    {
        return $this->ipsrsOperators()
            ->merge($this->roomHeadsFor($equipment))
            ->unique('id')
            ->when($excludeUserId !== null, fn (Collection $users) => $users->reject(fn (User $u): bool => $u->id === $excludeUserId))
            ->values();
    }
}
