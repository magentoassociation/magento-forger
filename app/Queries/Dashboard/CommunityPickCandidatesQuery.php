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
 * excluded labels, ranked by 👍 reactions on the PR body, oldest first on ties
 * (or by age — see SORTS).
 *
 * Area/Component filtering matches Effective Labels — the PR's own labels plus its
 * Linked Issues' labels. OpenSearch has no joins, so Linked Issue labels are read
 * from the issues index on every request; retagging an issue needs no PR re-sync.
 * The author filter matches the PR author's GitHub login, ignoring case.
 */
class CommunityPickCandidatesQuery
{
    public const PER_PAGE = 50;

    public const AREA_PREFIX = 'Area: ';

    public const COMPONENT_PREFIX = 'Component: ';

    public const SORT_VOTES = 'votes';

    public const SORT_OLDEST = 'oldest';

    public const SORT_NEWEST = 'newest';

    public const SORTS = [self::SORT_VOTES, self::SORT_OLDEST, self::SORT_NEWEST];

    private const FILTER_LABEL_PATTERN = '(Area|Component): .*';

    public function __construct(private readonly Client $client) {}

    /**
     * @return array{
     *     rows: list<array{number: int, title: string, url: string, author: ?string, created_at: string, thumbs_up_count: int, linked_issues: list<int>, labels: list<string>}>,
     *     total: int,
     *     areaOptions: list<string>,
     *     componentOptions: list<string>,
     *     authorOptions: list<string>
     * }
     *
     * @throws \Exception When OpenSearch fails, including a missing index.
     */
    public function execute(
        ?string $area = null,
        ?string $component = null,
        ?string $author = null,
        int $page = 1,
        string $sort = self::SORT_VOTES,
    ): array {
        $candidates = $this->candidateQuery();
        $allFacets = $this->candidateFacets($candidates);
        $issueLabels = $this->issueLabels($allFacets['linkedIssues']);

        $clauses = array_filter([
            'area' => $area ? $this->matchesEffectiveLabel($area, $issueLabels) : null,
            'component' => $component ? $this->matchesEffectiveLabel($component, $issueLabels) : null,
            // GitHub logins are case-insensitive.
            'author' => $author ? ['term' => ['author.keyword' => ['value' => $author, 'case_insensitive' => true]]] : null,
        ]);
        $query = $this->narrow($candidates, $clauses);

        // Each filter offers only values on candidates matching the other selections,
        // so a combination picked from the lists never comes up empty.
        $cache = [];
        $facetsWithout = function (string $filter) use ($candidates, $clauses, $allFacets, &$cache): array {
            $others = array_diff_key($clauses, [$filter => true]);
            if ($others === []) {
                return $allFacets;
            }
            $key = implode(',', array_keys($others));

            return $cache[$key] ??= $this->candidateFacets($this->narrow($candidates, $others));
        };
        $areaFacets = $facetsWithout('area');
        $componentFacets = $facetsWithout('component');
        $authorOptions = $facetsWithout('author')['authors'];
        usort($authorOptions, 'strcasecmp');

        $response = $this->client->search([
            'index' => $this->pullRequestIndex(),
            'body' => [
                'from' => (max($page, 1) - 1) * self::PER_PAGE,
                'size' => self::PER_PAGE,
                'track_total_hits' => true,
                '_source' => ['id', 'title', 'url', 'author', 'created_at', 'thumbs_up_count', 'linked_issues', 'labels'],
                'query' => $query,
                'sort' => $this->sortClauses($sort),
            ],
        ]);

        return [
            'rows' => array_map(fn (array $hit): array => $this->toRow($hit['_source'], $issueLabels), $response['hits']['hits'] ?? []),
            'total' => (int) ($response['hits']['total']['value'] ?? 0),
            'areaOptions' => $this->withPrefix($this->labelOptions($areaFacets, $issueLabels), self::AREA_PREFIX),
            'componentOptions' => $this->withPrefix($this->labelOptions($componentFacets, $issueLabels), self::COMPONENT_PREFIX),
            'authorOptions' => $authorOptions,
        ];
    }

    /**
     * Most votes: 👍 desc, oldest first on ties. By age: created_at, most votes first on ties.
     *
     * @return list<array<string, array<string, string>>>
     */
    private function sortClauses(string $sort): array
    {
        // unmapped_type: the field only exists once a sync has written it.
        $votes = ['thumbs_up_count' => ['order' => 'desc', 'missing' => '_last', 'unmapped_type' => 'long']];

        return match ($sort) {
            self::SORT_OLDEST => [['created_at' => ['order' => 'asc']], $votes],
            self::SORT_NEWEST => [['created_at' => ['order' => 'desc']], $votes],
            default => [$votes, ['created_at' => ['order' => 'asc']]],
        };
    }

    /**
     * @param  array<string, mixed>  $candidates
     * @param  array<string, array<string, mixed>>  $clauses  Filter clauses keyed by filter name.
     * @return array<string, mixed>
     */
    private function narrow(array $candidates, array $clauses): array
    {
        foreach ($clauses as $clause) {
            $candidates['bool']['filter'][] = $clause;
        }

        return $candidates;
    }

    /**
     * Sorted union of the PRs' own Area/Component labels and those of their Linked Issues.
     * Narrowed facets' Linked Issues are a subset of every candidate's, so $issueLabels
     * already covers them.
     *
     * @param  array{linkedIssues: list<int>, prLabels: list<string>, authors: list<string>}  $facets
     * @param  array<int, list<string>>  $issueLabels
     * @return list<string>
     */
    private function labelOptions(array $facets, array $issueLabels): array
    {
        $options = $facets['prLabels'];
        foreach ($facets['linkedIssues'] as $issue) {
            $options = array_merge($options, $issueLabels[$issue] ?? []);
        }
        $options = array_values(array_unique($options));
        sort($options);

        return $options;
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
     * Linked Issue numbers, the PRs' own Area/Component labels, and authors across the given PRs.
     *
     * @param  array<string, mixed>  $candidates
     * @return array{linkedIssues: list<int>, prLabels: list<string>, authors: list<string>}
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
                    'authors' => ['terms' => ['field' => 'author.keyword', 'size' => 10000]],
                ],
            ],
        ]);

        $keys = static fn (string $agg): array => array_column($response['aggregations'][$agg]['buckets'] ?? [], 'key');

        return [
            'linkedIssues' => array_map('intval', $keys('linked_issues')),
            'prLabels' => array_map('strval', $keys('pr_labels')),
            'authors' => array_map('strval', $keys('authors')),
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
