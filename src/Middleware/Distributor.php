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

use Closure;
use DirectoryIterator;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\StreamFactoryInterface;
use splitbrain\PHPArchive\Archive;
use splitbrain\PHPArchive\Tar;

/**
 * Class for serving policies up to a running OPA Agent
 */
class Distributor implements MiddlewareInterface
{
    public const OPT_ATTRIBUTE_TOKEN = 'attrToken';
    public const OPT_AGENT_USER      = 'agentusername';
    public const OPT_TOKEN_KEY       = 'tokenfield';

    private ?LoggerInterface $logger = null;
    private ResponseFactoryInterface $responseFactory;
    private StreamFactoryInterface $streamFactory;
    private ?Closure $dataCallable = null;
    private string $policyPath;
    private string $bundleRoute;

    /**
     * @var array<string, string>
     */
    private $options = [
        self::OPT_ATTRIBUTE_TOKEN   => 'token',
        self::OPT_AGENT_USER        => 'opa',
        self::OPT_TOKEN_KEY         => 'sub'
    ];

    /**
     * Class Setup
     *
     * @param array<string, string> $pOptions
     */
    public function __construct(
        string $pBundleRoute,
        string $pPolicyPath,
        array $pOptions,
        ResponseFactoryInterface $pResponseFactory,
        StreamFactoryInterface $pStreamFactory,
        ?LoggerInterface $pLogger = null
    ) {

        $this->logger = $pLogger;
        $this->responseFactory = $pResponseFactory;
        $this->streamFactory = $pStreamFactory;
        $this->bundleRoute = $pBundleRoute;
        $this->policyPath = $pPolicyPath;
        if(!file_exists($this->policyPath)) {
            throw new InvalidArgumentException('opa-distributor: Policy path is invalid');
        }

        $this->options = array_replace_recursive($this->options, $pOptions);
    }

    /**
     * Set a function to call to collect data to include
     *  Closure should return an array<filename, datacontent>
     */
    public function setDataCallable(Closure $pDataCallable) {
        $this->dataCallable = $pDataCallable;
    }

    /**
     * Check if OPA is attemting to fetch its bundle, then pack and serve it.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $attribute = $request->getAttribute($this->options[self::OPT_ATTRIBUTE_TOKEN]);
        $path = $request->getUri()->getPath();
        $pos = strpos($path, $this->bundleRoute);

        if (is_null($attribute) || ($pos === false || $pos > 0)) {
            return $handler->handle($request);
        }

        // If the subject is the OPA agent user, we provide the bundle
        if ($attribute['sub'] === $this->options[self::OPT_AGENT_USER]) {
            $bundleFile = $this->getBundle($request);
            $stream = $this->streamFactory->createStream($bundleFile);
            $response = $this->responseFactory->createResponse(200)
                ->withHeader('Content-Type', 'application/gzip')
                ->withBody($stream);
            return $response;
        }

        return $handler->handle($request);
    }

    /**
     * Collect up the contents of a bundle and gzip it
     */
    private function getBundle(ServerRequestInterface $request): string
    {
        $bundle = new Tar();
        $bundle->create();

        foreach ($this->getBundleFiles($this->policyPath) as $file) {
            $bundle->addFile($file[0], $file[1]);
        }

        // Callback and collect data to bundle
        if (is_callable($this->dataCallable)) {
            $files = call_user_func($this->dataCallable, $request);
            foreach ($files as $file => $content) {
                $bundle->addData($file, $content);
            }
        }

        $bundle->setCompression(9, Archive::COMPRESS_GZIP);

        return $bundle->getArchive();
    }

    /**
     * Find all files to put in the bundle
     *
     * @return array<mixed>
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
}
