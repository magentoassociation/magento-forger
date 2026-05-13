<?php
/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Services\GitHub;

use Exception;
use Illuminate\Support\Facades\Log;

class GitHubLabelService
{
    private ?array $lastOperationError = null;

    public function __construct(private readonly GitHubConnection $connection) {}

    public function createLabel(string $owner, string $repo, string $label): int
    {
        $this->lastOperationError = null;

        if ($this->checkIfLabelExists($owner, $repo, $label)) {
            $this->lastOperationError = [
                'operation' => 'create',
                'status' => 'skipped',
                'message' => "Label '{$label}' already exists.",
            ];

            return 0;
        }

        try {
            $this->connection->rest()->request('POST', "repos/$owner/$repo/labels", [
                'json' => [
                    'name' => $label,
                ],
            ]);

            return 1;
        } catch (Exception $exception) {
            $this->lastOperationError = [
                'operation' => 'create',
                'status' => 'failed',
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ];
            Log::error('Failed to create label', ['exception' => $exception]);

            return 0;
        }
    }

    public function renameLabel(string $owner, string $repo, string $oldName, string $newName): int
    {
        $this->lastOperationError = null;

        try {
            $this->connection->rest()->request('PATCH', $this->buildLabelUrl($owner, $repo, $oldName), [
                'json' => [
                    'new_name' => $newName,
                ],
            ]);

            return 1;
        } catch (Exception $exception) {
            $this->lastOperationError = [
                'operation' => 'rename',
                'status' => 'failed',
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
                'old_name' => $oldName,
                'new_name' => $newName,
            ];
            Log::error('Failed to rename label', ['exception' => $exception]);

            return 0;
        }
    }

    public function getLastOperationError(): ?array
    {
        return $this->lastOperationError;
    }

    private function checkIfLabelExists(string $owner, string $repo, string $label): bool
    {
        try {
            $response = $this->connection->rest()->get($this->buildLabelUrl($owner, $repo, $label));
            $json = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

            return $json['name'] === $label;
        } catch (Exception $exception) {
            Log::error('Failed to check is label already exists', ['exception' => $exception]);

            return false;
        }
    }

    private function buildLabelUrl(string $owner, string $repo, string $label): string
    {
        return "repos/$owner/$repo/labels/".rawurlencode($label);
    }
}
