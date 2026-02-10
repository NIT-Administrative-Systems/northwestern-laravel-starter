<?php

declare(strict_types=1);

namespace App\Http\Controllers\Support;

use App\Domains\Support\Models\Changelog;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Handles the public-facing changelog pages.
 *
 * Displays a paginated index of all changelog entries and individual
 * entry detail pages. Entries are ordered by `authored_at` descending
 * via the model's global scope.
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

        return view('support.changelog.index', [
            'entries' => Changelog::paginate($perPage),
            'feedUrl' => route('support.changelog.feed'),
        ]);
    }

    /**
     * Display a single changelog entry.
     */
    public function show(Changelog $changelog): View
    {
        return view('support.changelog.show', [
            'entry' => $changelog,
        ]);
    }
}
