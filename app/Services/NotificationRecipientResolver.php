<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Equipment;
use App\Models\User;
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

        return User::role(UserRole::KepalaRuangan->value)
            ->where('room_id', $equipment->room_id)
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    public function ipsrsOperators(): Collection
    {
        return User::role([
            UserRole::Teknisi->value,
            UserRole::AdminIpsrs->value,
        ])->get();
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
