<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\User\Models\User;
use Carbon\CarbonInterval;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class WildcardPhotoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function show(User $user): RedirectResponse
    {
        $imageUrl = filled($user->wildcard_photo_s3_key) && Gate::allows('view', $user)
            ? Storage::temporaryUrl(
                path: $user->wildcard_photo_s3_key,
                expiration: now()->addMinutes(30)
            )
            : asset('images/default-profile-photo.svg');

        return redirect($imageUrl)
            ->setPrivate()
            ->setMaxAge((int) CarbonInterval::minutes(30)->totalSeconds);
    }
}
