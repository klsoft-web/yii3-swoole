<?php

declare(strict_types=1);

namespace Klsoft\Yii3Swoole;

use Psr\Http\Message\ResponseInterface;
use Swoole\HTTP\Response;

final readonly class PsrResponseConverter implements PsrResponseConverterInterface
{
    private const CHUNK_SIZE = 128 * 1024;

    public function emit(Response $response, ResponseInterface $psrResponse): void
    {
        $response->status($psrResponse->getStatusCode());
        foreach ($psrResponse->getHeaders() as $name => $values) {
            $response->header($name, $values);
        }
        $body = $psrResponse->getBody();
        $body->rewind();
        if ($body->getSize() > self::CHUNK_SIZE) {
            while (!$body->eof()) {
                $response->write($body->read(self::CHUNK_SIZE));
            }
            $response->end();
        } else {
            $response->end($body->getContents());
        }
    }
}
