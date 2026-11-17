<?php
declare(strict_types=1);

namespace Pena\Service;

use RuntimeException;

/**
 * A rule of the letter workflow was violated. The message is safe to show the user;
 * $errors carries per-field/per-block details for forms.
 */
final class PenaException extends RuntimeException
{
    public function __construct(string $message, public readonly array $errors = [])
    {
        parent::__construct($message);
    }
}
