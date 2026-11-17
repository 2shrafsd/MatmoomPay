<?php

declare(strict_types=1);

namespace App\Http\Api;

use Illuminate\Http\JsonResponse;

/**
 * The response envelope used by every gateway API endpoint: {success, code, description, data}.
 */
final class ApiResponse
{
    public const RETRIEVED = 2000;

    public const CREATED = 2001;

    public const UPDATED = 2002;

    public const REMOVED = 2003;

    public const GENERATED = 2004;

    public static function success(int $code, mixed $data = null, int $status = 200): JsonResponse
    {
        $body = [
            'success' => true,
            'code' => $code,
            'description' => self::describe($code),
        ];

        if ($data !== null) {
            $body['data'] = $data;
        }

        return new JsonResponse($body, $status);
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    public static function error(int $code, string $description, int $status, ?array $data = null): JsonResponse
    {
        $body = [
            'success' => false,
            'code' => $code,
            'description' => $description,
        ];

        if ($data !== null) {
            $body['data'] = $data;
        }

        return new JsonResponse($body, $status);
    }

    private static function describe(int $code): string
    {
        return match ($code) {
            self::CREATED => 'object created successfully',
            self::UPDATED => 'object updated successfully',
            self::REMOVED => 'object removed successfully',
            self::GENERATED => 'object generated successfully',
            default => 'object retrieved successfully',
        };
    }
}
