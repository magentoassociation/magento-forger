<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Http\Controllers;

use App\DataTransferObjects\Misc\InfoText;
use App\Queries\Dashboard\OpenItemsByMonthQuery;
use App\Services\Search\OpenSearchService;
use Illuminate\View\View;

class PrsByMonthController extends Controller
{
    public function index(OpenItemsByMonthQuery $query): View
    {
        try {
            $prs = $query->execute(OpenSearchService::OPENSEARCH_GITHUB_PULL_REQUESTS_INDEX);
        } catch (\Exception $e) {
            abort(500, 'Error fetching PR data: '.$e->getMessage());
        }

        return view('prsByMonth/index', [
            'infoText' => $this->getInfoText(),
            'prs' => $prs,
        ]);
    }

    private function getInfoText(): InfoText
    {
        return new InfoText(
            title: 'Why Group Open Pull Requests by Month?',
            paragraphs: [
                'Instead of facing an overwhelming list of hundreds or even thousands of open pull requests, we group them by the month they were last updated. This makes the backlog more digestible and gives developers a clearer, more motivating way to engage with open PRs.',
                'By focusing on one chunk at a time—say, all PRs from last December—progress becomes visible. Every update or closure shrinks the list in real time, creating a satisfying sense of achievement.',
                'As an added bonus, this view also helps highlight older PRs that may have been forgotten, giving the community a chance to review, revive, or close them with intention.',
            ]
        );
    }
}
