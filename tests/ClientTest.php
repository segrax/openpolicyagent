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

use donatj\MockWebServer\Response;
use RuntimeException;
use Segrax\OpenPolicyAgent\Client;
use Segrax\OpenPolicyAgent\Exception\PolicyException;
use Segrax\OpenPolicyAgent\Exception\ServerException;
use Segrax\OpenPolicyAgent\Response as OpaResponse;
use UnexpectedValueException;

/**
 * Set of tests of the OPA Client class
 */
class ClientTest extends Base
{
    /**
     * @var array Version information
     */
    private $resultVersion = [
        'provenance' => [
            'version' => '0.15.1',
            'build_commit' => '62bb63d',
            'build_timestamp' => '2019-11-18T15:22:47Z',
            'build_hostname' => '1173a3a0b052'
        ]
    ];

    /**
     * @var string Stored as JSON due to size
     */
    //@codingStandardsIgnoreLine
    private $resultQueryJson = '{"explanation":[{"op":"enter","query_id":0,"parent_id":0,"type":"body","node":[{"index":0,"terms":[{"type":"ref","value":[{"type":"var","value":"eq"}]},{"type":"object","value":[[{"type":"string","value":"user"},{"type":"array","value":[{"type":"string","value":"alice"}]}]]},{"type":"var","value":"$term1"}]}],"locals":[]},{"op":"eval","query_id":0,"parent_id":0,"type":"expr","node":{"index":0,"terms":[{"type":"ref","value":[{"type":"var","value":"eq"}]},{"type":"object","value":[[{"type":"string","value":"user"},{"type":"array","value":[{"type":"string","value":"alice"}]}]]},{"type":"var","value":"$term1"}]},"locals":[]},{"op":"exit","query_id":0,"parent_id":0,"type":"body","node":[{"index":0,"terms":[{"type":"ref","value":[{"type":"var","value":"eq"}]},{"type":"object","value":[[{"type":"string","value":"user"},{"type":"array","value":[{"type":"string","value":"alice"}]}]]},{"type":"var","value":"$term1"}]}],"locals":[{"key":{"type":"var","value":"$term1"},"value":{"type":"object","value":[[{"type":"string","value":"user"},{"type":"array","value":[{"type":"string","value":"alice"}]}]]}}]},{"op":"redo","query_id":0,"parent_id":0,"type":"body","node":[{"index":0,"terms":[{"type":"ref","value":[{"type":"var","value":"eq"}]},{"type":"object","value":[[{"type":"string","value":"user"},{"type":"array","value":[{"type":"string","value":"alice"}]}]]},{"type":"var","value":"$term1"}]}],"locals":[{"key":{"type":"var","value":"$term1"},"value":{"type":"object","value":[[{"type":"string","value":"user"},{"type":"array","value":[{"type":"string","value":"alice"}]}]]}}]},{"op":"redo","query_id":0,"parent_id":0,"type":"expr","node":{"index":0,"terms":[{"type":"ref","value":[{"type":"var","value":"eq"}]},{"type":"object","value":[[{"type":"string","value":"user"},{"type":"array","value":[{"type":"string","value":"alice"}]}]]},{"type":"var","value":"$term1"}]},"locals":[{"key":{"type":"var","value":"$term1"},"value":{"type":"object","value":[[{"type":"string","value":"user"},{"type":"array","value":[{"type":"string","value":"alice"}]}]]}}]}],"metrics":{"histogram_eval_op_plug":{"75%":975,"90%":1000,"95%":1000,"99%":1000,"99.9%":1000,"99.99%":1000,"count":4,"max":1000,"mean":900,"median":900,"min":800,"stddev":70.71067811865476},"timer_eval_op_plug_ns":3600,"timer_query_compile_stage_check_safety_ns":14300,"timer_query_compile_stage_check_types_ns":9300,"timer_query_compile_stage_check_undefined_funcs_ns":2600,"timer_query_compile_stage_check_unsafe_builtins_ns":2400,"timer_query_compile_stage_resolve_refs_ns":3600,"timer_query_compile_stage_rewrite_comprehension_terms_ns":3800,"timer_query_compile_stage_rewrite_dynamic_terms_ns":3100,"timer_query_compile_stage_rewrite_expr_terms_ns":3700,"timer_query_compile_stage_rewrite_local_vars_ns":10600,"timer_query_compile_stage_rewrite_to_capture_value_ns":3500,"timer_query_compile_stage_rewrite_with_values_ns":2200,"timer_rego_input_parse_ns":1800,"timer_rego_load_bundles_ns":900,"timer_rego_load_files_ns":1200,"timer_rego_module_parse_ns":900,"timer_rego_query_compile_ns":77300,"timer_rego_query_eval_ns":26700,"timer_rego_query_parse_ns":900},"result":[{}]}';

    /**
     * @var array Error response
     */
    private $resultError = [
        'code' => 'invalid_parameter',
        'message' => 'error(s) occurred while compiling module(s)',
        'errors' => [
            [
                'code' => 'rego_unsafe_var_error',
                'message' => 'var x is unsafe',
                'location' => [
                    'file' => 'example',
                    'row' => 3,
                    'col' => 1
                ]
            ]
        ]
    ];

    /**
     * Test no URL provided to Client
     */
    public function testNoURLException(): void
    {
        $this->expectException(UnexpectedValueException::class);
        new Client([Client::OPT_AGENT_URL => '']);
    }

    /**
     * Test the Response on a policy allow
     */
    public function testPolicyResponse(): void
    {
        $this->setPolicyAllow('auth/api');
        $response = $this->client->policy('auth/api', [], false, false, false, false);
        $this->assertArrayHasKey('allow', $response->getResults());
        $this->assertTrue($response->has('allow'));
        $this->assertTrue($response->getByName('allow'));
    }

    /**
     * Check the agent version response
     */
    public function testGetAgentVersion(): void
    {
        self::$server->setResponseOfPath(
            $this->getBaseURL() . '/data/',
            new Response(json_encode($this->resultAllowTrue + $this->resultVersion))
        );
        $version = $this->client->getAgentVersion();
        $this->assertCount(4, $version);
        $this->assertEquals($this->resultVersion['provenance'], $version);
    }

    /**
     * Test the Response for all fields
     */
    public function testQueryResponse(): void
    {
        self::$server->setResponseOfPath(
            $this->getBaseURL() . '/query',
            new Response($this->resultQueryJson)
        );
        $response = $this->client->query('{"user": ["alice"]}', true, true, true, false);
        $expected = json_decode($this->resultQueryJson, true);
        $this->assertEquals($expected['explanation'], $response->getExplain());
        $this->assertEquals($expected['metrics'], $response->getMetrics());
        $this->assertEquals($expected['result'], $response->getResults());
        $this->assertEquals('', $response->getDecisionID());
    }

    /**
     * Test the Response in a policy fail
     */
    public function testPolicyFail(): void
    {
        self::$server->setResponseOfPath(
            $this->getBaseURL() . '/data/fail/asd',
            new Response(json_encode($this->resultError), [], 500)
        );
        $this->expectException(ServerException::class);
        $this->client->policy('fail/asd', [], false, false, false, false);
    }

    /**
     * Ensure a RuntimeException occurs with an invalid URL
     */
    public function testAgentFail(): void
    {
        $this->client = new Client([Client::OPT_AGENT_URL => 'http://inval']);
        $this->expectException(RuntimeException::class);
        $this->client->policy('failed', [], false, false, false, false);
    }

    /**
     * Ensure RuntimeException occurs on a 404 response
     */
    public function testFail(): void
    {
        self::$server->setResponseOfPath(
            $this->getBaseURL() . '/data/failed',
            new Response("", [], 404)
        );
        $this->expectException(RuntimeException::class);
        $this->client->policy('failed', [], false, false, false, false);
    }

    /**
     * Ensure a PolicyException occurs when there is no result set
     */
    public function testInvalidPolicy(): void
    {
        self::$server->setResponseOfPath(
            $this->getBaseURL() . '/data/failed',
            new Response("", [], 200)
        );
        $this->expectException(PolicyException::class);
        $this->client->policy('failed', [], false, false, false, false);
    }

    /**
     * Ensure a successful policy update returns an empty response
     */
    public function testPolicyUpdate(): void
    {
        self::$server->setResponseOfPath(
            $this->getBaseURL() . '/policies/some/policy',
            new Response(json_encode([]), [], 200)
        );
        $result = $this->client->policyUpdate('some/policy', 'some content', false);
        $this->assertEmpty($result);
    }

    /**
     * Ensure a bad policy update throws a RuntimeException
     */
    public function testPolicyUpdateFail(): void
    {
        self::$server->setResponseOfPath(
            $this->getBaseURL() . '/policies/some/policy',
            new Response(json_encode([$this->resultError]), [], 400)
        );
        $this->expectException(RuntimeException::class);
        $this->client->policyUpdate('some/policy', 'some content', false);
    }

    /**
     * Ensure a ServerException populates its members correctly
     */
    public function testServerException(): void
    {
        $exception = new ServerException(json_encode($this->resultError));
        $this->assertEquals($this->resultError['code'], $exception->getOpaCode());
        $this->assertEquals($this->resultError['message'], $exception->getMessage());
        $this->assertEquals($this->resultError['errors'], $exception->getErrors());
    }

    /**
     * Ensure a bearer token setup works
     *
     * Unfortunately the MockWebServer cant be setup to check for tokens yet
     */
    public function testToken(): void
    {
        self::$server->setResponseOfPath(
            $this->getBaseURL() . '/data/auth/apia',
            new Response(json_encode($this->resultAllowTrue), [], 200)
        );
        $Client = new Client([  Client::OPT_AGENT_URL => self::$server->getServerRoot(),
                                Client::OPT_AUTH_TOKEN => 'TOKEN']);
        $response = $Client->policy('auth/apia', [], false, false, false, false);
        $this->assertArrayHasKey('allow', $response->getResults());
        $this->assertTrue($response->getByName('allow'));
    }

    /**
     * Ensure a sucessful data update returns an empty array
     */
    public function testDataUpdate(): void
    {
        self::$server->setResponseOfPath(
            $this->getBaseURL() . '/data/mine',
            new Response(json_encode($this->resultAllowTrue), [], 204)
        );
        $result = $this->client->dataUpdate('mine', json_encode(['this'=>'value']));
        $this->assertCount(0, $result);
    }
}
