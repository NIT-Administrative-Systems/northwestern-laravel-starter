<?php

declare(strict_types=1);

namespace App\Http\Controllers\Support;

use App\Domains\Support\Models\Changelog;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Handles the public-facing changelog pages.
 *
 * The public changelog: its entries, newest first, and a page for each one.
 */
class ChangelogController extends Controller
{
    /**
     * Display a paginated list of changelog entries.
     */
    public function index(): View
    {
        /** @var int $perPage */
        $perPage = config('changelog.pagination.per_page', 10);

        return view('public.changelog.index', [
            'entries' => Changelog::query()->paginate($perPage),
            'feedUrl' => route('support.changelog.feed'),
        ]);
    }

    /**
     * Display a single changelog entry.
     */
    public function show(Changelog $changelog): View
    {
        return view('public.changelog.show', [
            'entry' => $changelog,
        ]);
    }
}
