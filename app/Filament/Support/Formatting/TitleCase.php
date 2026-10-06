<?php

declare(strict_types=1);

namespace App\Filament\Support\Formatting;

/**
 * Title case in Chicago headline style, the rule for names in the interface (see "Interface copy"
 * in .github/copilot-instructions.md): articles, coordinating conjunctions and prepositions stay
 * lowercase unless they come first or last, and words that already carry capitals ("NetID",
 * "MCP", "myHR") and placeholders (":label") keep their spelling.
 *
 * A word that looks like a preposition but finishes a verb ("Sign In", "Opt Out", "Set Up") is
 * capitalized, as is the second part of a hyphenated word ("Sign-In Records").
 */
final class TitleCase
{
    private const array ARTICLES_AND_CONJUNCTIONS = ['a', 'an', 'the', 'and', 'but', 'or', 'for', 'nor', 'as'];

    private const array PREPOSITIONS = [
        'about', 'above', 'across', 'after', 'against', 'along', 'among', 'around', 'at', 'before',
        'behind', 'below', 'between', 'beyond', 'by', 'down', 'during', 'except', 'from', 'in',
        'inside', 'into', 'near', 'of', 'off', 'on', 'onto', 'out', 'outside', 'over', 'past', 'per',
        'since', 'through', 'to', 'toward', 'under', 'until', 'up', 'upon', 'via', 'with', 'within',
        'without',
    ];

    /** Verbs whose particle is part of the verb, so it's capitalized: "Sign In", "Opt Out". */
    private const array PHRASAL_VERBS = ['sign', 'log', 'opt', 'set', 'turn', 'look', 'check', 'follow', 'back', 'roll'];

    public static function of(string $text): string
    {
        $words = explode(' ', $text);
        $last = count($words) - 1;

        foreach ($words as $index => $word) {
            $parts = explode('-', $word);

            foreach ($parts as $part => $piece) {
                $previous = $part > 0 ? $parts[$part - 1] : ($words[$index - 1] ?? '');
                $parts[$part] = self::word(
                    $piece,
                    first: $index === 0 && $part === 0,
                    last: $index === $last && $part === count($parts) - 1,
                    afterHyphen: $part > 0,
                    afterVerb: in_array(mb_strtolower($previous), self::PHRASAL_VERBS, true),
                );
            }

            $words[$index] = implode('-', $parts);
        }

        return implode(' ', $words);
    }

    private static function word(string $word, bool $first, bool $last, bool $afterHyphen, bool $afterVerb): string
    {
        // Placeholders, acronyms and words with inner capitals keep their spelling.
        if ($word === '' || str_starts_with($word, ':') || preg_match('/\p{Lu}/u', mb_substr($word, 1)) === 1) {
            return $word;
        }

        $lower = mb_strtolower($word);
        $minor = in_array($lower, self::ARTICLES_AND_CONJUNCTIONS, true)
            || (in_array($lower, self::PREPOSITIONS, true) && ! $afterHyphen && ! $afterVerb);

        if ($minor && ! $first && ! $last) {
            return $lower;
        }

        return mb_strtoupper(mb_substr($word, 0, 1)) . mb_substr($word, 1);
    }
}
