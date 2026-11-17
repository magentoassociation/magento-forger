<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Queries\Dashboard\CommunityPickCandidatesQuery;
use App\Queries\Dashboard\IssuePickerQuery;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IssuePickerController extends Controller
{
    public function index(Request $request, IssuePickerQuery $query): View
    {
        $filters = $request->validate([
            'rows' => ['nullable', 'integer', 'min:1', 'max:'.IssuePickerQuery::MAX_ROWS],
            // bail: starts_with throws on an array (?area[]=x) instead of failing.
            'area' => ['bail', 'nullable', 'string', 'max:255', 'starts_with:'.CommunityPickCandidatesQuery::AREA_PREFIX],
            'component' => ['bail', 'nullable', 'string', 'max:255', 'starts_with:'.CommunityPickCandidatesQuery::COMPONENT_PREFIX],
            'showAssigned' => ['nullable', 'in:1'],
            'showPr' => ['nullable', 'in:1'],
            'sort' => ['nullable', Rule::in(IssuePickerQuery::SORTS)],
        ]);
        $area = $filters['area'] ?? null;
        $showAssigned = isset($filters['showAssigned']);
        $showPr = isset($filters['showPr']);
        $sort = $filters['sort'] ?? IssuePickerQuery::SORT_OLDEST;
        $rows = (int) ($filters['rows'] ?? IssuePickerQuery::PER_PAGE);

        $dataMissing = false;

        try {
            $result = $query->execute($area, $filters['component'] ?? null, $showAssigned, $showPr, $rows, $sort);
        } catch (\Exception $e) {
            $this->abortUnlessMissingIndex($e, 'issue');
            $result = ['rows' => [], 'total' => 0, 'openTotal' => 0, 'areaOptions' => [], 'componentOptions' => [], 'component' => null];
            $dataMissing = true;
        }

        return view('issuePicker/index', [
            'issues' => $result['rows'],
            'total' => $result['total'],
            'openTotal' => $result['openTotal'],
            // Next depth, or null at the end of the list (or at OpenSearch's window).
            'moreRows' => $rows < min($result['total'], IssuePickerQuery::MAX_ROWS)
                ? min($rows + IssuePickerQuery::PER_PAGE, IssuePickerQuery::MAX_ROWS)
                : null,
            // Not `component`: Blade reserves $component for component rendering.
            'selectedArea' => $area,
            'selectedComponent' => $result['component'],
            'showAssigned' => $showAssigned,
            'showPr' => $showPr,
            'sort' => $sort,
            'areaOptions' => $result['areaOptions'],
            'componentOptions' => $result['componentOptions'],
            'dataMissing' => $dataMissing,
        ]);
    }
}
