<?php
/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

return [
    'host' => env('OPENSEARCH_HOST', 'localhost'),
    'port' => env('OPENSEARCH_PORT', 9200),
    'username' => env('OPENSEARCH_USERNAME'),
    'password' => env('OPENSEARCH_PASSWORD'),
    'tls' => env('OPENSEARCH_TLS', false),
    'verify_tls' => env('OPENSEARCH_VERIFY_TLS', true),
    'index_prefix' => env('OPENSEARCH_INDEX_PREFIX', ''),
];
