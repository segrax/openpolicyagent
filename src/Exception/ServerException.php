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

namespace Segrax\OpenPolicyAgent\Exception;

use RuntimeException;

/**
 * Exception thrown when OPA fails to process a request
 */
class ServerException extends RuntimeException
{
/*  private const MsgCompileModuleError         = "error(s) occurred while compiling module(s)";
    private const MsgParseQueryError            = "error(s) occurred while parsing query";
    private const MsgCompileQueryError          = "error(s) occurred while compiling query";
    private const MsgEvaluationError            = "error(s) occurred while evaluating query";
    private const MsgUnauthorizedUndefinedError = "authorization policy missing or undefined";
    private const MsgUnauthorizedError          = "request rejected by administrative policy";
    private const MsgUndefinedError             = "document missing or undefined";
    private const MsgPluginConfigError          = "error(s) occurred while configuring plugin(s)";*/
    private const OPA_KEY_ERRORCODE   = 'code';
    private const OPA_KEY_ERRORMSG    = 'message';
    private const OPA_KEY_ERRORS      = 'errors';

    /*
        {
        "code": "invalid_parameter",
        "message": "error(s) occurred while compiling module(s)",
        "errors": [
            {
            "code": "rego_type_error",
            "message": "multiple default rules named allow found",
            "location": {
                "file": "authz.rego",
                "row": 3,
                "col": 1
            }
            }
        ]}
    */

    /**
     * @var array<mixed>
     */
    private array $response = [];

    /**
     * @var array<mixed>
     */
    private array $errors = [];

    /**
     * @var string
     */
    private string $opaCode = '';

    /**
     * Class Setup
     *
     * Decode a JSON body, and load the error from it
     */
    public function __construct(string $pBody)
    {
        $this->response = json_decode($pBody, true, 512, JSON_THROW_ON_ERROR);
        if (empty($this->response[self::OPA_KEY_ERRORMSG]) || empty($this->response[self::OPA_KEY_ERRORCODE])) {
            throw new RuntimeException("ServerException occured, but data missing");
        }

        $this->opaCode = $this->response[self::OPA_KEY_ERRORCODE];

        if (isset($this->response[self::OPA_KEY_ERRORS])) {
            $this->errors = $this->response[self::OPA_KEY_ERRORS];
        }

        parent::__construct($this->response[self::OPA_KEY_ERRORMSG]);
    }

    /**
     * Get the OPA error code
     */
    public function getOpaCode(): string
    {
        return $this->opaCode;
    }

    /**
     * Get the errors
     *
     * @return array<mixed>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
