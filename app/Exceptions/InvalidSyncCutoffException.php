<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Thrown when a sync command's --since option is present but cannot be parsed.
 */
class InvalidSyncCutoffException extends Exception
{
}
