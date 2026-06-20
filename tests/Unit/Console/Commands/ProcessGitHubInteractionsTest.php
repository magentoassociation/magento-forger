<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Unit\Console\Commands;

use App\Console\Commands\ProcessGitHubInteractions;
use Tests\TestCase;

class ProcessGitHubInteractionsTest extends TestCase
{
    private function assignPoints(string $interaction): int
    {
        $command = new ProcessGitHubInteractions;
        $method = new \ReflectionMethod(ProcessGitHubInteractions::class, 'assignPoints');

        return $method->invoke($command, $interaction);
    }

    public function test_comment_scores_5(): void
    {
        $this->assertSame(5, $this->assignPoints('comment'));
    }

    public function test_assigned_scores_8(): void
    {
        $this->assertSame(8, $this->assignPoints('assigned'));
    }

    public function test_closed_scores_10(): void
    {
        $this->assertSame(10, $this->assignPoints('closed'));
    }

    public function test_labeled_scores_5(): void
    {
        $this->assertSame(5, $this->assignPoints('labeled'));
    }

    public function test_unlabeled_scores_5(): void
    {
        $this->assertSame(5, $this->assignPoints('unlabeled'));
    }

    public function test_mentioned_scores_3(): void
    {
        $this->assertSame(3, $this->assignPoints('mentioned'));
    }

    public function test_comment_deleted_scores_negative_2(): void
    {
        $this->assertSame(-2, $this->assignPoints('comment_deleted'));
    }

    public function test_unknown_interaction_scores_0(): void
    {
        $this->assertSame(0, $this->assignPoints('some_unknown_event'));
    }

    public function test_commented_does_not_match_and_scores_0(): void
    {
        // 'commented' was the old incorrect key; the correct key is 'comment'
        $this->assertSame(0, $this->assignPoints('commented'));
    }
}
