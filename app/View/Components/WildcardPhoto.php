<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Domains\User\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class WildcardPhoto extends Component
{
    public function __construct(
        public ?User $user,
    ) {
        //
    }

    public function render(): View
    {
        $imageUrl = filled($this->user->wildcard_photo_s3_key ?? '')
            ? route('users.wildcard-photo', ['user' => $this->user, 'c' => md5((string) $this->user->wildcard_photo_last_synced_at->toString())])
            : route('users.wildcard-photo', $this->user);

        return view('components.wildcard-photo', [
            'user' => $this->user,
            'imageUrl' => $imageUrl,
        ]);
    }
}
