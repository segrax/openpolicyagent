# Open Policy Agent Library

This library provides an interface to the Open Policy Agent (OPA), a PSR-15 authorization middleware and a PSR-15 bundle distributor middleware.

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Build Status](https://travis-ci.com/segrax/openpolicyagent.svg&branch=master)](https://travis-ci.com/segrax/openpolicyagent)
[![codecov](https://codecov.io/gh/segrax/openpolicyagent/branch/master/graph/badge.svg)](https://codecov.io/gh/segrax/openpolicyagent)


## Install

Install the latest using [composer](https://getcomposer.org/).

``` bash
composer require segrax/openpolicyagent
```

## Usage

``` php
use Segrax\OpenPolicyAgent\Client;
use Segrax\OpenPolicyAgent\Middleware\Authorization;

$app = AppFactory::create();

$client = new Client([Client::OPT_AGENT_URL => 'http://127.0.0.1:8181/']);
$app->add(new Authorization(
                [Authorization::OPT_POLICY => 'auth/api'],
                $client,
                $app->getResponseFactory()));

```

## Authorization Middleware


## Distributor Middleware


## Testing

``` bash
make tests
```

## Security

If you discover any security related issues, please email [segrax19@gmail.com](mailto:segrax19@gmail.com) instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE.txt) for more information.
