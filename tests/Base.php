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

use donatj\MockWebServer\MockWebServer;
use donatj\MockWebServer\Response;
use PHPUnit\Framework\TestCase;
use Segrax\OpenPolicyAgent\Client;
use Slim\Psr7\Uri;

/**
 * Base test class, provides mock-webserver functionality
 */
class Base extends TestCase
{
    /** @var MockWebServer */
    protected static $server;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var array
     */
    protected $resultAllowTrue = ['result' => ['allow' => true]];

    /**
     * @var array
     */
    protected $resultAllowFalse = ['result' => ['allow' => false]];

    /**
     * Setup the mock server
     */
    public static function setUpBeforeClass(): void
    {
        self::$server = new MockWebServer();
        self::$server->start();
    }

    /**
     * Setup the Client for each test
     */
    public function setUp(): void
    {
        $this->client = new Client([Client::OPT_AGENT_URL => self::$server->getServerRoot()]);
    }

    /**
     * Set the policy to return allow==true
     */
    protected function setPolicyAllow(string $pName = 'auth/api'): void
    {
        self::$server->setResponseOfPath(
            $this->getBaseURL() . "/data/$pName",
            new Response(json_encode($this->resultAllowTrue))
        );
    }

    /**
     * Set the policy to return allow==false
     */
    protected function setPolicyDeny(string $pName = 'auth/api'): void
    {
        self::$server->setResponseOfPath(
            $this->getBaseURL() . "/data/$pName",
            new Response(json_encode($this->resultAllowFalse))
        );
    }

    /**
     * Create a URI
     */
    protected function getUri(string $pPath): Uri
    {
        return new Uri("HTTP", '127.0.0.1', null, $pPath);
    }

    /**
     * Get the base URL of the OPA agent
     */
    protected function getBaseURL()
    {
        return '/' . Client::OPA_API_VER;
    }
}
