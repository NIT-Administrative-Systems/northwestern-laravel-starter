<?php

declare(strict_types=1);

namespace App\Http\Controllers\Support;

use App\Domains\Support\Models\Changelog;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Serves the changelog RSS feed.
 */
class ChangelogFeedController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var int $limit */
        $limit = config('changelog.feed.limit', 20);

        return response()
            ->view('support.changelog.feed', [
                'entries' => Changelog::limit($limit)->get(),
            ])
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }
}
