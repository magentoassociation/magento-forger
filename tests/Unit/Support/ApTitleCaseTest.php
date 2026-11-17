<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\ApTitleCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ApTitleCaseTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function titles(): array
    {
        return [
            'lowercase words capitalised' => ['ready for grooming', 'Ready for Grooming'],
            'short preposition lowercased' => ['Ready For Work', 'Ready for Work'],
            'verb and adverb capitalised however short' => ['Format is not valid', 'Format Is Not Valid'],
            'acronym kept' => ['PR Created', 'PR Created'],
            'acronym mid-title kept' => ['PR in progress', 'PR in Progress'],
            'first word small but capitalised' => ['on hold', 'On Hold'],
            'last word small but capitalised' => ['what dreams are made of', 'What Dreams Are Made Of'],
            'article lowercased' => ['pick an issue', 'Pick an Issue'],
            'word after colon starts a title' => ['Progress: ready for dev', 'Progress: Ready for Dev'],
            'small word after colon capitalised' => ['Issue: on hold', 'Issue: On Hold'],
        ];
    }

    #[DataProvider('titles')]
    public function testAppliesApTitleCase(string $text, string $expected): void
    {
        $this->assertSame($expected, ApTitleCase::apply($text));
    }
}
