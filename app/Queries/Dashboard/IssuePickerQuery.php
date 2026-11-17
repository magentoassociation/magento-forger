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
 * Issue picker: open issues narrowed by Area and Component label, with taken issues
 * (assigned, or with an open or merged PR that closes them) left out unless asked for.
 *
 * Area options are every Area label on an open issue the Show chips leave in; Component
 * options are the Component labels on those issues that also carry the selected Area.
 * Both are sorted by name, ignoring case.
 *
 * "Has a PR" reads two sources. Open PRs: the Linked Issues of open PRs in the PR index,
 * which the reactions sync refreshes every 15 minutes. Merged PRs: the issue's
 * closed_by_merged_pr. A PR closed without merging is in neither, so its issue stays.
 * The issue's own has_closing_pr is deliberately not used: opening or closing a PR does
 * not bump the issue's updatedAt, so the incremental issue sync leaves it stale both ways.
 */
class IssuePickerQuery
{
    public const PER_PAGE = 25;

    /**
     * OpenSearch refuses from + size beyond 10,000 hits (max_result_window).
     */
    public const MAX_ROWS = 10000;

    public const SORT_OLDEST = 'oldest';

    public const SORT_NEWEST = 'newest';

    public const SORT_COMMENTS = 'comments';

    public const SORTS = [self::SORT_OLDEST, self::SORT_NEWEST, self::SORT_COMMENTS];

    public function __construct(private readonly Client $client) {}

    /**
     * @return array{
     *     rows: list<array{number: int, title: string, url: string, areas: list<string>, components: list<string>,
     *     created_at: string, comments_count: int}>,
     *     total: int, openTotal: int, areaOptions: list<string>, componentOptions: list<string>,
     *     component: ?string
     * }
     *
     * @throws \Exception When OpenSearch fails, including a missing index.
     */
    public function execute(
        ?string $area = null,
        ?string $component = null,
        bool $showAssigned = false,
        bool $showPr = false,
        int $rows = self::PER_PAGE,
        string $sort = self::SORT_OLDEST,
    ): array {
        // Taken issues the Show chips leave out.
        $hidden = [];
        if (! $showAssigned) {
            $hidden[] = ['exists' => ['field' => 'assignees']];
        }
        if (! $showPr) {
            $hidden[] = ['term' => ['closed_by_merged_pr' => true]];
            $withOpenPr = $this->issuesWithOpenPr();
            if ($withOpenPr !== []) {
                $hidden[] = ['ids' => ['values' => array_map('strval', $withOpenPr)]];
            }
        }

        $facets = $this->facets($area, $hidden);

        // The component no longer fits the area (or never existed): drop it rather than
        // show an empty list for a combination the dropdowns could not have produced.
        if ($component !== null && ! $this->hasOption($facets['componentOptions'], $component)) {
            $component = null;
        }

        $query = ['bool' => ['filter' => [['term' => ['is_open' => true]]]]];
        foreach (array_filter([$area, $component]) as $label) {
            $query['bool']['filter'][] = $this->hasLabel($label);
        }
        if ($hidden !== []) {
            $query['bool']['must_not'] = $hidden;
        }

        $response = $this->client->search([
            'index' => $this->issueIndex(),
            'body' => [
                'size' => max($rows, 1),
                'track_total_hits' => true,
                '_source' => ['id', 'title', 'url', 'labels', 'created_at', 'comments_count'],
                'query' => $query,
                'sort' => $this->sortClauses($sort),
            ],
        ]);

        return [
            'rows' => array_map(fn (array $hit): array => $this->toRow($hit['_source']), $response['hits']['hits'] ?? []),
            'total' => (int) ($response['hits']['total']['value'] ?? 0),
            'openTotal' => $facets['openTotal'],
            'areaOptions' => $facets['areaOptions'],
            'componentOptions' => $facets['componentOptions'],
            'component' => $component,
        ];
    }

    /**
     * Open-issue total and the label options, sorted by name, in one search. Options come
     * only from issues the Show chips leave in, so none of them leads to an empty list;
     * the total counts every open issue.
     *
     * @param  list<array<string, mixed>>  $hidden  Clauses matching the issues the chips leave out.
     * @return array{openTotal: int, areaOptions: list<string>, componentOptions: list<string>}
     */
    private function facets(?string $area, array $hidden): array
    {
        $labels = static fn (string $pattern): array => [
            'terms' => [
                'field' => 'labels.keyword',
                'size' => 1000,
                'include' => $pattern,
                'order' => ['_key' => 'asc'],
            ],
        ];

        $response = $this->client->search([
            'index' => $this->issueIndex(),
            'body' => [
                'size' => 0,
                'track_total_hits' => true,
                'query' => ['term' => ['is_open' => true]],
                'aggs' => [
                    'shown' => [
                        'filter' => $hidden ? ['bool' => ['must_not' => $hidden]] : ['match_all' => (object) []],
                        'aggs' => [
                            'areas' => $labels(CommunityPickCandidatesQuery::AREA_PREFIX.'.*'),
                            'components' => [
                                'filter' => $area ? $this->hasLabel($area) : ['match_all' => (object) []],
                                'aggs' => ['labels' => $labels(CommunityPickCandidatesQuery::COMPONENT_PREFIX.'.*')],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        // OpenSearch orders keys byte-wise (uppercase first); re-sort ignoring case.
        $keys = static function (array $buckets): array {
            $labels = array_map('strval', array_column($buckets, 'key'));
            sort($labels, SORT_STRING | SORT_FLAG_CASE);

            return $labels;
        };

        return [
            'openTotal' => (int) ($response['hits']['total']['value'] ?? 0),
            'areaOptions' => $keys($response['aggregations']['shown']['areas']['buckets'] ?? []),
            'componentOptions' => $keys($response['aggregations']['shown']['components']['labels']['buckets'] ?? []),
        ];
    }

    /**
     * Numbers of the issues an open PR (draft or not) says it closes.
     *
     * @return list<int>
     */
    private function issuesWithOpenPr(): array
    {
        $response = $this->client->search([
            'index' => OpenSearchService::getIndexWithPrefix(OpenSearchService::OPENSEARCH_GITHUB_PULL_REQUESTS_INDEX),
            'body' => [
                'size' => 0,
                'query' => ['term' => ['is_open' => true]],
                'aggs' => ['linked_issues' => ['terms' => ['field' => 'linked_issues', 'size' => 10000]]],
            ],
        ]);

        return array_map('intval', array_column($response['aggregations']['linked_issues']['buckets'] ?? [], 'key'));
    }

    /**
     * GitHub label names are unique ignoring case; a shared link may carry another case.
     *
     * @return array<string, mixed>
     */
    private function hasLabel(string $label): array
    {
        return ['term' => ['labels.keyword' => ['value' => $label, 'case_insensitive' => true]]];
    }

    /**
     * @param  list<string>  $options
     */
    private function hasOption(array $options, string $label): bool
    {
        return in_array(mb_strtolower($label), array_map('mb_strtolower', $options), true);
    }

    /**
     * @return list<array<string, array<string, string>>>
     */
    private function sortClauses(string $sort): array
    {
        $oldest = ['created_at' => ['order' => 'asc']];

        return match ($sort) {
            self::SORT_NEWEST => [['created_at' => ['order' => 'desc']]],
            self::SORT_COMMENTS => [['comments_count' => ['order' => 'desc']], $oldest],
            default => [$oldest],
        };
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array{number: int, title: string, url: string, areas: list<string>, components: list<string>,
     *     created_at: string, comments_count: int}
     */
    private function toRow(array $source): array
    {
        $labels = $source['labels'] ?? [];
        $named = static fn (string $prefix): array => array_values(array_map(
            static fn (string $label): string => substr($label, strlen($prefix)),
            array_filter($labels, static fn (string $label): bool => str_starts_with($label, $prefix)),
        ));

        return [
            'number' => (int) $source['id'],
            'title' => $source['title'],
            'url' => $source['url'],
            'areas' => $named(CommunityPickCandidatesQuery::AREA_PREFIX),
            'components' => $named(CommunityPickCandidatesQuery::COMPONENT_PREFIX),
            'created_at' => $source['created_at'],
            'comments_count' => (int) ($source['comments_count'] ?? 0),
        ];
    }

    private function issueIndex(): string
    {
        return OpenSearchService::getIndexWithPrefix(OpenSearchService::OPENSEARCH_GITHUB_ISSUES_INDEX);
    }
}
