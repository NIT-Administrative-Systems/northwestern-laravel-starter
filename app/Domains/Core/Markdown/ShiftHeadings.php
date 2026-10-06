<?php

declare(strict_types=1);

namespace App\Domains\Core\Markdown;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\ExtensionInterface;

/**
 * Moves a document's headings so its shallowest one sits at a given level, keeping their
 * relative depth. Markdown written to stand alone can then follow the page's own headings
 * without skipping a level: "### Changes" under a page's <h1> becomes an <h2>.
 */
final readonly class ShiftHeadings implements ExtensionInterface
{
    /**
     * @param  int<1, 6>  $topLevel
     */
    public function __construct(private int $topLevel)
    {
        //
    }

    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addEventListener(DocumentParsedEvent::class, function (DocumentParsedEvent $event): void {
            $headings = [];

            foreach ($event->getDocument()->iterator() as $node) {
                if ($node instanceof Heading) {
                    $headings[] = $node;
                }
            }

            if ($headings === []) {
                return;
            }

            $shift = $this->topLevel - min(array_map(fn (Heading $heading): int => $heading->getLevel(), $headings));

            foreach ($headings as $heading) {
                $heading->setLevel(max(1, min(6, $heading->getLevel() + $shift)));
            }
        });
    }
}
