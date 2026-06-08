<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Queries\Dashboard\LeaderboardByMonthQuery;
use App\Queries\Dashboard\LeaderboardByYearQuery;
use Illuminate\View\View;

class LeaderboardController extends Controller
{
    public function index(LeaderboardByYearQuery $query): View
    {
        $dataMissing = false;

        try {
            $data = $query->execute();
        } catch (\Exception $e) {
            if (! $this->isMissingIndex($e)) {
                throw $e;
            }
            $data = [];
            $dataMissing = true;
        }

        return view('leaderboard/leaderboard', ['data' => $data, 'dataMissing' => $dataMissing]);
    }

    public function showYear(LeaderboardByMonthQuery $query, int $year): View
    {
        $dataMissing = false;

        try {
            $data = $query->execute($year);
        } catch (\Exception $e) {
            if (! $this->isMissingIndex($e)) {
                throw $e;
            }
            $data = [];
            $dataMissing = true;
        }

        return view('leaderboard/monthly', ['data' => $data, 'year' => $year, 'dataMissing' => $dataMissing]);
    }
}
