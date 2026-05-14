<?php

declare(strict_types=1);

namespace Klsoft\Yii3Swoole;

use HttpSoft\Message\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use Swoole\HTTP\Request;

final readonly class PsrRequestConverter implements PsrRequestConverterInterface
{
    public function __construct(
        private StreamFactoryInterface       $streamFactory,
        private UploadedFileFactoryInterface $uploadedFileFactory
    ) {}

    public function from(Request $request): ServerRequestInterface
    {
        $serverRequest = new ServerRequest(
            serverParams: $request->server,
            cookieParams: $request->cookie ?? [],
            queryParams: $request->get ?? [],
            method: $request->server['request_method'],
            uri: $request->server['request_uri'],
            headers: $request->header
        );

        $_GET = $serverRequest->getQueryParams(); //Support the yiisoft/yii-dataview widgets

        // Add body
        $body = $request->getContent();
        if ($body !== '') {
            $serverRequest = $serverRequest->withBody(
                $this->streamFactory->createStream($body)
            );
        }

        // Parse body
        if ($request->server['request_method'] === 'POST') {
            $contentType = $serverRequest->getHeaderLine('content-type');
            if (preg_match('~^(?:application/x-www-form-urlencoded|multipart/form-data)(?:$| |;)~', $contentType) === 1) {
                $serverRequest = $serverRequest->withParsedBody($request->post);
            }
        }

        // Add uploaded files
        $files = [];
        if (isset($request->files)) {
            foreach ($request->files as $class => $info) {
                $files[$class] = [];
                $this->populateUploadedFileRecursive(
                    $files[$class],
                    $info['name'],
                    $info['tmp_name'],
                    $info['type'],
                    $info['size'],
                    $info['error'],
                );
            }
        }

        $serverRequest = $serverRequest->withUploadedFiles($files);

        return $serverRequest;
    }

    /**
     * Populates uploaded files array from $_FILE data structure recursively.
     *
     * @param array $files Uploaded files array to be populated.
     * @param mixed $names File names provided by PHP.
     * @param mixed $tempNames Temporary file names provided by PHP.
     * @param mixed $types File types provided by PHP.
     * @param mixed $sizes File sizes provided by PHP.
     * @param mixed $errors Uploading issues provided by PHP.
     *
     * @psalm-suppress MixedArgument, ReferenceConstraintViolation
     */
    private function populateUploadedFileRecursive(
        array &$files,
        mixed $names,
        mixed $tempNames,
        mixed $types,
        mixed $sizes,
        mixed $errors
    ): void {
        if (is_array($names)) {
            /** @var array|string $name */
            foreach ($names as $i => $name) {
                $files[$i] = [];
                /** @psalm-suppress MixedArrayAccess */
                $this->populateUploadedFileRecursive(
                    $files[$i],
                    $name,
                    $tempNames[$i],
                    $types[$i],
                    $sizes[$i],
                    $errors[$i],
                );
            }

            return;
        }

        try {
            $stream = $this->streamFactory->createStreamFromFile($tempNames);
        } catch (RuntimeException) {
            $stream = $this->streamFactory->createStream();
        }

        $files = $this->uploadedFileFactory->createUploadedFile(
            $stream,
            (int)$sizes,
            (int)$errors,
            $names,
            $types
        );
    }
}
