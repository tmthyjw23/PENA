<?php
declare(strict_types=1);

namespace Pena\Service;

use RuntimeException;

/** The AI service could not give a usable answer. The message is safe to show the user. */
final class AiException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $latencyMs = null)
    {
        parent::__construct($message);
    }
}
