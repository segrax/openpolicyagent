<?php

/*
Copyright (c) 2022 Robert Crossfield

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

use Exception;
use Closure;
use InvalidArgumentException;
use Equip\Dispatch\MiddlewareCollection;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Segrax\OpenPolicyAgent\Client;
use Segrax\OpenPolicyAgent\Middleware\Authorization;
use Segrax\OpenPolicyAgent\Middleware\Distributor;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Response;
use Slim\Psr7\Uri;

/**
 * Set of tests for the PSR-15 Distributor middleware
 */
class DistributorTest extends TestCase
{
    private const POLICY_PATH = __DIR__ . '/../policies';
    private const TOKEN = ['sub' => 'opa'];

    private Closure $defaultResponse;
    private ClientInterface $httpclient;
    private Client $client;

    /**
     * Set a default success response
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->httpclient = $this->createMock(ClientInterface::class);
        $this->client = new Client(null, $this->httpclient, new RequestFactory(), 'http', 'fake-token');

        $this->defaultResponse = function () {

            $response = (new ResponseFactory())->createResponse(404);
            $response->getBody()->write('Not Found');
            return $response;
        };
    }

    /**
     * Execute the middleware
     */
    protected function executeMiddleware(
        string $pPolicyPath = self::POLICY_PATH,
        string $pRoute = '/opa/bundles',
        array $pToken = self::TOKEN,
        ?Closure $pDataCb = null,
        array $pOptions = []
    ): ResponseInterface {

        $distributor = new Distributor(
            '/opa/bundles',
            $pPolicyPath,
            $pOptions,
            new ResponseFactory(),
            new StreamFactory(),
            null
        );
        if (!is_null($pDataCb)) {
            $distributor->setDataCallable($pDataCb);
        }

        $collection = new MiddlewareCollection([
            $distributor
        ]);
        $request = (new ServerRequestFactory())->createFromGlobals();
        $request = $request->withUri(new Uri("HTTP", '127.0.0.1', null, $pRoute));
        $request = $request->withAttribute('token', $pToken);
        return $collection->dispatch($request, $this->defaultResponse);
    }

    public function testNoPolicies(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->executeMiddleware('');
    }

    public function testBundle(): void
    {
        $response = $this->executeMiddleware();
        $this->assertSame(200, $response->getStatusCode());
        $data = gzdecode($response->getBody()->__toString());
        $this->assertNotFalse($data);
    }

    public function testBundleWithDataCallback(): void
    {
        $cbhit = false;

        $response = $this->executeMiddleware(
            self::POLICY_PATH,
            '/opa/bundles',
            self::TOKEN,
            function ($pRequest) use (&$cbhit) {
                $cbhit = true;
                return ['asd' => 'file contentsz'];
            }
        );

        $this->assertSame(200, $response->getStatusCode());
        $data = gzdecode($response->getBody()->__toString());
        $this->assertStringContainsString('file contentsz', $data);
        $this->assertNotFalse($data);
        $this->assertTrue($cbhit);
    }

    public function testOtherTokenDoesntTriggerDistributor(): void
    {
        $response = $this->executeMiddleware(self::POLICY_PATH, '/opa/bundles', ['sub' => 'other']);
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testOtherRouteDoesntTriggerDistributor(): void
    {
        $response = $this->executeMiddleware(self::POLICY_PATH, '/v1/path');
        $this->assertSame(404, $response->getStatusCode());
    }
}
