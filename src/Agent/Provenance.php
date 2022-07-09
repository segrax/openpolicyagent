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

namespace Segrax\OpenPolicyAgent\Agent;

class Provenance
{
    private string $version;
    private string $buildCommit;
    private string $buildTimestamp;
    private string $buildHostname;

    private array $bundles = [];

    public function __construct(array $pVersionData)
    {
        $this->version = $pVersionData['version'];
        $this->buildCommit = $pVersionData['build_commit'];
        $this->buildTimestamp = $pVersionData['build_timestamp'];
        $this->buildHostname = $pVersionData['build_hostname'];

        foreach ($pVersionData['bundles'] as $name => $data) {
            $this->bundles[$name] = new Bundle($name, $data);
        }
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getBuildCommit(): string
    {
        return $this->buildCommit;
    }

    public function getBuildTimestamp(): string
    {
        return $this->buildTimestamp;
    }

    public function getBuildHostname(): string
    {
        return $this->buildHostname;
    }

    /**
     * @return array<string, Bundle>
     */
    public function getBundles(): array
    {
        return $this->bundles;
    }
}
