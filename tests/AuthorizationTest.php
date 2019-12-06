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

use Closure;
use InvalidArgumentException;
use Equip\Dispatch\MiddlewareCollection;
use Psr\Http\Message\ResponseInterface;
use Segrax\OpenPolicyAgent\Middleware\Authorization;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * Set of tests for the PSR-15 Authorization middleware
 */
class AuthorizationTest extends Base
{
    /**
     * @var Closure
     */
    private $defaultResponse;

    /**
     * Set a default success response
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->defaultResponse = function () {

            $response = (new ResponseFactory())->createResponse(200);
            $response->getBody()->write('Success');
            return $response;
        };
    }

    /**
     * Execute the middleware
     */
    protected function executeMiddleware(string $pName, string $pPath): ResponseInterface
    {
        $collection = new MiddlewareCollection([
            new Authorization([Authorization::OPT_POLICY => $pName], $this->client, new ResponseFactory())
        ]);
        $request = (new ServerRequestFactory())->createFromGlobals();
        $request = $request->withUri($this->getUri($pPath));
        return $collection->dispatch($request, $this->defaultResponse);
    }

    /**
     * Ensure an allow response results in reaching the action
     */
    public function testAllow(): void
    {
        $this->setPolicyAllow('unittest/api');
        $response = $this->executeMiddleware('unittest/api', '/test');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Success', $response->getBody()->__toString());
    }

    /**
     * Ensure a deny response results in a 403 (Unauthorized)
     */
    public function testDeny(): void
    {
        $this->setPolicyDeny('unittest/api');
        $response = $this->executeMiddleware('unittest/api', '/test');
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('', $response->getBody()->__toString());
    }

    /**
     * Ensure an InvalidArgumentException occurs if no policy name is provided
     */
    public function testNoPolicyName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Authorization([Authorization::OPT_POLICY => ''], $this->client, new ResponseFactory());
    }
}
