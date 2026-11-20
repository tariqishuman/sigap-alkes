<?php

namespace App\Filament\AvatarProviders;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class SigapAvatarProvider implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        $segments = collect(explode(' ', trim(Filament::getNameForDefaultAvatar($record))))
            ->map(function (string $segment): string {
                $letters = preg_replace('/^[^\p{L}\p{N}]+/u', '', $segment);

                return filled($letters) ? mb_strtoupper(mb_substr($letters, 0, 1)) : '';
            })
            ->filter()
            ->values();

        $initials = $segments->take(2)->implode('') ?: 'U';

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="68" height="68" viewBox="0 0 68 68">'
            .'<rect width="68" height="68" rx="34" fill="#D5ECEA"/>'
            .'<text x="50%" y="52%" dominant-baseline="middle" text-anchor="middle" '
            .'font-family="Plus Jakarta Sans, system-ui, sans-serif" font-size="24" font-weight="700" fill="#0E7C7B">'
            .e($initials)
            .'</text></svg>';

        return 'data:image/svg+xml,'.rawurlencode($svg);
    }
}
