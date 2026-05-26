<?php
/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\DataTransferObjects\Search;

class Filter
{
    public function __construct(
        public string $field,
        public FilterType $type = FilterType::TERM,
        public mixed $value = null,
    ) {
    }
}
