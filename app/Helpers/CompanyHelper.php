<?php

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

