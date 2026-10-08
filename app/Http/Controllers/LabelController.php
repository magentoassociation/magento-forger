<?php

/**
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Queries\Dashboard\OpenLabelsByIssueQuery;
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
}
