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
 * Issue picker: open issues narrowed by Area, Component, Progress and Issue label, with
 * taken issues (assigned, or with an open or merged PR that closes them) left out unless
 * asked for.
 *
 * All four label filters depend on each other: each option list comes from open issues
 * the Include chips leave in that match every other label selection, so no option leads
 * to an empty list. When selections conflict (a chip went off, or a shared link), the
 * lowest-priority one is cleared: Issue, then Progress, Component, Area. Lists are sorted
 * by name, ignoring case. Progress and Issue labels are the ones whose names start with
 * "progress" or "issue", ignoring case.
 *
 * "Has a PR" reads two sources. Open PRs: the Linked Issues of open PRs in the PR index,
 * which the reactions sync refreshes every 15 minutes. Merged PRs: the issue's
 * closed_by_merged_pr. A PR closed without merging is in neither, so its issue stays.
 * The issue's own has_closing_pr is deliberately not used: opening a closing PR does not
 * bump the issue's updatedAt (checked on GitHub), so the incremental issue sync misses new
 * PRs; closing one unmerged likely leaves it stale the other way too (not checked).
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

    /**
     * Label-name prefixes for the Progress and Issue filters, matched ignoring case.
     */
    public const PROGRESS_PREFIX = 'progress';

    public const ISSUE_PREFIX = 'issue';

    /**
     * Progress options follow the workflow, not the alphabet. Names after "Progress: ",
     * matched ignoring case; any other Progress label comes after these, by name.
     */
    private const PROGRESS_ORDER = ['ready for grooming', 'ready for dev', 'dev in progress', 'pr created', 'pr in progress', 'done'];

    public function __construct(private readonly Client $client) {}

    /**
     * @return array{
     *     rows: list<array{number: int, title: string, url: string, areas: list<string>, components: list<string>,
     *     status: list<string>, created_at: string, comments_count: int}>,
     *     total: int, openTotal: int, areaOptions: list<string>, componentOptions: list<string>,
     *     progressOptions: list<string>, issueOptions: list<string>,
     *     area: ?string, component: ?string, progress: ?string, issueStatus: ?string
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
        ?string $progress = null,
        ?string $issueStatus = null,
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

        // Label selections in priority order: when they conflict, the last one gives way.
        $selected = ['area' => $area, 'component' => $component, 'progress' => $progress, 'issueStatus' => $issueStatus];

        // A selection not in its list (a chip went off, or a shared link combines labels no
        // issue has) would give an empty list the dropdowns could not have produced. Each list
        // depends on the other selections, so drop the lowest-priority unlisted one and
        // recompute, until every selection is listed.
        while (true) {
            $facets = $this->facets($selected, $hidden);
            $unlisted = array_filter(
                $selected,
                fn (?string $label, string $filter): bool => $label !== null && ! $this->hasOption($facets['options'][$filter], $label),
                ARRAY_FILTER_USE_BOTH,
            );
            if ($unlisted === []) {
                break;
            }
            $selected[array_key_last($unlisted)] = null;
        }

        $query = ['bool' => ['filter' => [['term' => ['is_open' => true]]]]];
        foreach (array_filter($selected) as $label) {
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
            'areaOptions' => $facets['options']['area'],
            'componentOptions' => $facets['options']['component'],
            'progressOptions' => $facets['options']['progress'],
            'issueOptions' => $facets['options']['issueStatus'],
            'area' => $selected['area'],
            'component' => $selected['component'],
            'progress' => $selected['progress'],
            'issueStatus' => $selected['issueStatus'],
        ];
    }

    /**
     * Open-issue total and the label options, sorted by name, in one search. Each list comes
     * from issues the Include chips leave in that match every other label selection, so no
     * option leads to an empty list; the total counts every open issue.
     *
     * @param  array{area: ?string, component: ?string, progress: ?string, issueStatus: ?string}  $selected
     * @param  list<array<string, mixed>>  $hidden  Clauses matching the issues the chips leave out.
     * @return array{openTotal: int, options: array{area: list<string>, component: list<string>,
     *     progress: list<string>, issueStatus: list<string>}}
     */
    private function facets(array $selected, array $hidden): array
    {
        $patterns = [
            'area' => CommunityPickCandidatesQuery::AREA_PREFIX.'.*',
            'component' => CommunityPickCandidatesQuery::COMPONENT_PREFIX.'.*',
            'progress' => $this->prefixPattern(self::PROGRESS_PREFIX),
            'issueStatus' => $this->prefixPattern(self::ISSUE_PREFIX),
        ];
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
                        // Each list is narrowed by every selection but its own.
                        'aggs' => array_map(
                            fn (string $filter): array => [
                                'filter' => $this->hasLabels(array_values(array_diff_key($selected, [$filter => true]))),
                                'aggs' => ['labels' => $labels($patterns[$filter])],
                            ],
                            array_combine(array_keys($patterns), array_keys($patterns)),
                        ),
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

        $options = array_map(
            fn (string $filter): array => $keys($response['aggregations']['shown'][$filter]['labels']['buckets'] ?? []),
            array_combine(array_keys($patterns), array_keys($patterns)),
        );
        $options['progress'] = $this->inWorkflowOrder($options['progress']);

        return [
            'openTotal' => (int) ($response['hits']['total']['value'] ?? 0),
            'options' => $options,
        ];
    }

    /**
     * Sorts Progress labels by PROGRESS_ORDER; labels not in it keep their name order, after.
     *
     * @param  list<string>  $labels  Already sorted by name.
     * @return list<string>
     */
    private function inWorkflowOrder(array $labels): array
    {
        $rank = static function (string $label): int {
            $name = mb_strtolower(trim(str_contains($label, ':') ? substr($label, strpos($label, ':') + 1) : $label));
            $position = array_search($name, self::PROGRESS_ORDER, true);

            return $position === false ? PHP_INT_MAX : $position;
        };
        // usort is stable, so unranked labels stay in name order.
        usort($labels, static fn (string $a, string $b): int => $rank($a) <=> $rank($b));

        return $labels;
    }

    /**
     * Terms "include" regex for labels starting with $prefix, ignoring case. OpenSearch's
     * include regex has no case-insensitive flag, so each letter becomes a [Xx] class.
     */
    private function prefixPattern(string $prefix): string
    {
        $classes = array_map(
            static fn (string $char): string => '['.mb_strtoupper($char).mb_strtolower($char).']',
            mb_str_split($prefix),
        );

        return implode('', $classes).'.*';
    }

    /**
     * Filter clause matching issues that carry every given label; match_all when none is set.
     *
     * @param  list<?string>  $labels
     * @return array<string, mixed>
     */
    private function hasLabels(array $labels): array
    {
        $clauses = array_map($this->hasLabel(...), array_values(array_filter($labels)));

        return $clauses === [] ? ['match_all' => (object) []] : ['bool' => ['filter' => $clauses]];
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
     *     status: list<string>, created_at: string, comments_count: int}
     */
    private function toRow(array $source): array
    {
        $labels = $source['labels'] ?? [];
        $named = static fn (string $prefix): array => array_values(array_map(
            static fn (string $label): string => substr($label, strlen($prefix)),
            array_filter($labels, static fn (string $label): bool => str_starts_with($label, $prefix)),
        ));

        // Progress and Issue labels, kept whole ("Progress: ready for dev").
        $status = array_values(array_filter(
            $labels,
            static fn (string $label): bool => stripos($label, self::PROGRESS_PREFIX) === 0 || stripos($label, self::ISSUE_PREFIX) === 0,
        ));
        sort($status, SORT_STRING | SORT_FLAG_CASE);

        return [
            'number' => (int) $source['id'],
            'title' => $source['title'],
            'url' => $source['url'],
            'areas' => $named(CommunityPickCandidatesQuery::AREA_PREFIX),
            'components' => $named(CommunityPickCandidatesQuery::COMPONENT_PREFIX),
            'status' => $status,
            'created_at' => $source['created_at'],
            'comments_count' => (int) ($source['comments_count'] ?? 0),
        ];
    }

    private function issueIndex(): string
    {
        return OpenSearchService::getIndexWithPrefix(OpenSearchService::OPENSEARCH_GITHUB_ISSUES_INDEX);
    }
}
