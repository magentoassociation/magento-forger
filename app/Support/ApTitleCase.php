<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Support;

/**
 * AP style title case, per design/specs/README.md, *Capitalisation*: capitalise every word
 * except articles and prepositions/conjunctions of three letters or fewer, which stay
 * lowercase unless they are the first or last word. The word after a colon starts a new
 * title ("Progress: Ready for Dev"). Words already in capitals ("PR") are left as they are.
 */
class ApTitleCase
{
    /**
     * Articles, plus prepositions and conjunctions of three letters or fewer.
     */
    private const SMALL_WORDS = [
        'a', 'an', 'the',
        'and', 'as', 'at', 'but', 'by', 'for', 'if', 'in', 'nor', 'of', 'off', 'on', 'or', 'per', 'so', 'to', 'up', 'via', 'yet',
    ];

    public static function apply(string $text): string
    {
        $words = explode(' ', $text);
        $last = count($words) - 1;
        $startsTitle = true;

        foreach ($words as $i => $word) {
            $small = in_array(mb_strtolower($word), self::SMALL_WORDS, true);
            if ($small && ! $startsTitle && $i !== $last) {
                $words[$i] = mb_strtolower($word);
            } elseif ($word !== mb_strtoupper($word)) {
                $words[$i] = mb_strtoupper(mb_substr($word, 0, 1)).mb_substr($word, 1);
            }
            $startsTitle = str_ends_with($word, ':');
        }

        return implode(' ', $words);
    }
}
