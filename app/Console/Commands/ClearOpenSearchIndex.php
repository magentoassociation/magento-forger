<?php
/**
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenSearch\Client;

class ClearOpenSearchIndex extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'opensearch:clear-index';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Interactively delete all documents from a selected OpenSearch index';

    /**
     * The OpenSearch client instance.
     *
     * @var \OpenSearch\Client
     */
    protected Client $client;

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();

        // Set up OpenSearch client with appropriate hosts
        $this->client = app(Client::class);
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Fetch index data
        try {
            $indices = $this->client->cat()->indices(['format' => 'json']);
        } catch (\Exception $e) {
            $this->error("Error fetching indices: " . $e->getMessage());
            return 1;
        }

        // Filter out system or excluded indices
        $availableIndices = collect($indices)
            ->pluck('index')
            ->filter(fn($name) => !str_starts_with($name, '.') && !str_starts_with($name, 'top_queries'))
            ->values();

        if ($availableIndices->isEmpty()) {
            $this->warn('No suitable indices found.');
            return 0;
        }

        // Let user pick one
        $selected = $this->choice(
            'Which index do you want to clear?',
            $availableIndices->toArray()
        );

        // Confirm deletion
        if (!$this->confirm("Really delete all documents from '{$selected}'?")) {
            $this->info('Operation canceled.');
            return 0;
        }

        // Perform delete-by-query
        try {
            $response = $this->client->deleteByQuery([
                'index' => $selected,
                'body' => [
                    'query' => [
                        'match_all' => (object)[]
                    ]
                ],
                'conflicts' => 'proceed',
                'refresh' => true,
            ]);

            $this->info("Successfully deleted {$response['deleted']} documents from '{$selected}'.");
        } catch (\Exception $e) {
            $this->error("Delete failed: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
