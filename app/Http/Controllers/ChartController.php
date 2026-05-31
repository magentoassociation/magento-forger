<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Queries\Dashboard\AgeOverTimeQuery;
use App\Services\Search\OpenSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenSearch\Exception\NotFoundHttpException;

class ChartController extends Controller
{
    public function __construct(private readonly AgeOverTimeQuery $query) {}

    public function dispatch(Request $request, string $method): mixed
    {
        if (method_exists($this, $method) && is_callable([$this, $method])) {
            return $this->$method($request);
        }

        throw new NotFoundHttpException("Chart method '{$method}' not found.");
    }

    public function prAgeOverTime(Request $request): JsonResponse
    {
        $data = $this->query->execute(OpenSearchService::OPENSEARCH_GITHUB_PULL_REQUESTS_INDEX);

        return response()->json([
            'type' => 'bar',
            'data' => [
                'datasets' => [[
                    'label' => 'Avg PR Age (days)',
                    'data' => $data,
                    'backgroundColor' => 'rgba(54, 162, 235, 0.6)',
                ]],
            ],
            'options' => [
                'responsive' => true,
                'scales' => ['y' => ['beginAtZero' => true]],
            ],
        ]);
    }

    public function issueAgeOverTime(Request $request): JsonResponse
    {
        $data = $this->query->execute(OpenSearchService::OPENSEARCH_GITHUB_ISSUES_INDEX);

        return response()->json([
            'type' => 'bar',
            'data' => [
                'datasets' => [[
                    'label' => 'Avg Issue Age (days)',
                    'data' => $data,
                    'backgroundColor' => 'rgba(54, 162, 235, 0.6)',
                ]],
            ],
            'options' => [
                'responsive' => true,
                'scales' => ['y' => ['beginAtZero' => true]],
            ],
        ]);
    }
}
