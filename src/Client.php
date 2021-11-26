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
use UnexpectedValueException;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ClientException as HttpClientException;
use GuzzleHttp\Exception\RequestException as HttpException;
use GuzzleHttp\Exception\ServerException as HttpServerException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LogLevel;
use Segrax\OpenPolicyAgent\Exception\ServerException;
use Segrax\OpenPolicyAgent\Response as OpaResponse;

/**
 *
 */
class Client
{
    public const OPA_API_VER   = 'v1';
    public const OPT_AGENT_URL  = 'agent_url';
    public const OPT_AUTH_TOKEN = 'bearer_token';

    /**
     * @var ?LoggerInterface
     */
    private $logger = null;

    /**
     * @var array<string, string>
     */
    private $httpHeadersJson = ['Content-Type' => 'application/json'];

    /**
     * @var array<string, string>
     */
    private $httpHeadersText = ['Content-Type' => 'text/plain'];

    /**
     * @var array<string, string>
     */
    private $options = [self::OPT_AGENT_URL  => '',
                        self::OPT_AUTH_TOKEN => ''];

    /**
     * Class Setup
     *
     * @param array<string, string> $pOptions
     * @param LoggerInterface $pLogger
     */
    public function __construct(array $pOptions, LoggerInterface $pLogger = null)
    {
        if (empty($pOptions[self::OPT_AGENT_URL])) {
            throw new UnexpectedValueException($pOptions[self::OPT_AGENT_URL] . ' is not set');
        }

        $this->logger = $pLogger;
        $this->options = array_replace_recursive($this->options, $pOptions);
        // Append trailing / to agent url
        $this->options[self::OPT_AGENT_URL] = (rtrim($this->options[self::OPT_AGENT_URL], '/') . '/');
    }

    /**
     * Get the version information of the agent
     *
     * @return array<string>
     */
    public function getAgentVersion(): array
    {
        $url = $this->getUrlQuery($this->getDataUrl(), false, false, false, true);
        $result = $this->executePost($url);
        return $result->getVersion();
    }

    /**
     * Create or Update a document
     *
     * @return array<string>
     */
    public function dataUpdate(string $pDataName, string $pContent): array
    {
        return $this->executePut($this->getDataUrl($pDataName), true, $pContent);
    }

    /**
     * Create or Update a policy on the agent
     *
     * @return array<string>
     */
    public function policyUpdate(string $pPolicyName, string $pContent, bool $pMetrics): array
    {
        $url = $this->getUrlQuery($this->getPolicyUrl($pPolicyName), false, $pMetrics, false, false);
        return $this->executePut($url, false, $pContent);
    }

    /**
     * Execute a policy
     *
     * @param string $pPolicyName
     * @param array<string> $pInputData
     * @param bool $pExplain
     * @param bool $pMetrics
     * @param bool $pInstrument
     * @param bool $pProvenance
     *
     * @return OpaResponse
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
     *
     * @param string $pQuery
     * @param bool $pExplain
     * @param bool $pMetrics
     * @param bool $pInstrument
     * @param bool $pProvenance
     *
     * @return OpaResponse
     */
    public function query(
        string $pQuery,
        bool $pExplain,
        bool $pMetrics,
        bool $pInstrument,
        bool $pProvenance
    ): OpaResponse {
        $url = $this->getUrlQuery($this->getQueryUrl(), $pExplain, $pMetrics, $pInstrument, $pProvenance);
        return $this->executePost($url, ['query' => $pQuery]);
    }

    /**
     * Execute a POST
     *
     * @param string $pUrl
     * @param array<mixed> $pContent
     *
     * @return OpaResponse
     */
    private function executePost(string $pUrl, array $pContent = []): OpaResponse
    {
        $response = $this->execute('POST', $pUrl, true, json_encode($pContent, JSON_THROW_ON_ERROR));
        return new OpaResponse($response);
    }

    /**
     * Execute a PUT
     *
     * @param string $pUrl
     * @param bool $pJson
     * @param string $pBody
     *
     * @return array<string>
     */
    private function executePut(string $pUrl, bool $pJson, string $pBody = ""): array
    {
        $response = $this->execute('PUT', $pUrl, $pJson, $pBody);

        // Success with no content
        if ($response->getStatusCode() === 204) {
            return [];
        }

        return json_decode($response->getBody()->__toString(), true);
    }

    /**
     * Execute a request
     *
     * @param string $pMethod
     * @param string $pUrl
     * @param bool $pJson
     * @param string $pBody
     *
     * @return ResponseInterface
     */
    private function execute(string $pMethod, string $pUrl, bool $pJson, string $pBody = ""): ResponseInterface
    {
        $headers = ($pJson === true) ? $this->httpHeadersJson : $this->httpHeadersText;
        if (!empty($this->options[self::OPT_AUTH_TOKEN])) {
            $headers['Authorization'] = 'Bearer ' . $this->options[self::OPT_AUTH_TOKEN];
        }

        try {
            $client = new HttpClient(['headers' => $headers]);
            $response = $client->request($pMethod, $pUrl, ['body' => $pBody]);
        } catch (HttpClientException | HttpServerException $exception) {
            $this->log(LogLevel::ERROR, "opa-Client: Error", [$pUrl, $pBody]);
            $response = $exception->getResponse();
            throw new ServerException($response->getBody()->__toString());
        } catch (HttpException $exception) {
            $this->log(LogLevel::ERROR, "opa-Client: Not Available", [$pUrl]);
            throw new RuntimeException("OPA Client unavailable: " . $exception->getMessage(), 0, $exception);
        }

        return $response;
    }

    /**
     * Add parameters to the url
     *
     * @param string $pUrl
     * @param bool $pExplain
     * @param bool $pMetrics
     * @param bool $pInstrument
     * @param bool $pProvenance
     *
     * @return string
     */
    private function getUrlQuery(
        string $pUrl,
        bool $pExplain,
        bool $pMetrics,
        bool $pInstrument,
        bool $pProvenance
    ): string {
        $query = '';
        if ($pExplain === true) {
            $query .= 'explain=full&';
        }
        if ($pMetrics === true) {
            $query .= 'metrics=true&';
        }
        if ($pInstrument === true) {
            $query .= 'instrument=true&';
        }
        if ($pProvenance === true) {
            $query .= 'provenance=true&';
        }
        if (empty($query)) {
            return $pUrl;
        }
        return "$pUrl?" . rtrim($query, '&');
    }

    /**
     * Get the url to the agent and API version
     *
     * @return string
     */
    private function getBaseUrl(): string
    {
        return $this->options[self::OPT_AGENT_URL] . self::OPA_API_VER . "/";
    }

    /**
     * Get the url to the data API
     */
    private function getDataUrl(string $pName = ""): string
    {
        return $this->getBaseUrl() . "data/$pName";
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

    /**
     * Log if available
     *
     * @param string $pLevel
     * @param string $pMessage
     * @param array<mixed> $pContext
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
