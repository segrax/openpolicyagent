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
use Segrax\OpenPolicyAgent\Client;
use Segrax\OpenPolicyAgent\Middleware\Authorization;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Response;

/**
 * Set of tests for the PSR-15 Authorization middleware
 */
class AuthorizationTest extends TestCase
{
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

            $response = (new ResponseFactory())->createResponse(200);
            $response->getBody()->write('Success');
            return $response;
        };
    }

    /**
     * Execute the middleware
     */
    protected function executeMiddleware(string $pName, array $pOptions = []): ResponseInterface
    {
        if (!isset($pOptions[Authorization::OPT_POLICY]))
            $pOptions[Authorization::OPT_POLICY] = $pName;

        $collection = new MiddlewareCollection([
            new Authorization($pOptions, $this->client, new ResponseFactory())
        ]);
        $request = (new ServerRequestFactory())->createFromGlobals();
        return $collection->dispatch($request, $this->defaultResponse);
    }

    public function testNoPolicy(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->executeMiddleware('');
    }
    /**
     * @dataProvider policyResultAllowProvider
     */
    public function testAllow(Response $pResponse): void
    {
        $this->httpclient->method('sendRequest')->willReturn(
            $pResponse
        );
        $response = $this->executeMiddleware('unittest/api');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Success', $response->getBody()->__toString());
    }

    /**
     * @dataProvider policyResultDenyProvider
     */
    public function testDeny(Response $pResponse): void
    {
        $this->httpclient->method('sendRequest')->willReturn(
            $pResponse
        );
        $response = $this->executeMiddleware('unittest/api');
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('', $response->getBody()->__toString());
    }

    public function testPolicyMissing(): void
    {
        $this->httpclient->method('sendRequest')->willReturn(
            new Response(200, null, (new StreamFactory)->createStream(json_encode([])))
        );

        $this->expectException(Exception::class);
        $response = $this->executeMiddleware('unittest/api');
    }

    public function testPolicyMissingCallbackSuccess(): void
    {
        $this->httpclient->method('sendRequest')->willReturn(
            new Response(200, null, (new StreamFactory)->createStream(json_encode([])))
        );

        $response = $this->executeMiddleware('unittest/api', [
            Authorization::OPT_INPUT_CALLBACK => function() {
                return ['some' => 'thing'];
            },
            Authorization::OPT_POLICY_MISSING_CALLBACK => function (array $pInputs) {
                $this->assertArrayHasKey('some', $pInputs);
                $this->assertSame('thing', $pInputs['some']);
                return true;
            }
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Success', $response->getBody()->__toString());
    }


    public function testPolicyMissingCallbackFail(): void
    {
        $this->httpclient->method('sendRequest')->willReturn(
            new Response(200, null, (new StreamFactory)->createStream(json_encode([])))
        );

        $response = $this->executeMiddleware('unittest/api', [
            Authorization::OPT_POLICY_MISSING_CALLBACK => function (array $pInputs) {
                return false;
            }
        ]);

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function policyResultAllowProvider(): array
    {
        return [
            [
                new Response(200, null, (new StreamFactory)->createStream(json_encode(
                    [
                        'result' => ['allow' => true]
                    ]
                )))
            ]
        ];
    }

    public function policyResultDenyProvider(): array
    {
        return [
            [
                new Response(200, null, (new StreamFactory)->createStream(json_encode(
                    [
                        'result' => ['allow' => false]
                    ]
                )))
            ]
        ];
    }
}
