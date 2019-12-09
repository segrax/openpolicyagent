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

use Psr\Http\Message\ResponseInterface;
use Segrax\OpenPolicyAgent\Exception\PolicyException;

/**
 * Holds a response from OPA
 */
class Response
{
    private const OPA_DECISIONID_ARRAY = 'decision_id';
    private const OPA_EXPLAIN_ARRAY = 'explanation';
    private const OPA_METRIC_ARRAY = 'metrics';
    private const OPA_PROVENANCE_ARRAY = 'provenance';
    private const OPA_RESULT_ARRAY = 'result';

    /**
     * @var string
     */
    private $decisionid = '';

    /**
     * @var array
     */
    private $explain = [];

    /**
     * @var array
     */
    private $metrics = [];

    /**
     * @var array
     */
    private $result = [];

    /**
     * @var array
     */
    private $version = [];

    /**
     * Create from a HTTP response
     */
    public function __construct(ResponseInterface $pResponse)
    {
        $data = json_decode($pResponse->getBody()->__toString(), true);
        $this->decisionid = $data[self::OPA_DECISIONID_ARRAY] ?? '';
        $this->explain = $data[self::OPA_EXPLAIN_ARRAY] ?? [];
        $this->metrics = $data[self::OPA_METRIC_ARRAY] ?? [];
        $this->result = $data[self::OPA_RESULT_ARRAY] ?? [];
        $this->version = $data[self::OPA_PROVENANCE_ARRAY] ?? [];

        if (!isset($data[self::OPA_RESULT_ARRAY])) {
            throw new PolicyException($this, 'Policy not found');
        }
    }

    /**
     * Get a specific result by its name
     *
     * @return mixed
     */
    public function getByName(string $pName)
    {
        return $this->result[$pName] ?? null;
    }

    /**
     * Get all results
     */
    public function getResults(): array
    {
        return $this->result;
    }

    /**
     * Get the decisionID
     */
    public function getDecisionID(): string
    {
        return $this->decisionid;
    }

    /**
     * Ge the explaination
     */
    public function getExplain(): array
    {
        return $this->explain;
    }

    /**
     * Get the metrics
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    /**
     * Get the version
     */
    public function getVersion(): array
    {
        return $this->version;
    }

    /**
     * Does the result set have this key
     */
    public function has(string $pName): bool
    {
        return !empty($this->result[$pName]);
    }
}
