<?php
declare(strict_types=1);

namespace Pena\Service;

/** @internal content broke a paragraph rule; carries what the retry needs. */
final class AiInvalid extends \RuntimeException
{
    public function __construct(public readonly array $problems, public readonly int $generationId,
                                public readonly string $rawText, public readonly ?array $parsed)
    {
        parent::__construct(implode(' ', $problems));
    }
}
