<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\GitHub\InteractionPointsProcessor;
use Illuminate\Console\Command;

class ProcessGitHubInteractions extends Command
{
    protected $signature = 'opensearch:process-interactions';

    protected $description = 'Assign points to GitHub interactions and store results in a new OpenSearch index.';

    /**
     * @throws \JsonException
     */
    public function handle(InteractionPointsProcessor $processor): void
    {
        $bar = null;

        $result = $processor->process(
            onStart: function (int $total) use (&$bar) {
                $bar = $this->output->createProgressBar($total);
                $bar->start();
            },
            onAdvance: function () use (&$bar) {
                $bar?->advance();
            },
        );

        if ($bar) {
            $bar->finish();
        }

        $this->info("\nFinished processing all GitHub interactions.");
        $this->info("Missing users: {$result['missingUsers']}");
        $this->info("Missing company affiliations: {$result['missingAffiliations']}");
    }
}
