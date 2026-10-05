<?php

declare(strict_types=1);

namespace App\Domains\Support\Models;

use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\Auth\Models\Role;
use App\Domains\Core\Models\BaseModel;
use App\Domains\Support\Enums\AnnouncementAudience;
use App\Domains\Support\Enums\AnnouncementSeverity;
use App\Domains\Support\Enums\AnnouncementStatus;
use App\Domains\User\Enums\Affiliation;
use App\Domains\User\Models\User;
use Carbon\CarbonInterface;
use Database\Factories\Domains\Support\Models\AnnouncementFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Spatie\LaravelMarkdown\MarkdownRenderer;

/**
 * A message from the application's administrators, shown in a banner and on the Announcements
 * page to its audience while it's live.
 *
 * A draft has no `published_at`. A published announcement is live from `starts_at` until
 * `ends_at`, if it has one; its status is worked out from those dates, with no scheduled jobs.
 *
 * @property int $id
 * @property string $title
 * @property string $body Markdown
 * @property AnnouncementSeverity $severity
 * @property AnnouncementAudience $audience
 * @property list<int>|null $role_ids
 * @property list<string>|null $affiliations
 * @property Carbon|null $published_at
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property bool $notify_audience
 * @property Carbon|null $notified_at
 * @property int|null $created_by_user_id
 * @property-read AnnouncementStatus $status
 */
class Announcement extends BaseModel
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory;

    protected $casts = [
        'severity' => AnnouncementSeverity::class,
        'audience' => AnnouncementAudience::class,
        'role_ids' => 'array',
        'affiliations' => 'array',
        'published_at' => 'datetime',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'notify_audience' => 'boolean',
        'notified_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::deleting(fn (self $announcement) => $announcement->dismissals()->delete());
    }

    /** @return HasMany<AnnouncementDismissal, $this> */
    public function dismissals(): HasMany
    {
        return $this->hasMany(AnnouncementDismissal::class);
    }

    /** @return BelongsTo<User, $this> */
    public function created_by_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function statusAt(CarbonInterface $at): AnnouncementStatus
    {
        return match (true) {
            $this->published_at === null || $this->starts_at === null => AnnouncementStatus::Draft,
            $this->starts_at->gt($at) => AnnouncementStatus::Scheduled,
            $this->ends_at !== null && $this->ends_at->lte($at) => AnnouncementStatus::Ended,
            default => AnnouncementStatus::Live,
        };
    }

    /** @return Attribute<AnnouncementStatus, never> */
    protected function status(): Attribute
    {
        // Not cached: it changes with the dates, and with the time.
        return Attribute::get(fn (): AnnouncementStatus => $this->statusAt(Carbon::now()))->withoutObjectCaching();
    }

    public function isDismissible(): bool
    {
        return $this->severity->isDismissible();
    }

    /**
     * The body as HTML. Raw HTML in the Markdown is shown as text, never rendered, and unsafe
     * links (`javascript:` and the like) are dropped.
     */
    public function bodyHtml(): HtmlString
    {
        return new HtmlString(self::renderMarkdown($this->body));
    }

    public static function renderMarkdown(string $markdown): string
    {
        return (clone resolve(MarkdownRenderer::class))
            ->disableAnchors()
            ->disableHighlighting()
            ->commonmarkOptions(['html_input' => 'escape', 'allow_unsafe_links' => false])
            ->toHtml($markdown);
    }

    /**
     * A short description of who sees the announcement, for lists.
     */
    public function audienceSummary(): string
    {
        if ($this->audience !== AnnouncementAudience::Targeted) {
            return $this->audience->getLabel();
        }

        $roles = Role::query()->whereKey($this->role_ids ?? [])->orderBy('name')->pluck('name')->all();
        $affiliations = array_map(
            fn (string $affiliation): string => Affiliation::tryFrom($affiliation)?->getLabel() ?? $affiliation,
            $this->affiliations ?? [],
        );

        return implode(', ', [...$roles, ...$affiliations]) ?: 'Nobody';
    }

    /**
     * Published announcements showing now: started, and not ended.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function live(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $at ??= Carbon::now();

        return $query->whereNotNull('published_at')
            ->where('starts_at', '<=', $at)
            ->where(fn (Builder $ends) => $ends->whereNull('ends_at')->orWhere('ends_at', '>', $at));
    }

    /**
     * Published announcements that have started: live ones and ended ones, for the
     * Announcements page.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function started(Builder $query, ?CarbonInterface $at = null): Builder
    {
        return $query->whereNotNull('published_at')->where('starts_at', '<=', $at ?? Carbon::now());
    }

    /**
     * Announcements whose audience includes the person, or, with no person, signed-out visitors.
     * Super Administrators see every announcement.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function visibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user instanceof User) {
            return $query->where('audience', AnnouncementAudience::Public);
        }

        if ($user->hasPermissionTo(SystemPermission::ManageAll)) {
            return $query;
        }

        $roleIds = $user->roles->modelKeys();
        $affiliation = $user->primary_affiliation?->value;

        return $query->where(fn (Builder $audience) => $audience
            ->whereIn('audience', [AnnouncementAudience::Everyone, AnnouncementAudience::Public])
            ->orWhere(fn (Builder $targeted) => $targeted
                ->where('audience', AnnouncementAudience::Targeted)
                ->where(function (Builder $match) use ($roleIds, $affiliation): void {
                    // An empty target matches nobody, never everybody.
                    $match->whereRaw('1 = 0');

                    foreach ($roleIds as $roleId) {
                        $match->orWhereJsonContains('role_ids', $roleId);
                    }

                    if ($affiliation !== null) {
                        $match->orWhereJsonContains('affiliations', $affiliation);
                    }
                })));
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function notDismissedBy(Builder $query, User $user): Builder
    {
        return $query->whereDoesntHave('dismissals', fn (Builder $dismissal) => $dismissal->where('user_id', $user->getKey()));
    }

    /**
     * Most important first: by severity, then the newest start.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function byImportance(Builder $query): Builder
    {
        $ranks = collect(AnnouncementSeverity::cases())
            ->map(fn (AnnouncementSeverity $severity): string => "WHEN '{$severity->value}' THEN {$severity->rank()}")
            ->implode(' ');

        return $query->orderByRaw("CASE severity {$ranks} END DESC")->latest('starts_at')->orderByDesc('id');
    }
}
