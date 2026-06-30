<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Services\Label;

use App\Services\GitHub\GitHubLabelService;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class LabelOrchestrator
{
    public function __construct(private readonly GitHubLabelService $labels) {}

    /**
     * Parse a label spreadsheet and apply creates and renames via the GitHub API.
     *
     * @param  string  $filePath  Absolute path to the uploaded spreadsheet.
     * @return array{created: int, renamed: int, errors: list<string>, skipped: list<string>}
     *
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception When the spreadsheet cannot be loaded.
     * @throws RuntimeException When the configured GitHub repository value is missing or invalid.
     */
    public function process(string $filePath): array
    {
        [$owner, $repo] = $this->resolveRepo();

        $spreadsheet = IOFactory::load($filePath);

        $newLabels = [];
        $renames = [];
        $remaps = [];
        $sheetsFound = 0;

        foreach (['area', 'component'] as $tabName) {
            $sheet = $spreadsheet->getSheetByName($tabName);
            if (! $sheet) {
                continue;
            }

            $sheetsFound++;

            $highestRow = $sheet->getHighestRow();
            $data = $sheet->toArray(null, true, true, true);

            $dataEndRow = 1;
            for ($row = 2; $row <= $highestRow; $row++) {
                $label = trim($data[$row]['A'] ?? '');
                if (empty($label)) {
                    $nextRowsEmpty = true;
                    for ($i = $row + 1; $i <= min($row + 3, $highestRow); $i++) {
                        if (! empty(trim($data[$i]['A'] ?? ''))) {
                            $nextRowsEmpty = false;
                            break;
                        }
                    }

                    if ($nextRowsEmpty) {
                        $dataEndRow = $row - 1;
                        break;
                    }
                }
            }

            if ($dataEndRow < 2) {
                $dataEndRow = $highestRow;
            }

            for ($row = 2; $row <= $dataEndRow; $row++) {
                $label = trim($data[$row]['A'] ?? '');
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
            'skipped' => [],
        ];

        if ($sheetsFound === 0) {
            $results['errors'][] = 'No supported worksheets found. Expected an "area" or "component" tab.';

            return $results;
        }

        foreach ($newLabels as $label) {
            try {
                $created = $this->labels->createLabel($owner, $repo, $label);

                if ($created === 0) {
                    $serviceError = $this->labels->getLastOperationError();

                    if (($serviceError['status'] ?? null) === 'skipped') {
                        $message = "Skipped creating label '$label': "
                            .($serviceError['message'] ?? 'GitHub skipped the label creation.');
                        $results['skipped'][] = $message;
                        Log::info('GitHub label creation skipped.', [
                            'label' => $label,
                            'service_error' => $serviceError,
                        ]);
                    } else {
                        $message = "Failed to create label '$label': "
                            .($serviceError['message'] ?? 'GitHub returned 0.');
                        $results['errors'][] = $message;
                        Log::error('GitHub label creation returned 0.', [
                            'label' => $label,
                            'service_error' => $serviceError,
                        ]);
                    }

                    continue;
                }

                Log::info("GitHub label created: {$label}");
                $results['created'] += $created;
            } catch (\Exception $e) {
                $results['errors'][] = "Failed to create label '$label': ".$e->getMessage();
                Log::error("Error creating label $label: ".$e->getMessage());
            }
        }

        foreach ($renames as $oldName => $newName) {
            try {
                $renamed = $this->labels->renameLabel($owner, $repo, $oldName, $newName);

                if ($renamed === 0) {
                    $serviceError = $this->labels->getLastOperationError();
                    $results['errors'][] = "Failed to rename '$oldName' to '$newName': "
                        .($serviceError['message'] ?? 'GitHub returned 0.');
                    Log::error('GitHub label rename returned 0.', [
                        'old_name' => $oldName,
                        'new_name' => $newName,
                        'service_error' => $serviceError,
                    ]);

                    continue;
                }

                Log::info("Renaming GitHub label: $oldName to $newName");
                $results['renamed'] += $renamed;
            } catch (\Exception $e) {
                $results['errors'][] = "Failed to rename '$oldName' to '$newName': ".$e->getMessage();
                Log::error("Error renaming label $oldName to $newName: ".$e->getMessage());
            }
        }

        foreach ($remaps as $oldName => $newName) {
            $results['skipped'][] = "Skipped remapping label '$oldName' to '$newName': remap not implemented.";
            Log::warning('GitHub label remap skipped because remap handling is not implemented.', [
                'old_name' => $oldName,
                'new_name' => $newName,
                'reason' => 'remap not implemented',
            ]);
        }

        return $results;
    }

    /**
     * Resolve the GitHub owner and repository from configuration.
     *
     * @return array{string, string}
     *
     * @throws RuntimeException When the repo is not configured or has an invalid format.
     */
    public function resolveRepo(): array
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
