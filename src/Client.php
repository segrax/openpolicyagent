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

namespace Segrax\OpenPolicyAgent;

use RuntimeException;
use Psr\Log\LoggerInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Segrax\OpenPolicyAgent\Exception\ServerException;
use Segrax\OpenPolicyAgent\Response as OpaResponse;

/**
 *
 */
class Client
{
    public const OPA_API_VER   = 'v1';

    private string $agentUrl;
    private string $agentToken;

    private ?LoggerInterface $logger = null;
    private ClientInterface $httpClient;
    private RequestFactoryInterface $requestFactory;
    
    /**
     * Class Setup
     */
    public function __construct(?LoggerInterface $pLogger = null, ClientInterface $pHttpClient, RequestFactoryInterface $pRequestFactory, string $pAgentUrl, string $pAgentToken = '')
    {
        $this->agentUrl = (rtrim($pAgentUrl, '/') . '/');
        $this->agentToken = $pAgentToken;

        $this->logger = $pLogger;
        $this->httpClient = $pHttpClient;
        $this->requestFactory = $pRequestFactory;
    }

    /**
     * Get the version information of the agent
     *
     * @return array<string>
     */
    public function getAgentVersion(): array
    {
        $url = $this->getUrlQuery($this->getDataUrl(), false, false, false, true);
        $result = $this->executeGet($url);
        return $result->getVersion();
    }

    /**
     * Create or Update a document
     */
    public function dataUpdate(string $pDataName, string $pContent): bool
    {
        return $this->executePut($this->getDataUrl($pDataName), true, $pContent);
    }

    /**
     * Create or Update a policy on the agent
     */
    public function policyUpdate(string $pPolicyName, string $pContent, bool $pMetrics): bool
    {
        $url = $this->getUrlQuery($this->getPolicyUrl($pPolicyName), false, $pMetrics, false, false);
        return $this->executePut($url, false, $pContent);
    }

    /**
     * Execute a policy
     */
    public function policy(
        string $pPolicyName,
        array $pInputData,
        bool $pExplain,
        bool $pMetrics,
        bool $pInstrument,
        bool $pProvenance
    ): OpaResponse {
        $url = $this->getUrlQuery($this->getDataUrl($pPolicyName), $pExplain, $pMetrics, $pInstrument, $pProvenance);
        return $this->executePost($url, ['input' => $pInputData]);
    }

    /**
     * Execute a query
     */
    public function query(
        string $pQuery,
        array $pInputData,
        bool $pExplain,
        bool $pMetrics,
        bool $pInstrument,
        bool $pProvenance
    ): OpaResponse {
        $url = $this->getUrlQuery($this->getQueryUrl(), $pExplain, $pMetrics, $pInstrument, $pProvenance);
        return $this->executePost($url, ['query' => $pQuery, 'input' => $pInputData]);
    }

    /**
     * Execute a GET
     */
    private function executeGet(string $pUrl, array $pContent = []): OpaResponse
    {
        $response = $this->execute('GET', $pUrl, true, json_encode($pContent, JSON_THROW_ON_ERROR));
        return new OpaResponse($response);
    }

    /**
     * Execute a POST
     */
    private function executePost(string $pUrl, array $pContent = []): OpaResponse
    {
        $response = $this->execute('POST', $pUrl, true, json_encode($pContent, JSON_THROW_ON_ERROR));
        return new OpaResponse($response);
    }

    /**
     * Execute a PUT
     */
    private function executePut(string $pUrl, bool $pJson, string $pBody = ""): bool
    {
        $response = $this->execute('PUT', $pUrl, $pJson, $pBody);

        // Success with no content
        if ($response->getStatusCode() === 200 || $response->getStatusCode() === 204) {
            return true;
        }

        return false;
    }

    /**
     * Execute a request
     */
    private function execute(string $pMethod, string $pUrl, bool $pJson, string $pBody = ""): ResponseInterface
    {
        $request = $this->requestFactory->createRequest($pMethod, $pUrl);
        $request = $request->withHeader('Content-Type', ($pJson == true) ? 'application/json' : 'text/plain');

        if (!empty($this->agentToken)) {
            $request = $request->withHeader('Authorization', 'Bearer ' . $this->agentToken);
        }

        if(strlen($pBody)) {
            $request->getBody()->write($pBody);
        }

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (NetworkExceptionInterface $exception) {
            $this->logger?->error("opa-client: Connection Failed", ['url' => $pUrl]);
            throw new RuntimeException("OPA Client unavailable: " . $exception->getMessage(), 0, $exception);
        }
        //echo $response->getStatusCode();
        //var_dump(json_encode((string) $response->getBody()));exit;

        if($response->getStatusCode() === 400) {
            throw new ServerException($response->getBody()->__toString());
        }
        
        //var_dump(json_encode((string) $response->getBody()));exit;

        return $response;
    }

    /**
     * Add parameters to the url
     */
    private function getUrlQuery(
        string $pUrl,
        bool $pExplain,
        bool $pMetrics,
        bool $pInstrument,
        bool $pProvenance
    ): string {

        $query = http_build_query([
            'explain' => $pExplain == true ? 'true' : 'false',
            'metrics' => $pMetrics == true ? 'true' : 'false',
            'instrument' => $pInstrument == true ? 'true' : 'false',
            'provenance' => $pProvenance == true ? 'true' : 'false',
        ]);

        return "$pUrl?" . rtrim($query, '&');
    }

    /**
     * Get the url to the agent and API version
     */
    private function getBaseUrl(): string
    {
        return $this->agentUrl . self::OPA_API_VER . "/";
    }

    /**
     * Get the url to the data API
     */
    private function getDataUrl(string $pName = ""): string
    {
        return $this->getBaseUrl() . "data" . ((strlen($pName) == 0) ? '' : "/$pName");
    }

    /**
     * Get the url to the query API
     */
    private function getQueryUrl(): string
    {
        return $this->getBaseUrl() . 'query';
    }

    /**
     * Get the url to the policy API
     */
    private function getPolicyUrl(string $pName): string
    {
        return $this->getBaseUrl() . "policies/$pName";
    }
}
