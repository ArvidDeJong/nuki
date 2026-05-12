<?php

declare(strict_types=1);

namespace Darvis\Nuki\Http;

use Darvis\Nuki\Contracts\Authenticator;
use Darvis\Nuki\Exceptions\ApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class HttpClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly Authenticator $authenticator,
        private readonly array $httpConfig = [],
    ) {}

    public function get(string $endpoint, array $query = [], string $accountKey = 'default'): Response
    {
        return $this->send('GET', $endpoint, ['query' => $query], $accountKey);
    }

    public function post(string $endpoint, array $body = [], string $accountKey = 'default'): Response
    {
        return $this->send('POST', $endpoint, ['json' => $body], $accountKey);
    }

    public function put(string $endpoint, array $body = [], string $accountKey = 'default'): Response
    {
        return $this->send('PUT', $endpoint, ['json' => $body], $accountKey);
    }

    public function delete(string $endpoint, array $query = [], string $accountKey = 'default'): Response
    {
        return $this->send('DELETE', $endpoint, ['query' => $query], $accountKey);
    }

    public function send(string $method, string $endpoint, array $options, string $accountKey): Response
    {
        $request = $this->pending();
        $request = $this->authenticator->apply($request, $accountKey);

        $url = $this->baseUrl.'/'.ltrim($endpoint, '/');

        $response = match (strtoupper($method)) {
            'GET' => $request->get($url, $options['query'] ?? []),
            'POST' => $request->post($url, $options['json'] ?? []),
            'PUT' => $request->put($url, $options['json'] ?? []),
            'DELETE' => $request->delete($url, $options['query'] ?? []),
            default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };

        if ($response->failed()) {
            throw ApiException::fromResponse($response, $method.' '.$endpoint);
        }

        return $response;
    }

    private function pending(): PendingRequest
    {
        $timeout = (int) ($this->httpConfig['timeout'] ?? 10);
        $retries = (int) ($this->httpConfig['retries'] ?? 3);
        $retrySleep = (int) ($this->httpConfig['retry_sleep'] ?? 200);

        return Http::acceptJson()
            ->asJson()
            ->timeout($timeout)
            ->retry($retries, $retrySleep, function ($exception) {
                if ($exception instanceof ConnectionException) {
                    return true;
                }

                if ($exception instanceof RequestException) {
                    $status = $exception->response->status();

                    return $status === 429 || $status >= 500;
                }

                return false;
            }, throw: false);
    }
}
