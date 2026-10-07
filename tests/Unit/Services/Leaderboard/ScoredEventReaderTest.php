<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Unit\Services\Leaderboard;

use App\DataTransferObjects\Leaderboard\Action;
use App\DataTransferObjects\Leaderboard\Board;
use App\DataTransferObjects\Leaderboard\ScoredEvent;
use App\Services\Leaderboard\ScoredEventReader;
use Carbon\Carbon;
use OpenSearch\Client;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class ScoredEventReaderTest extends TestCase
{
    public function testReadTreatsMissingIndexAsEmpty(): void
    {
        // A fresh or short-window bootstrap can leave a stream's index
        // uncreated (e.g. github-events with no rows yet). read() must pass
        // ignore_unavailable so a missing index returns empty, not a 404.
        $client = $this->createMock(Client::class);
        $client->expects($this->atLeastOnce())
            ->method('search')
            ->with($this->callback(function (array $params): bool {
                return ($params['ignore_unavailable'] ?? null) === true;
            }))
            ->willReturn(['hits' => ['hits' => []]]);

        $reader = new ScoredEventReader($client);

        $events = $reader->read(Carbon::parse('2026-01-01T00:00:00Z'), Carbon::parse('2026-01-15T00:00:00Z'));

        $this->assertSame([], $events);
    }

    public function testPrLookupsTolerateMissingPullRequestsIndex(): void
    {
        // One review and one PR label row force pullRequestsInfo() and prTitles() to run.
        $searches = [];
        $client = $this->createMock(Client::class);
        $client->method('search')->willReturnCallback(function (array $params) use (&$searches): array {
            $searches[] = $params;
            $row = match (true) {
                str_contains($params['index'], 'github-pr-reviews') => [
                    'author' => 'jane', 'state' => 'COMMENTED', 'submitted_at' => '2026-01-05T00:00:00Z', 'pr_number' => 7,
                ],
                str_contains($params['index'], 'github-pr-timeline') => [
                    'actor' => 'jane', 'label_name' => 'Priority: P2', 'pr_number' => 8, 'created_at' => '2026-01-05T00:00:00Z',
                ],
                default => null,
            };

            return ['hits' => ['hits' => $row === null ? [] : [['_source' => $row]]]];
        });

        $events = (new ScoredEventReader($client))
            ->read(Carbon::parse('2026-01-01T00:00:00Z'), Carbon::parse('2026-01-15T00:00:00Z'));

        $lookups = array_filter($searches, fn (array $p): bool => isset($p['body']['_source']));
        $this->assertCount(2, $lookups); // pullRequestsInfo + prTitles
        foreach ($searches as $params) {
            $this->assertTrue($params['ignore_unavailable'] ?? false, $params['index']);
        }
        // No PR docs → titles fall back to "PR #n".
        $titles = array_map(fn ($e): ?string => $e->title, $events);
        $this->assertContains('PR #7', $titles);
        $this->assertContains('PR #8', $titles);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $excluded
     * @return list<ScoredEvent>
     */
    private function buildLabelEvents(array $rows, array $excluded = [], string $repo = ''): array
    {
        $reader = (new ReflectionClass(ScoredEventReader::class))->newInstanceWithoutConstructor();

        return (new ReflectionMethod(ScoredEventReader::class, 'buildLabelEvents'))->invoke($reader, $rows, $excluded, $repo);
    }

    public function testPrLabelEventCarriesPrTitleAndLink(): void
    {
        $events = $this->buildLabelEvents([
            ['actor' => 'mod', 'label' => 'bug', 'target' => 'pr:77', 'date' => Carbon::parse('2026-01-01T00:00:00Z')],
        ], [], 'magento/magento2');

        $this->assertSame('PR #77', $events[0]->title);
        $this->assertSame('https://github.com/magento/magento2/pull/77', $events[0]->url);
    }

    public function testIssueLabelEventSurfacesLabelNameWithoutLink(): void
    {
        $events = $this->buildLabelEvents([
            ['actor' => 'mod', 'label' => 'bug', 'target' => 'issue:1', 'date' => Carbon::parse('2026-01-01T00:00:00Z')],
        ], [], 'magento/magento2');

        $this->assertSame("'bug' label", $events[0]->title);
        $this->assertNull($events[0]->url);
    }

    public function testDedupesSameActorTargetLabelKeepingEarliest(): void
    {
        $early = Carbon::parse('2026-01-01T00:00:00Z');
        $late = Carbon::parse('2026-02-01T00:00:00Z');

        $events = $this->buildLabelEvents([
            ['actor' => 'mod', 'label' => 'bug', 'target' => 'issue:1', 'date' => $late],
            ['actor' => 'mod', 'label' => 'bug', 'target' => 'issue:1', 'date' => $early],
        ]);

        $this->assertCount(1, $events);
        $this->assertSame(Action::LABEL_APPLIED, $events[0]->action);
        $this->assertSame(Board::MAINTAINER, $events[0]->board);
        $this->assertTrue($events[0]->date->equalTo($early));
    }

    public function testExcludesConfiguredLabels(): void
    {
        $events = $this->buildLabelEvents([
            [
                'actor' => 'mod',
                'label' => 'Progress: pending review',
                'target' => 'pr:5',
                'date' => Carbon::parse('2026-01-01T00:00:00Z'),
            ],
        ], ['Progress: pending review']);

        $this->assertSame([], $events);
    }

    public function testCreditsDistinctActorsSeparately(): void
    {
        $date = Carbon::parse('2026-01-01T00:00:00Z');

        $events = $this->buildLabelEvents([
            ['actor' => 'a', 'label' => 'bug', 'target' => 'issue:1', 'date' => $date],
            ['actor' => 'b', 'label' => 'bug', 'target' => 'issue:1', 'date' => $date],
        ]);

        $this->assertCount(2, $events);
    }

    public function testSkipsRowsMissingActorOrLabel(): void
    {
        $date = Carbon::parse('2026-01-01T00:00:00Z');

        $events = $this->buildLabelEvents([
            ['actor' => null, 'label' => 'bug', 'target' => 'issue:1', 'date' => $date],
            ['actor' => 'mod', 'label' => '', 'target' => 'issue:2', 'date' => $date],
        ]);

        $this->assertSame([], $events);
    }
}
