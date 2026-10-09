<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Http\Controllers;

use App\DataTransferObjects\Misc\InfoText;
use App\Queries\Dashboard\CommunityPickCandidatesQuery;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class CommunityPickedController extends Controller
{
    /**
     * OpenSearch refuses from + size beyond 10,000 hits (max_result_window).
     */
    private const MAX_PAGE = 10000 / CommunityPickCandidatesQuery::PER_PAGE;

    public function index(Request $request, CommunityPickCandidatesQuery $query): View
    {
        $page = (int) ($request->validate([
            'page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PAGE],
        ])['page'] ?? 1);

        $dataMissing = false;

        try {
            $result = $query->execute($page);
        } catch (\Exception $e) {
            if (! $this->isMissingIndex($e)) {
                abort(500, 'Error fetching PR data: '.$e->getMessage());
            }
            $result = ['rows' => [], 'total' => 0];
            $dataMissing = true;
        }

        return view('communityPicked/index', [
            'infoText' => $this->getInfoText(),
            'candidates' => new LengthAwarePaginator(
                $result['rows'],
                $result['total'],
                CommunityPickCandidatesQuery::PER_PAGE,
                $page,
                ['path' => $request->url(), 'query' => $request->query()],
            ),
            'dataMissing' => $dataMissing,
        ]);
    }

    private function getInfoText(): InfoText
    {
        return new InfoText(
            title: 'How community picking works',
            paragraphs: [
                'These open pull requests are waiting to be picked for merging. Vote for the ones you want by giving '.
                    'the PR description a 👍 on GitHub — click a PR\'s 👍 count to open it.',
                'The most-wanted PRs rise to the top. Once maintainers pick a PR, it leaves this list. '.
                    'Vote counts refresh as Forger syncs with GitHub.',
            ]
        );
    }
}
