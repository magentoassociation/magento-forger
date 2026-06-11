<?php

/**
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Queries\Dashboard\OpenLabelsByIssueQuery;
use App\Queries\Dashboard\PrsWithoutComponentLabelQuery;
use App\Services\GitHub\GitHubService;
use App\Services\Label\LabelOrchestrator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LabelController extends Controller
{
    public function listAllLabels(OpenLabelsByIssueQuery $query): View
    {
        $dataMissing = false;

        try {
            $nestedLabels = $query->execute();
        } catch (\Exception $e) {
            if (! $this->isMissingIndex($e)) {
                abort(500, 'Error fetching label data: '.$e->getMessage());
            }
            $nestedLabels = [];
            $dataMissing = true;
        }

        return view('labels/allLabels', ['labels' => $nestedLabels, 'dataMissing' => $dataMissing]);
    }

    public function listPrWithoutComponentLabel(PrsWithoutComponentLabelQuery $query): View
    {
        $dataMissing = false;

        try {
            $prs = $query->execute();
        } catch (\Exception $e) {
            if (! $this->isMissingIndex($e)) {
                abort(500, 'Error fetching PR data: '.$e->getMessage());
            }
            $prs = [];
            $dataMissing = true;
        }

        return view('labels/prsWithoutComponentLabel', ['prs' => $prs, 'dataMissing' => $dataMissing]);
    }

    public function processLabels(): View
    {
        return view('labels/processLabels');
    }

    /**
     * Process an uploaded label spreadsheet and apply label creates and renames in GitHub.
     *
     * @param  Request  $request  The request containing the uploaded `label_sheet` spreadsheet.
     * @param  GitHubService  $github  Service used to create and rename labels in GitHub.
     * @param  LabelOrchestrator  $orchestrator  Handles spreadsheet parsing and GitHub orchestration.
     * @return RedirectResponse Redirects back with a success, warning, or error flash message.
     *
     * @throws \Illuminate\Validation\ValidationException When the uploaded file is missing or has an invalid mime type.
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception When the spreadsheet cannot be loaded.
     * @throws \RuntimeException When the configured GitHub repository value is missing or invalid.
     */
    public function uploadLabels(Request $request, GitHubService $github, LabelOrchestrator $orchestrator): RedirectResponse
    {
        $request->validate([
            'label_sheet' => 'required|mimes:xlsx,xls,ods,csv',
        ]);

        $results = $orchestrator->process(
            $request->file('label_sheet')->getRealPath(),
            $github
        );

        $hasErrors = count($results['errors']) > 0;
        $hasSkipped = count($results['skipped']) > 0;
        $hasSuccessfulChanges = $results['created'] > 0 || $results['renamed'] > 0;
        $flashKey = 'success';
        $header = 'Labels were processed successfully.';

        if ($hasErrors) {
            $flashKey = $hasSuccessfulChanges || $hasSkipped ? 'warning' : 'error';
            $header = $hasSuccessfulChanges || $hasSkipped
                ? 'Labels were processed with some errors.'
                : 'Label processing failed.';
        } elseif ($hasSkipped) {
            $flashKey = 'warning';
            $header = 'Labels were processed with skipped remaps.';
        }

        $flash = [
            'header' => $header,
            'created' => $results['created'],
            'renamed' => $results['renamed'],
            'skipped' => $results['skipped'],
            'errors' => $results['errors'],
        ];

        return redirect()->back()->with($flashKey, $flash);
    }
}
