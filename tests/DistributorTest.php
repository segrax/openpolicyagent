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

namespace Segrax\OpenPolicyAgent\Tests;

use DirectoryIterator;
use Equip\Dispatch\MiddlewareCollection;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Segrax\OpenPolicyAgent\Middleware\Distributor;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use splitbrain\PHPArchive\FileInfo;
use splitbrain\PHPArchive\Tar;

/**
 * Set of tests for the PSR-15 Distributor middleware
 */
class DistributorTest extends Base
{
    private const POLICY_PATH = __DIR__ . '/policies';
    private const FAIL_BODY = 'Fail';

    /**
     * @var Callable
     */
    private $defaultResponse;

    /**
     * @var array
     */
    private $defaultToken = ["sub" => "opa", "iat" => 1516239022];

    /**
     * Setup a fail response to test for
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->defaultResponse = function () {

            $response = (new ResponseFactory())->createResponse(404);
            $response->getBody()->write(self::FAIL_BODY);
            return $response;
        };
    }

    /**
     * Execute the Distributor middleware
     */
    protected function executeMiddleware(string $pPath, array $pToken = []): ResponseInterface
    {
        $collection = new MiddlewareCollection([
            new Distributor(
                [Distributor::OPT_POLICY_PATH => self::POLICY_PATH],
                new ResponseFactory(),
                new StreamFactory()
            )
        ]);
        $request = (new ServerRequestFactory())->createFromGlobals();
        $request = $request->withUri($this->getUri($pPath));
        $request = $request->withAttribute('token', !empty($pToken) ? $pToken : $this->defaultToken);
        return $collection->dispatch($request, $this->defaultResponse);
    }

    /**
     * Get a list of files which can be put in a bundle
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
     * Check the distributor returns all files in the policies folder
     */
    public function testGetBundle(): void
    {
        $response = $this->executeMiddleware('/opa/bundles/test');
        $data = gzdecode($response->getBody()->__toString());
        $this->assertNotFalse($data);

        // Create a tmp file, write out the tar content
        $tmpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid() . 'tar';
        file_put_contents($tmpFile, $data);

        // Now load the tar
        $tar = new Tar();
        $tar->open($tmpFile);
        // Get the files in the policy folder to compare against
        $files = $this->getBundleFiles(self::POLICY_PATH);
        $this->assertGreaterThan(0, count($files));

        $contents = $tar->contents();
        $this->assertGreaterThan(0, count($contents));

        // Ensure all files made it
        foreach ($contents as $file) {
            /** @var FileInfo $file */
            foreach ($files as $localKey => $fileLocal) {
                if ($file->getPath() === $fileLocal[1]) {
                    unset($files[$localKey]);
                    continue;
                }
            }
        }
        unlink($tmpFile);
        $this->assertCount(0, $files);
    }

    /**
     * Ensure a 404 occurs with other paths
     */
    public function testNonBundle(): void
    {
        $response = $this->executeMiddleware('/opa/otherpath');
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals(self::FAIL_BODY, $response->getBody()->__toString());
    }

    /**
     * Ensure an Exception occurs if the policy path is empty
     */
    public function testNoPolicyPath(): void
    {
        $this->expectException(Exception::class);
        new Distributor(
            [Distributor::OPT_POLICY_PATH => ''],
            new ResponseFactory(),
            new StreamFactory()
        );
    }

    /**
     * Ensure other JWT sub's get a 404 on the bundle url
     */
    public function testValidPathWrongSub(): void
    {
        $response = $this->executeMiddleware('/opa/bundles/test', ['sub' => 'me']);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals(self::FAIL_BODY, $response->getBody()->__toString());
    }

    /**
     * Ensure using a path with no policies causes an Exception
     */
    public function testEmptyPolicyPath(): void
    {
        $folder = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid();
        $collection = new MiddlewareCollection([
            new Distributor(
                [Distributor::OPT_POLICY_PATH => $folder],
                new ResponseFactory(),
                new StreamFactory()
            )]);

        $request = (new ServerRequestFactory())->createFromGlobals();
        $request = $request->withUri($this->getUri('/opa/bundles/test'));
        $request = $request->withAttribute('token', $this->defaultToken);

        $this->expectException(Exception::class);
        $collection->dispatch($request, $this->defaultResponse);
    }
}
