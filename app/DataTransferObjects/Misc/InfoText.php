<?php
/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\DataTransferObjects\Misc;

class InfoText
{
    /**
     * @param string $title
     * @param string[] $paragraphs
     */
    public function __construct(
        public readonly string $title,
        public readonly array $paragraphs,
    ) {
    }
}
