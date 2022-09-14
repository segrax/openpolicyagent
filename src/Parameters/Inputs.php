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

namespace Segrax\OpenPolicyAgent\Parameters;

use Psr\Http\Message\ServerRequestInterface;

class Inputs implements CollectorInterface
{
    /**
     * @var array<CollectorInterface>
     */
    private array $collectors = [];

    /**
     * Add a collector
     */
    public function addCollector(CollectorInterface $pCollector)
    {
        $this->collectors[] = $pCollector;
    }

    /**
     * Get parameters from all collectors
     */
    public function collect(ServerRequestInterface $pRequest): array
    {
        $params = [];

        foreach ($this->collectors as $collector) {
            try {
                $params = array_merge_recursive($params, $collector->collect($pRequest));
            } catch (\Throwable $e) {
                continue;
            }
        }

        return ['params' => $params];
    }
}
