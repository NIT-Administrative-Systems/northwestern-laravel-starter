<?php

declare(strict_types=1);

namespace App\Domains\Foundation\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Eloquent cast that transforms backtick-wrapped JIRA issue identifiers into clickable links.
 *
 * The identifier prefix (e.g., "PROJ", "GSTS") is read from `config('changelog.jira.identifier')`,
 * so each project can configure its own pattern. When the identifier is not set, the cast is a
 * no-op and backtick-wrapped text remains as plain code formatting.
 *
 * Example: `PROJ-1234` becomes <a href="https://..."><code>PROJ-1234</code></a>
 *
 * @implements CastsAttributes<string, string>
 */
class MarkdownWithJiraLinksCast implements CastsAttributes
{
    /**
     * Transform the stored Markdown by replacing JIRA issue identifiers with links.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): string
    {
        /** @var string|null $identifier */
        $identifier = config('changelog.jira.identifier');

        /** @var string|null $baseUrl */
        $baseUrl = config('changelog.jira.url');

        if (! $identifier || ! $baseUrl) {
            return $value ?? '';
        }

        $pattern = sprintf('/`(%s-\d+)`/', preg_quote($identifier, '/'));

        /** @var string */
        return Str::replaceMatches($pattern, static function (array $match) use ($baseUrl): string {
            $issueId = $match[1];

            return sprintf(
                '<a href="%s/browse/%s" target="_blank" rel="noopener"><code>%s</code></a>',
                rtrim($baseUrl, '/'),
                $issueId,
                $issueId,
            );
        }, (string) ($value ?? ''));
    }

    /**
     * Store the raw Markdown value without transformation.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        return $value ?? '';
    }
}
