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

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Segrax\OpenPolicyAgent\Exception\ServerException;

class ServerExceptionTest extends TestCase
{
    public function setUp(): void
    {
    }

    public function testErrorResponse()
    {
        $exception =  new ServerException('        {
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
            ]}');

        $this->assertSame('invalid_parameter', $exception->getOpaCode());
        $this->assertNotEmpty($exception->getErrors());
        $this->assertArrayHasKey('code', $exception->getErrors()[0]);
    }

    public function testInvalidErrorResponseThrows()
    {
        $this->expectException(RuntimeException::class);
        $exception =  new ServerException('{}');
    }
}
