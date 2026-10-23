<?php
/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CommunityPickedResult;
use App\Queries\Dashboard\CommunityPickCandidatesQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CommunityPickedController extends Controller
{
    public function index(Request $request, CommunityPickCandidatesQuery $query): View
    {
        // 404, not 403: the page isn't advertised to anyone who can't see it.
        abort_unless(Gate::allows('view-community-picked'), 404);

        $filters = $request->validate([
            'rows' => ['nullable', 'integer', 'min:1', 'max:'.CommunityPickCandidatesQuery::MAX_ROWS],
            // bail: starts_with throws on an array (?area[]=x) instead of failing.
            'area' => ['bail', 'nullable', 'string', 'max:255', 'starts_with:'.CommunityPickCandidatesQuery::AREA_PREFIX],
            'component' => ['bail', 'nullable', 'string', 'max:255', 'starts_with:'.CommunityPickCandidatesQuery::COMPONENT_PREFIX],
            // GitHub logins: alphanumerics and hyphens; apps end in [bot].
            'author' => ['bail', 'nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9-]+(\[bot\])?$/'],
            'sort' => ['nullable', Rule::in(CommunityPickCandidatesQuery::SORTS)],
            'title' => ['bail', 'nullable', 'string', 'max:200'],
        ]);
        $sort = $filters['sort'] ?? CommunityPickCandidatesQuery::SORT_VOTES;
        $rows = (int) ($filters['rows'] ?? CommunityPickCandidatesQuery::PER_PAGE);
        $area = $filters['area'] ?? null;
        $component = $filters['component'] ?? null;
        $author = $filters['author'] ?? null;
        $title = $filters['title'] ?? null;

        $dataMissing = false;

        try {
            $result = $query->execute($area, $component, $author, $rows, $sort, $title);
        } catch (\Exception $e) {
            $this->abortUnlessMissingIndex($e, 'PR');
            $result = ['rows' => [], 'total' => 0, 'areaOptions' => [], 'componentOptions' => [], 'authorOptions' => []];
            $dataMissing = true;
        }

        return view('communityPicked/index', [
            'candidates' => $result['rows'],
            'total' => $result['total'],
            // Next depth, or null at the end of the list (or at OpenSearch's window).
            'moreRows' => $rows < min($result['total'], CommunityPickCandidatesQuery::MAX_ROWS)
                ? min($rows + CommunityPickCandidatesQuery::PER_PAGE, CommunityPickCandidatesQuery::MAX_ROWS)
                : null,
            // Not `component`: Blade reserves $component for component rendering.
            'selectedArea' => $area,
            'selectedComponent' => $component,
            'selectedAuthor' => $author,
            'selectedTitle' => $title,
            'sort' => $sort,
            'areaOptions' => $result['areaOptions'],
            'componentOptions' => $result['componentOptions'],
            'authorOptions' => $result['authorOptions'],
            'dataMissing' => $dataMissing,
            'monthlyResults' => CommunityPickedResult::query()->where('enabled', true)->orderByDesc('month')->get(),
        ]);
    }
}
