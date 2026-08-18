<?php

declare(strict_types=1);

namespace MccMnc\Tests;

use MccMnc\Internal\HttpResponse;
use MccMnc\Internal\HttpTransport;

/**
 * In-memory HttpTransport for tests: returns queued responses (FIFO) and
 * records every request. Never touches the network.
 */
final class FakeTransport implements HttpTransport
{
    /** @var list<array{url: string, headers: array<string, string>, timeout: float}> */
    public array $requests = [];

    /** @var list<HttpResponse> */
    private array $queue = [];

    public function queue(HttpResponse $response): self
    {
        $this->queue[] = $response;

        return $this;
    }

    /**
     * Queue a JSON response with the given decoded payload.
     *
     * @param array<string, mixed>  $payload
     * @param array<string, string> $headers
     */
    public function queueJson(array $payload, int $status = 200, array $headers = []): self
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $headers = self::lowercaseKeys($headers);
        $headers['content-type'] = 'application/json';

        return $this->queue(new HttpResponse($status, $headers, $body));
    }

    /**
     * Queue a raw text/CSV body.
     *
     * @param array<string, string> $headers
     */
    public function queueText(string $body, int $status = 200, array $headers = []): self
    {
        $headers = self::lowercaseKeys($headers);
        $headers += ['content-type' => 'text/plain; charset=utf-8'];

        return $this->queue(new HttpResponse($status, $headers, $body));
    }

    public function request(string $url, array $headers, float $timeout): HttpResponse
    {
        $this->requests[] = ['url' => $url, 'headers' => $headers, 'timeout' => $timeout];

        $response = array_shift($this->queue);
        if ($response === null) {
            throw new \LogicException('FakeTransport queue is empty for ' . $url);
        }

        return $response;
    }

    /**
     * The single recorded request; fails if there was not exactly one.
     *
     * @return array{url: string, headers: array<string, string>, timeout: float}
     */
    public function onlyRequest(): array
    {
        if (\count($this->requests) !== 1) {
            throw new \LogicException(sprintf('Expected exactly 1 request, saw %d', \count($this->requests)));
        }

        return $this->requests[0];
    }

    /**
     * @param array<string, string> $headers
     *
     * @return array<string, string>
     */
    private static function lowercaseKeys(array $headers): array
    {
        $lowered = [];
        foreach ($headers as $name => $value) {
            $lowered[strtolower($name)] = $value;
        }

        return $lowered;
    }
}
