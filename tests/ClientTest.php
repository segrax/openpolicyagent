<?php

/*
Copyright (c) 2019-2022 Robert Crossfield

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

use GuzzleHttp\Exception\ConnectException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Segrax\OpenPolicyAgent\Client;
use Segrax\OpenPolicyAgent\Exception\PolicyException;
use Segrax\OpenPolicyAgent\Exception\ServerException;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Response;

/**
 * Set of tests of the OPA Client class
 */
class ClientTest extends TestCase
{
    private ClientInterface $httpclient;
    private Client $client;

    /**
     * Setup the Client for each test
     */
    #[\Override]
    public function setUp(): void
    {
        $this->httpclient = $this->createMock(ClientInterface::class);
        $this->client = new Client(null, $this->httpclient, new RequestFactory(), 'http', 'fake-token');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('agentProvenanceResponse')]
    public function testAgentVersion(array $pAgentResponse): void
    {
        $this->httpclient->method('sendRequest')->willReturn(
            new Response(
                200,
                null,
                (new StreamFactory())->createStream(json_encode($pAgentResponse))
            )
        );

        $response = $this->client->getAgentVersion();
        $this->assertSame($pAgentResponse['provenance']['version'], $response->getVersion());
    }

    public function testDataUpdate(): void
    {
        $this->httpclient->method('sendRequest')->willReturn(
            new Response(204, null, (new StreamFactory())->createStream('{"data":"test"}'))
        );

        $this->assertSame(true, $this->client->dataUpdate('random', 'no content'));
    }

    public function testDataUpdateServerError(): void
    {
        $this->httpclient->method('sendRequest')->willReturn(
            new Response(500, null, (new StreamFactory())->createStream(json_encode(['message' => 'a', 'code' => 'invalid_parameter'])))
        );

        $this->expectException(ServerException::class);
        $this->assertSame(false, $this->client->dataUpdate('random', '{"data":"test"}'));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('dataUpdateFailProvider')]
    public function testDataUpdateFailThrows(Response $pDataResponse): void
    {
        $this->httpclient->method('sendRequest')->willReturn(
            $pDataResponse
        );

        $this->expectException(ServerException::class);
        $this->client->dataUpdate('random', 'no content');
    }

    public function testNetworkErrorThrows(): void
    {
        $this->httpclient->method('sendRequest')->will($this->throwException(
            new ConnectException("Connect Fail", $this->createMock(RequestInterface::class))
        ));

        $this->expectException(RuntimeException::class);
        $this->client->dataUpdate('random', 'no content');
    }

    public function testPolicyUpdate(): void
    {
        $this->httpclient->method('sendRequest')->willReturn(
            new Response(204, null, (new StreamFactory())->createStream(json_encode("data")))
        );

        $this->assertSame(true, $this->client->policyUpdate('random', 'package random.api', false));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('policyUpdateFailProvider')]
    public function testPolicyUpdateFail(Response $pDataResponse): void
    {
        $this->httpclient->method('sendRequest')->willReturn(
            $pDataResponse
        );

        $this->expectException(ServerException::class);
        $this->client->dataUpdate('random', 'no content');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('policyUpdateFailProvider')]
    public function testPolicyUpdateFailCatch(Response $pDataResponse): void
    {
        $this->httpclient->method('sendRequest')->willReturn(
            $pDataResponse
        );

        try {
            $this->client->dataUpdate('random', 'no content');
        } catch (ServerException $e) {
            $this->assertSame('invalid_parameter', $e->getOpaCode());
            $this->assertNotEmpty($e->getErrors());
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('policyResultAllowProvider')]
    public function testPolicyAllow(Response $pResponse): void
    {
        $this->httpclient->method('sendRequest')->willReturn(
            $pResponse
        );

        $response = $this->client->policy('test/api', ['path' => ['v1', 'status']], false, false, false, false);
        $results = $response->getResults();
        $this->assertSame(true, $results['allow']);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('policyResultDeniedProvider')]
    public function testPolicyDeny(Response $pResponse): void
    {
        $this->httpclient->method('sendRequest')->willReturn(
            $pResponse
        );

        $response = $this->client->policy('test/api', ['path' => ['v1', 'status']], false, false, false, false);
        $results = $response->getResults();
        $this->assertSame(false, $results['allow']);
    }

    public function testPolicyMissing(): void
    {
        $this->httpclient->method('sendRequest')->willReturn(
            new Response(200, null, (new StreamFactory())->createStream(json_encode([])))
        );

        $this->expectException(PolicyException::class);
        $this->client->policy('missing/api', ['path' => ['v1', 'status']], false, false, false, false);
    }

    public function testPolicyMissingCatch(): void
    {
        $this->httpclient->method('sendRequest')->willReturn(
            new Response(200, null, (new StreamFactory())->createStream(json_encode([])))
        );

        try {
            $this->client->policy('missing/api', ['path' => ['v1', 'status']], false, false, false, false);
        } catch (PolicyException $e) {
            $this->assertEmpty($e->getResponse()->getResults());
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('fullResultProvider')]
    public function testFullResponse(Response $pFullResponse): void
    {
        $this->httpclient->method('sendRequest')->willReturn(
            $pFullResponse
        );

        $response = $this->client->policy('test/api', ['path' => ['v1', 'status']], false, false, false, false);

        $this->assertSame(false, $response->getByName('allow'));
        $this->assertSame('', $response->getDecisionID());
        $this->assertNotEmpty($response->getMetrics());
        $this->assertEmpty($response->getExplain());

        $this->assertSame(false, $response->has('doesntexist'));
    }
    #[\PHPUnit\Framework\Attributes\DataProvider('queryResultProvider')]
    public function testQuery(Response $pResponse): void
    {
        $this->httpclient->method('sendRequest')->willReturn(
            $pResponse
        );
        $response = $this->client->query(
            'input.servers[i].ports[_] = "p2"; input.servers[i].name = name',
            ['servers' => [
                [
                    'ports' => ['p2'],
                    'name' => 'test'
                ]
            ]],
            false,
            false,
            false,
            false
        );
        $results = $response->getResults();
        $this->assertSame(0, $results['i']);
        $this->assertSame('test', $results['name']);
    }
    #[\PHPUnit\Framework\Attributes\DataProvider('queryResultFailProvider')]
    public function testQueryFails(Response $pResponse): void
    {
        $this->httpclient->method('sendRequest')->willReturn(
            $pResponse
        );

        $this->expectException(ServerException::class);
        $this->client->query(
            'input.servers[i]].ports[_] = "p2"; input.servers[i].name = name',
            ['servers' => [
                [
                    'ports' => ['p2'],
                    'name' => 'test'
                ]
            ]],
            false,
            false,
            false,
            false
        );
    }

    public static function agentProvenanceResponse(): array
    {
        return [
            [
                [
                    'provenance' => [
                        'version' => '0.42.0',
                        'build_commit' => '9b5fb9b',
                        'build_timestamp' => '2022-07-04T12:23:16Z',
                        'build_hostname' => 'd3afd1ae56c8',
                        'bundles' => [
                            "file" => ["revision" => '123456']
                        ]
                    ],
                    'result' => []
                ]
            ]
        ];
    }

    public static function dataUpdateFailProvider(): array
    {
        return [
            [
                new Response(400, null, (new StreamFactory())->createStream(json_encode(
                    [
                        'code' => 'invalid_parameter',
                        'message' => 'path test/api is owned by bundle "response.tar.gz"'
                    ]
                )))
            ]
        ];
    }

    public static function policyUpdateFailProvider(): array
    {
        return [
            [
                new Response(400, null, (new StreamFactory())->createStream(json_encode(
                    [
                        'code' => 'invalid_parameter',
                        'message' => 'error(s) occurred while compiling module(s)',
                        'errors' => [
                            [
                                "code" => "rego_parse_error",
                                "message" => "package expected",
                                "location" => [
                                    "file" => "test/api",
                                    "row" => 1,
                                    "col" => 1
                                ]
                            ]
                        ]
                    ]
                )))
            ]
        ];
    }

    public static function policyResultAllowProvider(): array
    {
        return [
            [
                new Response(200, null, (new StreamFactory())->createStream(json_encode(
                    [
                        'result' => ['allow' => true]
                    ]
                )))
            ]
        ];
    }

    public static function policyResultDeniedProvider(): array
    {
        return [
            [
                new Response(200, null, (new StreamFactory())->createStream(json_encode(
                    [
                        'result' => ['allow' => false]
                    ]
                )))
            ]
        ];
    }

    public static function queryResultProvider(): array
    {
        return [
            [
                new Response(200, null, (new StreamFactory())->createStream(json_encode(
                    [
                        'result' => ['i' => 0, 'name' => 'test']
                    ]
                )))
            ]
        ];
    }

    public static function queryResultFailProvider(): array
    {
        return [
            [
                new Response(400, null, (new StreamFactory())->createStream(json_encode(
                    [
                        'code' => 'invalid_parameter',
                        'message' => 'error(s) occurred while parsing query',
                        'errors' => [
                            0 => [
                                'code' => 'rego_parse_error',
                                'message' => 'unexpected ] token',
                                'location' => [
                                    'file' => '',
                                    'row' => 1,
                                    'col' => 17,
                                ],
                                'details' => [
                                    'line' => 'input.servers[i]].ports[_] = "p2"; input.servers[i].name = name',
                                    'idx' => 16,
                                ],
                            ],
                        ],
                    ]
                )))
            ]
        ];
    }

    public static function fullResultProvider(): array
    {
        return [
            [
                new Response(200, null, (new StreamFactory())->createStream(json_encode(
                    [
                        'provenance' => [
                            'version' => '0.42.0',
                            'build_commit' => '9b5fb9b',
                            'build_timestamp' => '2022-07-04T12:23:16Z',
                            'build_hostname' => 'd3afd1ae56c8',
                            'bundles' => [
                                'response.tar.gz' => [
                                    'revision' => '5a639d8d6ac83c51aad5e33dace09cfcb053b5830048e3a1e374d5e1a727d9f1',
                                ],
                            ],
                        ],
                        'metrics' => [
                            'counter_eval_op_base_cache_miss' => 1,
                            'counter_eval_op_virtual_cache_miss' => 1,
                            'counter_server_query_cache_hit' => 1,
                            'histogram_eval_op_plug' => [
                                '75%' => 0,
                                '90%' => 0,
                                '95%' => 0,
                                '99%' => 0,
                                '99.9%' => 0,
                                '99.99%' => 0,
                                'count' => 9,
                                'max' => 0,
                                'mean' => 0,
                                'median' => 0,
                                'min' => 0,
                                'stddev' => 0,
                            ],
                            'histogram_eval_op_resolve' => [
                                '75%' => 0,
                                '90%' => 0,
                                '95%' => 0,
                                '99%' => 0,
                                '99.9%' => 0,
                                '99.99%' => 0,
                                'count' => 2,
                                'max' => 0,
                                'mean' => 0,
                                'median' => 0,
                                'min' => 0,
                                'stddev' => 0,
                            ],
                            'histogram_eval_op_rule_index' => [
                                '75%' => 0,
                                '90%' => 0,
                                '95%' => 0,
                                '99%' => 0,
                                '99.9%' => 0,
                                '99.99%' => 0,
                                'count' => 1,
                                'max' => 0,
                                'mean' => 0,
                                'median' => 0,
                                'min' => 0,
                                'stddev' => 0,
                            ],
                            'timer_eval_op_plug_ns' => 0,
                            'timer_eval_op_resolve_ns' => 0,
                            'timer_eval_op_rule_index_ns' => 0,
                            'timer_rego_external_resolve_ns' => 0,
                            'timer_rego_input_parse_ns' => 0,
                            'timer_rego_query_eval_ns' => 506000,
                            'timer_server_handler_ns' => 506000,
                        ],
                        'result' => [
                            'allow' => false,
                        ],
                    ]
                )))
            ]
        ];
    }
}
