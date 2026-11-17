<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A business-rule failure with a stable API error code (the same code table the API docs publish).
 */
final class GatewayException extends RuntimeException
{
    /**
     * @param  array<string, mixed>|null  $data
     */
    public function __construct(
        public readonly int $apiCode,
        string $description,
        public readonly int $httpStatus = 400,
        public readonly ?array $data = null,
    ) {
        parent::__construct($description);
    }

    public static function invalid(string $field, string $message): self
    {
        return new self(4000, 'Invalid information', 400, ['error' => [['field' => $field, 'message' => $message]]]);
    }

    public static function notFound(): self
    {
        return new self(4003, 'object not found', 404);
    }

    public static function alreadyExists(string $description = 'Object already exists'): self
    {
        return new self(4004, $description, 400);
    }

    public static function expired(string $description = 'Date expired'): self
    {
        return new self(4006, $description, 400);
    }

    public static function cannotUpdate(string $description = 'Unable to update object'): self
    {
        return new self(4101, $description, 400);
    }

    public static function paymentRejected(string $description = 'Payment method rejected'): self
    {
        return new self(4200, $description, 402);
    }

    public static function refundNotAllowed(string $description = 'Refund is not allowed'): self
    {
        return new self(4300, $description, 400);
    }

    public static function alreadyRefunded(): self
    {
        return new self(4301, 'Payment already refunded', 400);
    }

    public static function processorTimeout(): self
    {
        return new self(4200, 'The card processor did not answer. We are checking the result with the bank; do not pay again yet.', 503);
    }

    public static function paymentInProgress(): self
    {
        return new self(4101, 'A payment is already being processed. Wait a moment, then check its result.', 409);
    }

    public static function processorUnavailable(): self
    {
        return new self(4200, 'Live card processing is not available yet. Use your test keys.', 402);
    }
}
