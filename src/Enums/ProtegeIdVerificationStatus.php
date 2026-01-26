<?php

namespace ProtegeId\Enums;


final class ProtegeIdVerificationStatus
{
    public const PENDING = 'pending';
    public const AWAITING_CLIENT = 'awaiting_client';
    public const SUCCESS = 'success';
    public const FAILED = 'failed';
    public const SKIPPED = 'skipped';
    public const TIMEOUT = 'timeout';
    public const CANCELED = 'canceled';

    public static function tryFrom(string $value): ?string
    {
        return in_array($value, self::values(), true) ? $value : null;
    }

    public static function values(): array
    {
        return [
            self::PENDING,
            self::AWAITING_CLIENT,
            self::SUCCESS,
            self::FAILED,
            self::SKIPPED,
            self::TIMEOUT,
            self::CANCELED,
        ];
    }
}