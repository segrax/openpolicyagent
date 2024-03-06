<?php

/*
Copyright (c) 2022 Robert Crossfield

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

namespace Segrax\OpenPolicyAgent\Tests\Agent;

use PHPUnit\Framework\TestCase;
use Segrax\OpenPolicyAgent\Agent\Provenance;

class ProvenanceTest extends TestCase
{
    private Provenance $provenance;

    #[\Override]
    public function setUp(): void
    {
        $this->provenance = new Provenance($this->agentResponse());
    }

    public function testVersion(): void
    {
        $this->assertSame($this->agentResponse()['version'], $this->provenance->getVersion());
    }

    public function testBuildCommit(): void
    {
        $this->assertSame($this->agentResponse()['build_commit'], $this->provenance->getBuildCommit());
    }

    public function testBuildTimestamp(): void
    {
        $this->assertSame($this->agentResponse()['build_timestamp'], $this->provenance->getBuildTimestamp());
    }
    public function testBuildHostname(): void
    {
        $this->assertSame($this->agentResponse()['build_hostname'], $this->provenance->getBuildHostname());
    }

    public function testBundles(): void
    {
        $bundles = $this->provenance->getBundles();

        foreach ($this->agentResponse()['bundles'] as $name => $data) {
            $this->assertArrayHasKey($name, $bundles);

            $this->assertSame($data['revision'], $bundles[$name]->getRevision());
            $this->assertSame($name, $bundles[$name]->getName());
        }
    }

    private function agentResponse(): array
    {
        return [
            'version' => '0.42.0',
            'build_commit' => '9b5fb9b',
            'build_timestamp' => '2022-07-04T12:23:16Z',
            'build_hostname' => 'd3afd1ae56c8',
            'bundles' => [
                "file" => ["revision" => '123456']
            ]
        ];
    }
}
