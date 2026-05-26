<?php
/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use OpenSearch\Client;
use OpenSearch\GuzzleClientFactory;

class OpenSearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Client::class, function () {
            $host = config('opensearch.host', 'localhost');
            $port = config('opensearch.port', 9200);
            $useTls = config('opensearch.tls', false);
            $verifyTls = config('opensearch.verify_tls', true);
            $username = config('opensearch.username');
            $password = config('opensearch.password');

            $scheme = $useTls ? 'https://' : 'http://';
            $baseUri = preg_replace('#^https?://#', '', $host);

            $options = [
                'base_uri' => $scheme . $baseUri . ':' . $port,
                'verify' => $verifyTls,
            ];

            if ($username !== null && $password !== null) {
                $options['auth'] = [$username, $password];
            }

            return (new GuzzleClientFactory())->create($options);
        });
    }

    public function boot(): void
    {
        // No actions needed here by default
    }
}
