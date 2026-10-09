<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Queries\Dashboard;

use App\Services\Search\OpenSearchService;
use OpenSearch\Client;

/**
 * Community Pick Candidates: open, non-draft PRs carrying none of the configured
 * excluded labels, ranked by 👍 reactions on the PR body, oldest first on ties.
 *
 * Area/Component filtering matches Effective Labels — the PR's own labels plus its
 * Linked Issues' labels. OpenSearch has no joins, so Linked Issue labels are read
 * from the issues index on every request; retagging an issue needs no PR re-sync.
 */
class CommunityPickCandidatesQuery
{
    public const PER_PAGE = 50;

    public const AREA_PREFIX = 'Area: ';

    public const COMPONENT_PREFIX = 'Component: ';

    private const FILTER_LABEL_PATTERN = '(Area|Component): .*';

    public function __construct(private readonly Client $client) {}

    /**
     * @return array{
     *     rows: list<array{number: int, title: string, url: string, author: ?string, created_at: string, thumbs_up_count: int, linked_issues: list<int>, labels: list<string>}>,
     *     total: int,
     *     areaOptions: list<string>,
     *     componentOptions: list<string>
     * }
     *
     * @throws \Exception When OpenSearch fails, including a missing index.
     */
    public function execute(?string $area = null, ?string $component = null, int $page = 1): array
    {
        $candidates = $this->candidateQuery();
        [$linkedIssues, $prLabels] = $this->candidateFacets($candidates);
        $issueLabels = $this->issueLabels($linkedIssues);

        $query = $candidates;
        foreach (array_filter([$area, $component]) as $label) {
            $query['bool']['filter'][] = $this->matchesEffectiveLabel($label, $issueLabels);
        }

        $response = $this->client->search([
            'index' => $this->pullRequestIndex(),
            'body' => [
                'from' => (max($page, 1) - 1) * self::PER_PAGE,
                'size' => self::PER_PAGE,
                'track_total_hits' => true,
                '_source' => ['id', 'title', 'url', 'author', 'created_at', 'thumbs_up_count', 'linked_issues', 'labels'],
                'query' => $query,
                // unmapped_type: the field only exists once a sync has written it.
                'sort' => [
                    ['thumbs_up_count' => ['order' => 'desc', 'missing' => '_last', 'unmapped_type' => 'long']],
                    ['created_at' => ['order' => 'asc']],
                ],
            ],
        ]);

        $options = array_unique(array_merge($prLabels, ...array_values($issueLabels)));
        sort($options);

        return [
            'rows' => array_map(fn (array $hit): array => $this->toRow($hit['_source'], $issueLabels), $response['hits']['hits'] ?? []),
            'total' => (int) ($response['hits']['total']['value'] ?? 0),
            'areaOptions' => $this->withPrefix($options, self::AREA_PREFIX),
            'componentOptions' => $this->withPrefix($options, self::COMPONENT_PREFIX),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function candidateQuery(): array
    {
        return ['bool' => [
            'filter' => [['term' => ['is_open' => true]]],
            'must_not' => [
                ['term' => ['is_draft' => true]],
                ['terms' => ['labels.keyword' => config('github.community_picked.exclude_labels', [])]],
            ],
        ]];
    }

    /**
     * Linked Issue numbers and the PRs' own Area/Component labels across every candidate.
     *
     * @param  array<string, mixed>  $candidates
     * @return array{list<int>, list<string>}
     */
    private function candidateFacets(array $candidates): array
    {
        $response = $this->client->search([
            'index' => $this->pullRequestIndex(),
            'body' => [
                'size' => 0,
                'query' => $candidates,
                'aggs' => [
                    'linked_issues' => ['terms' => ['field' => 'linked_issues', 'size' => 10000]],
                    'pr_labels' => ['terms' => ['field' => 'labels.keyword', 'size' => 1000, 'include' => self::FILTER_LABEL_PATTERN]],
                ],
            ],
        ]);

        return [
            array_map(static fn (array $b): int => (int) $b['key'], $response['aggregations']['linked_issues']['buckets'] ?? []),
            array_map(static fn (array $b): string => (string) $b['key'], $response['aggregations']['pr_labels']['buckets'] ?? []),
        ];
    }

    /**
     * Area/Component labels of each given issue, keyed by issue number.
     *
     * @param  list<int>  $issueNumbers
     * @return array<int, list<string>>
     */
    private function issueLabels(array $issueNumbers): array
    {
        if ($issueNumbers === []) {
            return [];
        }

        $response = $this->client->search([
            'index' => OpenSearchService::getIndexWithPrefix(OpenSearchService::OPENSEARCH_GITHUB_ISSUES_INDEX),
            'body' => [
                // ponytail: one page of 10,000 issues — candidates link far fewer today; page this if that changes.
                'size' => min(count($issueNumbers), 10000),
                '_source' => ['labels'],
                'query' => ['ids' => ['values' => array_map('strval', $issueNumbers)]],
            ],
        ]);

        $labels = [];
        foreach ($response['hits']['hits'] ?? [] as $hit) {
            $labels[(int) $hit['_id']] = $this->filterLabels($hit['_source']['labels'] ?? []);
        }

        return $labels;
    }

    /**
     * A PR matches when it carries the label itself or any of its Linked Issues does.
     *
     * @param  array<int, list<string>>  $issueLabels
     * @return array<string, mixed>
     */
    private function matchesEffectiveLabel(string $label, array $issueLabels): array
    {
        $should = [['term' => ['labels.keyword' => $label]]];

        $issues = array_keys(array_filter($issueLabels, static fn (array $labels): bool => in_array($label, $labels, true)));
        if ($issues !== []) {
            $should[] = ['terms' => ['linked_issues' => $issues]];
        }

        return ['bool' => ['should' => $should, 'minimum_should_match' => 1]];
    }

    /**
     * @param  array<string, mixed>  $source
     * @param  array<int, list<string>>  $issueLabels
     * @return array{number: int, title: string, url: string, author: ?string, created_at: string, thumbs_up_count: int, linked_issues: list<int>, labels: list<string>}
     */
    private function toRow(array $source, array $issueLabels): array
    {
        $linkedIssues = array_map('intval', $source['linked_issues'] ?? []);

        $labels = $this->filterLabels($source['labels'] ?? []);
        foreach ($linkedIssues as $issue) {
            $labels = array_merge($labels, $issueLabels[$issue] ?? []);
        }
        $labels = array_values(array_unique($labels));
        sort($labels);

        return [
            'number' => (int) $source['id'],
            'title' => $source['title'],
            'url' => $source['url'],
            'author' => $source['author'] ?? null,
            'created_at' => $source['created_at'],
            'thumbs_up_count' => (int) ($source['thumbs_up_count'] ?? 0),
            'linked_issues' => $linkedIssues,
            'labels' => $labels,
        ];
    }

    /**
     * @param  list<string>  $labels
     * @return list<string>
     */
    private function filterLabels(array $labels): array
    {
        return array_values(array_filter(
            $labels,
            static fn (string $label): bool => preg_match('/^'.self::FILTER_LABEL_PATTERN.'$/', $label) === 1,
        ));
    }

    /**
     * @param  list<string>  $labels
     * @return list<string>
     */
    private function withPrefix(array $labels, string $prefix): array
    {
        return array_values(array_filter($labels, static fn (string $label): bool => str_starts_with($label, $prefix)));
    }

    private function pullRequestIndex(): string
    {
        return OpenSearchService::getIndexWithPrefix(OpenSearchService::OPENSEARCH_GITHUB_PULL_REQUESTS_INDEX);
    }
}
