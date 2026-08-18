<?php

declare(strict_types=1);

namespace MccMnc\Exception;

/** 404 — no network matched the lookup. */
final class NotFoundException extends ApiException
{
    public function __construct(string $message, ?string $errorCode = null)
    {
        parent::__construct($message, 404, $errorCode);
    }
}
