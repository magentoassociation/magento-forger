<?php
/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
namespace App\Http\Controllers;

use App\Services\GitHub\GitHubService;
use App\Services\Search\OpenSearchService;
use DateTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use OpenSearch\Client;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class LabelController extends Controller
{
    public function listAllLabels(Client $client): view
    {
        $params = [
            'index' => OpenSearchService::getIndexWithPrefix('github-issues'),
            'body' => [
                'size' => 0,
                'query' => [
                    'term' => [
                        'is_open' => true,
                    ],
                ],
                'aggs' => [
                    'by_label' => [
                        'terms' => [
                            'field' => 'labels.keyword',
                            'order' => [
                                '_key' => 'asc',  // sort alphabetically
                            ],
                            'size' => 1000,  // adjust based on number of unique labels
                        ],
                    ],
                ],
            ],
        ];

        try {
            $result = $client->search($params);
        } catch (\Exception $e) {
            abort(500, 'Error fetching label data: '.$e->getMessage());
        }
        $nestedLabels = [];
        $buckets = $result['aggregations']['by_label']['buckets'];

        foreach ($buckets as $bucket) {
            $label = $bucket['key'];
            $count = $bucket['doc_count'];

            // Split label into prefix and remainder
            $parts = explode(':', $label, 2);
            $prefix = count($parts) > 1 ? trim($parts[0]) : 'no_prefix';

            // Initialize the prefix group if it doesn't exist
            if (! isset($nestedLabels[$prefix])) {
                $nestedLabels[$prefix] = [];
            }

            // Append the label and count under the prefix
            $nestedLabels[$prefix][] = [
                'label' => $label,
                'count' => $count,
            ];
        }
        ksort($nestedLabels);

        return view('labels/allLabels', ['labels' => $nestedLabels]);
    }

    public function listPrWithoutComponentLabel(Client $client): view
    {
        $params = [
            'index' => OpenSearchService::getIndexWithPrefix('github-pull-requests'),
            'body' => [
                'size' => 0,
                'query' => [
                    'bool' => [
                        'must' => [
                            ['term' => ['is_open' => true]],
                        ],
                        'must_not' => [
                            [
                                'regexp' => [
                                    'labels.keyword' => 'Component:.*',
                                ],
                            ],
                        ],
                    ],
                ],
                'aggs' => [
                    'by_year' => [
                        'date_histogram' => [
                            'field' => 'created_at',
                            'calendar_interval' => 'year',
                            'format' => 'yyyy',
                            'order' => ['_key' => 'asc'],
                            'min_doc_count' => 1,
                        ],
                        'aggs' => [
                            'by_month' => [
                                'date_histogram' => [
                                    'field' => 'created_at',
                                    'calendar_interval' => 'month',
                                    'format' => 'MM',
                                    'order' => ['_key' => 'asc'],
                                    'min_doc_count' => 1,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        try {
            $result = $client->search($params);
        } catch (\Exception $e) {
            abort(500, 'Error fetching PR data: '.$e->getMessage());
        }
        $dataToDisplay = [];
        foreach ($result['aggregations']['by_year']['buckets'] as $yearBucket) {
            $dataToDisplay[$yearBucket['key_as_string']] = [
                'year' => $yearBucket['key_as_string'],
                'total' => $yearBucket['doc_count'],
                'months' => [
                    '01' => ['month_number' => '01', 'total' => 0, 'start' => null, 'end' => null],
                    '02' => ['month_number' => '02', 'total' => 0, 'start' => null, 'end' => null],
                    '03' => ['month_number' => '03', 'total' => 0, 'start' => null, 'end' => null],
                    '04' => ['month_number' => '04', 'total' => 0, 'start' => null, 'end' => null],
                    '05' => ['month_number' => '05', 'total' => 0, 'start' => null, 'end' => null],
                    '06' => ['month_number' => '06', 'total' => 0, 'start' => null, 'end' => null],
                    '07' => ['month_number' => '07', 'total' => 0, 'start' => null, 'end' => null],
                    '08' => ['month_number' => '08', 'total' => 0, 'start' => null, 'end' => null],
                    '09' => ['month_number' => '09', 'total' => 0, 'start' => null, 'end' => null],
                    '10' => ['month_number' => '10', 'total' => 0, 'start' => null, 'end' => null],
                    '11' => ['month_number' => '11', 'total' => 0, 'start' => null, 'end' => null],
                    '12' => ['month_number' => '12', 'total' => 0, 'start' => null, 'end' => null],
                ],
            ];
            foreach ($yearBucket['by_month']['buckets'] as $monthBucket) {
                $dataToDisplay[$yearBucket['key_as_string']]['months'][$monthBucket['key_as_string']]['total'] = $monthBucket['doc_count'];
                $monthDate = Datetime::createFromFormat('Y-m-d', $yearBucket['key_as_string'].'-'.$monthBucket['key_as_string'].'-01');
                $firstOfMonth = (clone $monthDate)->modify('first day of this month')->setTime(0, 0, 0);
                $lastOfMonth = (clone $monthDate)->modify('last day of this month')->setTime(23, 59, 59);
                $dataToDisplay[$yearBucket['key_as_string']]['months'][$monthBucket['key_as_string']]['start'] = $firstOfMonth->format('Y-m-d\TH:i:s\Z');
                $dataToDisplay[$yearBucket['key_as_string']]['months'][$monthBucket['key_as_string']]['end'] = $lastOfMonth->format('Y-m-d\TH:i:s\Z');
            }
        }

        return view('labels/prsWithoutComponentLabel', ['prs' => $dataToDisplay]);
    }

    public function processLabels(): view
    {
        return view('labels/processLabels');
    }

    /**
     * Process an uploaded label spreadsheet and apply label creates and renames in GitHub.
     *
     * The spreadsheet may contain `area` and `component` sheets. Rows without keep, rename,
     * or replace values are treated as new labels. Rows with a rename value are treated as
     * label renames. Remap rows are collected from the sheet but are not applied yet.
     *
     * @param  Request  $request  The request containing the uploaded `label_sheet` spreadsheet.
     * @param  GitHubService  $github  Service used to create and rename labels in GitHub.
     * @return RedirectResponse Redirects back with a success, warning, or error flash message.
     *
     * @throws \Illuminate\Validation\ValidationException When the uploaded file is missing or has an invalid mime type.
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception When the spreadsheet cannot be loaded.
     * @throws RuntimeException When the configured GitHub repository value is missing or invalid.
     */
    public function uploadLabels(Request $request, GitHubService $github): RedirectResponse
    {
        $request->validate([
            'label_sheet' => 'required|mimes:xlsx,xls,ods',
        ]);

        $file = $request->file('label_sheet');
        $spreadsheet = IOFactory::load($file->getRealPath());

        $newLabels = [];
        $renames = [];
        // todo: handle remaps separately
        $remaps = [];

        foreach (['area', 'component'] as $tabName) {
            $sheet = $spreadsheet->getSheetByName($tabName);
            if (! $sheet) {
                continue;
            }

            $highestRow = $sheet->getHighestRow();
            $data = $sheet->toArray(null, true, true, true);

            // Find the last meaningful row in column A so trailing blank rows are ignored.
            // todo: validate that there are no empty rows, the CSV should be clean
            $dataEndRow = 1;
            for ($row = 2; $row <= $highestRow; $row++) {
                $label = trim($data[$row]['A'] ?? '');
                if (empty($label)) {
                    // A single empty row does not end the dataset because there may be spacing
                    // between entries. Only stop when this row and the next few rows are blank.
                    $nextRowsEmpty = true;
                    for ($i = $row + 1; $i <= min($row + 3, $highestRow); $i++) {
                        if (! empty(trim($data[$i]['A'] ?? ''))) {
                            $nextRowsEmpty = false;
                            break;
                        }
                    }

                    // Once we hit a run of empty rows, treat the previous row as the end of the
                    // actual data and stop scanning the sheet.
                    if ($nextRowsEmpty) {
                        $dataEndRow = $row - 1;
                        break;
                    }
                }
            }

            if ($dataEndRow < 2) {
                $dataEndRow = $highestRow;
            }

            // todo: use labels for headers instead of letters?
            for ($row = 2; $row <= $dataEndRow; $row++) {
                $label = trim($data[$row]['A'] ?? '');
                // For Future use
                // $description = trim($data[$row]['C'] ?? '');
                $keep = strtolower(trim($data[$row]['D'] ?? ''));
                $rename = trim($data[$row]['E'] ?? '');
                $replaceWith = trim($data[$row]['F'] ?? '');

                if (empty($label)) {
                    continue;
                }

                if ($keep === 'no' && ! empty($replaceWith)) {
                    $remaps[$label] = $replaceWith;
                } elseif (empty($keep) && ! empty($rename)) {
                    $renames[$label] = $rename;
                } elseif (empty($keep) && empty($rename) && empty($replaceWith)) {
                    if ($label !== 'New Labels') {
                        $newLabels[] = $label;
                    }
                }
            }
        }

        $results = [
            'created' => 0,
            'renamed' => 0,
            'errors' => [],
        ];

        foreach ($newLabels as $label) {
            try {
                $created = $this->createGitHubLabel($github, $label);

                if ($created === 0) {
                    $serviceError = $github->getLastLabelOperationError();
                    $message = ($serviceError['status'] ?? null) === 'skipped'
                        ? "Skipped creating label '$label': ".($serviceError['message'] ?? 'GitHub skipped the label creation.')
                        : "Failed to create label '$label': ".($serviceError['message'] ?? 'GitHub returned 0.');

                    $results['errors'][] = $message;
                    Log::error('GitHub label creation returned 0.', [
                        'label' => $label,
                        'service_error' => $serviceError,
                    ]);

                    continue;
                }

                $results['created'] += $created;
            } catch (\Exception $e) {
                $results['errors'][] = "Failed to create label '$label': ".$e->getMessage();
                Log::error("Error creating label $label: ".$e->getMessage());
            }
        }

        foreach ($renames as $oldName => $newName) {
            try {
                $renamed = $this->renameGitHubLabel($github, $oldName, $newName);

                if ($renamed === 0) {
                    $serviceError = $github->getLastLabelOperationError();
                    $results['errors'][] = "Failed to rename '$oldName' to '$newName': "
                        .($serviceError['message'] ?? 'GitHub returned 0.');
                    Log::error('GitHub label rename returned 0.', [
                        'old_name' => $oldName,
                        'new_name' => $newName,
                        'service_error' => $serviceError,
                    ]);

                    continue;
                }

                $results['renamed'] += $renamed;
            } catch (\Exception $e) {
                $results['errors'][] = "Failed to rename '$oldName' to '$newName': ".$e->getMessage();
                Log::error("Error renaming label $oldName to $newName: ".$e->getMessage());
            }
        }

        $hasErrors = count($results['errors']) > 0;
        $hasSuccessfulChanges = $results['created'] > 0 || $results['renamed'] > 0;
        $flashKey = 'success';
        $header = 'Labels were processed successfully.';

        if ($hasErrors) {
            $flashKey = $hasSuccessfulChanges ? 'warning' : 'error';
            $header = $hasSuccessfulChanges
                ? 'Labels were processed with some errors.'
                : 'Label processing failed.';
        }

        $message =
            $header.'<br>'.
            'Created Labels: '.number_format($results['created']).'<br>'.
            'Renamed Labels: '.number_format($results['renamed']);

        if ($hasErrors) {
            $message .= '<br>Errors:<br>'.implode('<br>', $results['errors']);
        }

        return redirect()->back()->with($flashKey, $message);
    }

    protected function createGitHubLabel(GitHubService $github, string $label): int
    {
        [$owner, $repo] = $this->getRepo();
        $result = $github->createLabel($owner, $repo, $label);

        if ($result) {
            Log::info("GitHub label created: {$label}");
        }

        return $result;
    }

    protected function renameGitHubLabel(GitHubService $github, string $oldLabel, string $newLabel): int
    {
        [$owner, $repo] = $this->getRepo();
        $result = $github->renameLabel($owner, $repo, $oldLabel, $newLabel);

        if ($result) {
            Log::info("Renaming GitHub label: $oldLabel to $newLabel");
        }

        return $result;
    }

    protected function getRepo(): array
    {
        $repo = trim((string) config('github.repo'));

        if ($repo === '') {
            throw new RuntimeException('GitHub repository is not configured');
        }

        if (substr_count($repo, '/') !== 1) {
            throw new RuntimeException('Invalid GitHub repository format');
        }

        [$owner, $repository] = explode('/', $repo);
        if ($owner === '' || $repository === '') {
            throw new RuntimeException('Invalid GitHub repository format');
        }

        return [$owner, $repository];
    }
}
