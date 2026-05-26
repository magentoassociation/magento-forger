<?php
/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\DataTransferObjects\Search;

enum FilterType: string
{
    case TERM = 'term';
    case TERMS = 'terms';
    case RANGE = 'range';
}
