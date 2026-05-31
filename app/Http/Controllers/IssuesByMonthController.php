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

class IssuesByMonthController extends Controller
{
    public function index(OpenItemsByMonthQuery $query): View
    {
        try {
            $issues = $query->execute(OpenSearchService::OPENSEARCH_GITHUB_ISSUES_INDEX);
        } catch (\Exception $e) {
            abort(500, 'Error fetching Issue data: '.$e->getMessage());
        }

        return view('issuesByMonth/index', [
            'infoText' => $this->getInfoText(),
            'issues' => $issues,
        ]);
    }

    private function getInfoText(): InfoText
    {
        return new InfoText(
            title: 'Why Group Open Issues by Month?',
            paragraphs: [
                'A long list of open issues can be daunting and discouraging. To make things more manageable, we group issues by the month they were last updated. This breaks the backlog into smaller, more approachable segments.',
                'Developers can focus on a specific month—like issues from March—and make visible progress. Each update or resolution shortens the list, providing a clear sense of momentum and accomplishment.',
                'This view also makes it easier to spot older issues that may have fallen through the cracks, giving the community an opportunity to reassess and take action where needed.',
            ]
        );
    }
}
