<?php

declare(strict_types=1);

namespace Darvis\Nuki\Exceptions;

use Illuminate\Http\Client\Response;
use Throwable;

class ApiException extends NukiException
{
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly ?string $body = null,
        public readonly ?string $endpoint = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    public static function fromResponse(Response $response, string $endpoint): self
    {
        $body = $response->body();
        $status = $response->status();

        $message = sprintf(
            'NUKI API %s returned HTTP %d: %s',
            $endpoint,
            $status,
            mb_strimwidth($body, 0, 300, '...'),
        );

        return new self(
            message: $message,
            status: $status,
            body: $body,
            endpoint: $endpoint,
        );
    }
}
