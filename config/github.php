<?php
/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

return [
    'repo' => env('GITHUB_REPO', 'magento/magento2'),
    'token' => env('GITHUB_TOKEN'),
];
