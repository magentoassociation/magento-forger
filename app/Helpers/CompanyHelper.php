<?php
/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Helpers;

class CompanyHelper
{
    /**
     * Get the CSS class for a company based on its name.
     *
     * @param string $companyName
     * @return string
     */
    public static function getCompanyRowClass(string $companyName): string
    {
        return $companyName === 'Adobe' ? 'bg-danger-subtle' : '';
    }
}

