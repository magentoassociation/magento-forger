<?php
/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\DataTransferObjects\Search;

class QueryConfig
{
    public function __construct(
        public array $filters = [],
        public array $aggregations = [],
        public array $fields = [],
        public int $size = 0,
        public ?array $sort = null,
    ) {
    }
}
