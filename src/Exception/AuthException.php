<?php

declare(strict_types=1);

namespace MccMnc\Exception;

/** 401 — missing or invalid API key. Get a free key at https://mcc-mnc.dev. */
final class AuthException extends ApiException
{
    public function __construct(string $message, ?string $errorCode = null)
    {
        parent::__construct($message, 401, $errorCode);
    }
}
