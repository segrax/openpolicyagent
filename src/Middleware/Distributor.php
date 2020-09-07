<?php

/*
Copyright (c) 2019 Robert Crossfield

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
*/

/**
 * @see       https://github.com/segrax/OpenPolicyAgent
 * @license   https://www.opensource.org/licenses/mit-license.php
 */

declare(strict_types=1);

namespace Segrax\OpenPolicyAgent\Middleware;

use DirectoryIterator;
use Exception;
use Phar;
use PharData;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Class for serving policies up to a running OPA Agent
 */
class Distributor implements MiddlewareInterface
{
    public const OPT_ATTRIBUTE_TOKEN = 'attrToken';
    public const OPT_AGENT_USER      = 'agentusername';
    public const OPT_TOKEN_KEY       = 'tokenfield';
    public const OPT_POLICY_PATH     = 'policypath';
    public const OPT_BUNDLE_CALLBACK = 'bundlecallback';

    /**
     * @var ?LoggerInterface
     */
    private $logger = null;

    /**
     * @var ResponseFactoryInterface
     */
    private $responseFactory;

    /**
     * @var StreamFactoryInterface
     */
    private $streamFactory;

    /**
     * @var array
     */
    private $options = [
        self::OPT_ATTRIBUTE_TOKEN   => 'token',
        self::OPT_AGENT_USER        => 'opa',
        self::OPT_TOKEN_KEY         => 'sub',
        self::OPT_POLICY_PATH       => '',
        self::OPT_BUNDLE_CALLBACK   => null
    ];

    /**
     * Class Setup
     */
    public function __construct(
        array $pOptions,
        ResponseFactoryInterface $pResponseFactory,
        StreamFactoryInterface $pStreamFactory,
        LoggerInterface $pLogger = null
    ) {
        if (empty($pOptions[self::OPT_POLICY_PATH])) {
            $this->log(LogLevel::EMERGENCY, 'opa-distributor has no policies');
            throw new Exception('opa-distributor has no policies');
        }

        $this->logger = $pLogger;
        $this->responseFactory = $pResponseFactory;
        $this->streamFactory = $pStreamFactory;
        $this->options = array_replace_recursive($this->options, $pOptions) ?? [];
    }

    /**
     * Check if OPA is attemting to fetch its bundle, then pack and serve it.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $attribute = $request->getAttribute($this->options[self::OPT_ATTRIBUTE_TOKEN]);
        $path = $request->getUri()->getPath();
        $pos = strpos($path, '/opa/bundles');

        if (is_null($attribute) || ($pos === false || $pos > 0)) {
            return $handler->handle($request);
        }

        // If the subject is the OPA agent user, we provide the bundle
        if ($attribute['sub'] === $this->options[self::OPT_AGENT_USER]) {
            $bundleFile = $this->getBundle();
            $stream = $this->streamFactory->createStreamFromFile($bundleFile, 'rb');
            $response = $this->responseFactory->createResponse(200)
                ->withHeader('Content-Type', 'application/gzip')
                ->withBody($stream);
            unlink($bundleFile);
            return $response;
        }

        return $handler->handle($request);
    }

    /**
     * Collect up the contents of a bundle and gzip it
     */
    private function getBundle(): string
    {
        $filename = tempnam(sys_get_temp_dir(), 'bundle_') . '.tar';
        try {
            $bundle = new PharData($filename);
            foreach ($this->getBundleFiles($this->options[self::OPT_POLICY_PATH]) as $file) {
                $bundle->addFile($file[0], $file[1]);
            }

            // Callback and collect data to bundle
            if (is_callable($this->options[self::OPT_BUNDLE_CALLBACK])) {
                $files = call_user_func($this->options[self::OPT_BUNDLE_CALLBACK]);
                foreach ($files as $file => $content) {
                    $bundle->addFromString($file, $content);
                }
            }

            $bundle->compress(Phar::GZ);
        } catch (Exception $e) {
            $this->log(LogLevel::EMERGENCY, 'opa-distributor: Failed to build OPA bundle', [$e]);
            throw new Exception('opa-distributor: Failed to build OPA bundle');
        }

        return $filename . '.gz';
    }

    /**
     * Find all files to put in the bundle
     */
    private function getBundleFiles(string $pPath): array
    {
        $results = [];

        foreach (new DirectoryIterator($pPath) as $file) {
            if ($file->isDot()) {
                continue;
            }

            if ($file->isDir()) {
                $results = array_merge($results, $this->getBundleFiles($file->getPathname()));
                continue;
            }

            $filename = $file->getFilename();
            $path = basename($file->getPath());
            $results[] = [$file->getPathname(), "$path/$filename"];
        }

        return $results;
    }

    /**
     * Log if available
     */
    private function log(string $pLevel, string $pMessage, array $pContext = []): void
    {
        if (!is_null($this->logger)) {
            // @codeCoverageIgnoreStart
            $this->logger->log($pLevel, $pMessage, $pContext);
            // @codeCoverageIgnoreEnd
        }
    }
}
