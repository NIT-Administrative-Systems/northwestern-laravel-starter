<?php

declare(strict_types=1);

namespace Tests\Browser;

use Northwestern\SysDev\Chassis\Testing\Browser\Health;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Api\PendingAwaitablePage;

/**
 * Layout rules that Chassis's health checks leave out: nothing pushes the
 * page wider than the screen, nothing in the header overlaps, tables and
 * badges fit their containers, and touch controls are 44 pixels tall.
 */
final class Layout
{
    /**
     * Visit a path in a browser this many pixels wide. Below 1024 pixels
     * it has a touch screen, and below 768 pixels it acts as a phone.
     */
    public static function visit(string $path, int $width): PendingAwaitablePage
    {
        return visit($path, [
            'viewport' => ['width' => $width, 'height' => 900],
            'deviceScaleFactor' => 1,
            'isMobile' => $width < 768,
            'hasTouch' => $width < 1024,
        ]);
    }

    /**
     * Get everything wrong with the page's layout at its current width.
     *
     * With `$tapTargets`, a touch screen's controls must be 44px tall too.
     *
     * @return list<string>
     */
    public static function problems(AwaitableWebpage|PendingAwaitablePage $page, bool $tapTargets = false): array
    {
        // A table that loads its rows after the page appears is measured with them.
        Health::waitUntil($page, "() => document.querySelector('.fi-ta-content-loading') === null");

        /** @var list<string> */
        return Health::playwright($page)->evaluate(<<<'JS'
            (tapTargets) => {
                const viewport = document.documentElement.clientWidth;
                const shown = (element) => element.checkVisibility() && element.getBoundingClientRect().width > 0;
                const name = (element) => (element.innerText || element.value || element.getAttribute('aria-label') || element.tagName.toLowerCase()).trim().split('\n')[0].slice(0, 40);
                const problems = [];

                if (document.documentElement.scrollWidth > viewport) {
                    const widest = [...document.body.querySelectorAll('*')].filter(shown)
                        .filter((element) => element.getBoundingClientRect().right > viewport + 1)
                        .pop();

                    problems.push(`The page is ${document.documentElement.scrollWidth}px wide on a ${viewport}px screen, pushed out by "${widest ? name(widest) : 'unknown'}".`);
                }

                for (const bar of [...document.querySelectorAll('.fi-topbar, .nu-site-header')].filter(shown)) {
                    const items = [...bar.querySelectorAll('a, button, input, .nu-env-indicator')].filter(shown)
                        .filter((item) => ! item.closest('.sr-only'));

                    items.forEach((item, index) => items.slice(index + 1).forEach((other) => {
                        const [a, b] = [item.getBoundingClientRect(), other.getBoundingClientRect()];

                        if (! item.contains(other) && ! other.contains(item) && a.left < b.right - 1 && b.left < a.right - 1 && a.top < b.bottom - 1 && b.top < a.bottom - 1) {
                            problems.push(`In the header, "${name(item)}" overlaps "${name(other)}".`);
                        }
                    }));
                }

                for (const table of [...document.querySelectorAll('.fi-ta-content-ctn')].filter(shown)) {
                    if (table.scrollWidth > table.clientWidth + 1) {
                        const heading = table.closest('.fi-ta-ctn')?.querySelector('.fi-ta-header-heading')?.innerText ?? 'A table';

                        problems.push(`${heading} is ${table.scrollWidth}px wide in its ${table.clientWidth}px container.`);
                    }
                }

                for (const badge of [...document.querySelectorAll('.fi-ta-cell .fi-badge, .fi-in-entry .fi-badge')].filter(shown)) {
                    const container = badge.closest('.fi-ta-cell, .fi-in-entry-content-ctn').getBoundingClientRect();

                    if (badge.getBoundingClientRect().right > container.right + 1) {
                        problems.push(`The badge "${name(badge)}" runs past its cell.`);
                    }
                }

                if (tapTargets && matchMedia('(pointer: coarse)').matches) {
                    const controls = 'button, summary, select, textarea, [role="button"], a.fi-btn, a.fi-link, input:not([type="hidden"], [type="checkbox"], [type="radio"])';

                    for (const control of [...document.querySelectorAll(controls)].filter(shown)) {
                        // A field's box is its wrapper, which can be taller than its input.
                        const height = Math.round((control.closest('.fi-input-wrp') ?? control).getBoundingClientRect().height);

                        if (height < 44 && ! control.closest('.sr-only')) {
                            problems.push(`The control "${name(control)}" is ${height}px tall, under the 44px a finger needs.`);
                        }
                    }
                }

                return [...new Set(problems)];
            }
            JS, $tapTargets);
    }
}
