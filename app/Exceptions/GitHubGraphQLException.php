<?php
/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use JsonException;

class GitHubGraphQLException extends Exception
{
    protected array $context;

    public function __construct(string $message, array $context = [], int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * @throws JsonException
     */
    public function __toString(): string
    {
        return sprintf(
            "%s: %s\nContext: %s",
            static::class,
            $this->getMessage(),
            json_encode($this->context, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }
}
