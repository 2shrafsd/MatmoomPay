<?php

declare(strict_types=1);

namespace App\Services\Payments\Exceptions;

use RuntimeException;

/**
 * The processor could not be reached. When $configured is false no driver exists for this mode at all;
 * otherwise the outcome of the call is unknown and must be reconciled later, never assumed.
 */
final class ProcessorUnavailable extends RuntimeException
{
    public function __construct(public readonly bool $configured = true, string $message = 'The card processor did not answer in time.')
    {
        parent::__construct($message);
    }

    public static function notConfigured(): self
    {
        return new self(false, 'Live card processing is not configured for this platform yet.');
    }
}
