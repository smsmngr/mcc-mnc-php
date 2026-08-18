<?php

declare(strict_types=1);

namespace MccMnc\Exception;

/**
 * A network-level failure before any HTTP response was received: DNS failure,
 * connection refused, TLS problem, or the configured timeout elapsing.
 *
 * `getCode()` carries the cURL error number (e.g. 28 = CURLE_OPERATION_TIMEDOUT)
 * when the default transport is in use.
 */
final class TransportException extends \RuntimeException
{
}
