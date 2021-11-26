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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Segrax\OpenPolicyAgent\Client;
use Segrax\OpenPolicyAgent\Exception\PolicyException;
use Segrax\OpenPolicyAgent\Response;
use Exception;

/**
 * Class for providing an authorization layer to the middleware
 */
class Authorization implements MiddlewareInterface
{
    /**
     * Option array keys
     */
    public const OPT_ATTRIBUTE_RESULT        = 'attrResult';
    public const OPT_ATTRIBUTE_INPUT_DEFAULT = 'attrResultDefault';
    public const OPT_ATTRIBUTE_INPUT         = 'attrInput';
    public const OPT_POLICY                  = 'policy';
    public const OPT_POLICY_ALLOW            = 'policy_allow';
    public const OPT_INPUT_CALLBACK          = 'inputCallback';
    public const OPT_POLICY_MISSING_CALLBACK = 'policy_missing_callback';

    /**
     * @var Client
     */
    private $client;

    /**
     * @var ?LoggerInterface
     */
    private $logger;

    /**
     * @var ResponseFactoryInterface
     */
    private $responseFactory;

    /**
     * @var array<string, mixed>
     */
    private $options = [
        self::OPT_ATTRIBUTE_RESULT          => 'openpolicyagent',
        self::OPT_ATTRIBUTE_INPUT           => 'token',
        self::OPT_ATTRIBUTE_INPUT_DEFAULT   => ['sub' => ''],
        self::OPT_POLICY                    => '',
        self::OPT_POLICY_ALLOW              => 'allow',
        self::OPT_INPUT_CALLBACK            => null,
        self::OPT_POLICY_MISSING_CALLBACK   => null
    ];

    /**
     * Class Setup
     *
     * @param array<mixed,string> $pOptions
     * @param Client $pClient
     * @param ResponseFactoryInterface $pResponseFactory
     * @param LoggerInterface $pLogger
     */
    public function __construct(
        array $pOptions,
        Client $pClient,
        ResponseFactoryInterface $pResponseFactory,
        LoggerInterface $pLogger = null
    ) {
        $this->logger = $pLogger;
        if (empty($pOptions[self::OPT_POLICY])) {
            $this->log(LogLevel::EMERGENCY, 'opa-authz: no policy set');
            throw new InvalidArgumentException('opa-authz: no policy set');
        }

        $this->client = $pClient;
        $this->responseFactory = $pResponseFactory;
        $this->options = array_replace_recursive($this->options, $pOptions);
    }

    /**
     * Process server request
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $input = $this->policyInputsPrepare($request);
        try {
            $result = $this->client->policy($this->options[self::OPT_POLICY], $input, false, false, false, false);
        } catch (PolicyException $exception) {
            if ($this->policyMissing($input)) {
                return $handler->handle($request);
            }

            return $this->responseFactory->createResponse(403, 'Unauthorized');
        }

        // Add the result as an attribute
        if (!empty($this->options[self::OPT_ATTRIBUTE_RESULT])) {
            $request = $request->withAttribute($this->options[self::OPT_ATTRIBUTE_RESULT], $result);
        }

        $allowed = $result->getByName($this->options[self::OPT_POLICY_ALLOW]);
        if ($allowed === true) {
            return $handler->handle($request);
        }

        $this->log(LogLevel::INFO, 'opa-authz: Unauthorized', [$input, $result]);
        return $this->responseFactory->createResponse(403, 'Unauthorized');
    }

    /**
     * Handle a missing policy
     *
     * @param array<mixed> $pInput
     */
    private function policyMissing(array $pInput): bool
    {
        $this->log(LogLevel::WARNING, 'opa-authz: Policy not found', [$pInput, $this->options[self::OPT_POLICY]]);

        // Is a handler available
        if (!is_callable($this->options[self::OPT_POLICY_MISSING_CALLBACK])) {
            throw new Exception('Policy not found');
        }

        // Did it fail?
        if (call_user_func($this->options[self::OPT_POLICY_MISSING_CALLBACK], $pInput) === false) {
            return false;
        }

        $this->log(LogLevel::WARNING, 'opa-authz: Policy auth override', [$pInput, $this->options[self::OPT_POLICY]]);
        return true;
    }

    /**
     * Prepare the parameters to pass the policy
     *
     * @return array<mixed>
     */
    private function policyInputsPrepare(ServerRequestInterface $request): array
    {
        $name = $this->options[self::OPT_ATTRIBUTE_INPUT];
        $attribute = $request->getAttribute($name, $this->options[self::OPT_ATTRIBUTE_INPUT_DEFAULT]);

        $input = [  'path'   => array_values(array_filter(explode('/', urldecode($request->getUri()->getPath())))),
                    'method' => $request->getMethod(),
                    'user' => $attribute['sub'] ?? '',
                    $name => $attribute
                 ];

        if (is_callable($this->options[self::OPT_INPUT_CALLBACK])) {
            $input = array_merge_recursive(call_user_func($this->options[self::OPT_INPUT_CALLBACK], $request), $input);
        }
        return $input;
    }

    /**
     * Log if available
     *
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
