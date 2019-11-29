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
use Segrax\OpenPolicyAgent\Engine;

class Authorization implements MiddlewareInterface
{
    /**
     * Option array keys
     */
    public const OPT_ATTRIBUTE_RESULT = 'attrResult';
    public const OPT_ATTRIBUTE_INPUT  = 'attrInput';
    public const OPT_POLICY           = 'policy';
    public const OPT_POLICY_ALLOW     = 'policy_allow';

    /**
     * @var Engine
     */
    private $engine;

    /**
     * @var ?LoggerInterface
     */
    private $logger;

    /**
     * @var ResponseFactoryInterface
     */
    private $responseFactory;

    /**
     * @var array
     */
    private $options = [
        self::OPT_ATTRIBUTE_RESULT  => 'openpolicyagent',
        self::OPT_ATTRIBUTE_INPUT   => 'token',
        self::OPT_POLICY            => '',
        self::OPT_POLICY_ALLOW      => 'allow'
    ];

    /**
     *
     */
    public function __construct(
        array $pOptions,
        Engine $pEngine,
        ResponseFactoryInterface $pResponseFactory,
        LoggerInterface $pLogger = null
    ) {
        $this->logger = $pLogger;
        if (empty($pOptions[self::OPT_POLICY])) {
            $this->log(LogLevel::EMERGENCY, 'opa-authz: no policy set');
            throw new InvalidArgumentException('opa-authz: no policy set');
        }

        $this->engine = $pEngine;
        $this->responseFactory = $pResponseFactory;
        $this->options = array_replace_recursive($this->options, $pOptions);
    }

    /**
     * Process server request
     */
    public function process(ServerRequestInterface $pRequest, RequestHandlerInterface $pHandler): ResponseInterface
    {
        $input = $this->policyInputsPrepare($pRequest);
        $result = $this->engine->policy($this->options[self::OPT_POLICY], $input, false, false, false, false);
        // Add the result as an attribute
        if (!empty($this->options[self::OPT_ATTRIBUTE_RESULT])) {
            $pRequest = $pRequest->withAttribute($this->options[self::OPT_ATTRIBUTE_RESULT], $result);
        }

        $allowed = $result->getByName($this->options[self::OPT_POLICY_ALLOW]);
        if ($allowed === true) {
            return $pHandler->handle($pRequest);
        }

        $this->log(LogLevel::INFO, 'opa-authz: Unauthorized', [$input, $result]);
        return $this->responseFactory->createResponse(403, 'Unauthorized');
    }

    /**
     * Prepare the parameters to pass the policy
     */
    private function policyInputsPrepare(ServerRequestInterface $pRequest): array
    {
        $name = $this->options[self::OPT_ATTRIBUTE_INPUT];
        $attribute = $pRequest->getAttribute($name);
        $input = [
            $name    => $attribute ?? [],
            'user'   => $attribute['sub'] ?? '',
            'path'   => array_values(array_filter(explode('/', $pRequest->getUri()->getPath()))),
            'method' => $pRequest->getMethod()
        ];
        return $input;
    }

    private function log(string $pLevel, string $pMessage, array $pContext = []): void
    {
        if (!is_null($this->logger)) {
            // @codeCoverageIgnoreStart
            $this->logger->log($pLevel, $pMessage, $pContext);
            // @codeCoverageIgnoreEnd
        }
    }
}
