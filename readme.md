# Open Policy Agent Library

This library provides a client for the Open Policy Agent (OPA), a PSR-15 authorization middleware and a PSR-15 bundle distributor middleware.


[![Latest Version](https://img.shields.io/packagist/v/segrax/open-policy-agent)](https://packagist.org/packages/segrax/open-policy-agent)
[![Packagist](https://img.shields.io/packagist/dm/segrax/open-policy-agent)](https://packagist.org/packages/segrax/open-policy-agent)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE.md)
[![Build Status](https://api.travis-ci.com/segrax/openpolicyagent.svg)](https://travis-ci.com/segrax/openpolicyagent)
[![codecov](https://codecov.io/gh/segrax/openpolicyagent/branch/master/graph/badge.svg)](https://codecov.io/gh/segrax/openpolicyagent)


## Install
Install the latest using [composer](https://getcomposer.org/).
``` bash
composer require segrax/openpolicyagent
```

### Usage Examples
For ready to use examples, please see [segrax/opa-php-examples](https://github.com/segrax/opa-php-examples)

### Client Usage
```php
use Segrax\OpenPolicyAgent\Client;

$apiPolicy = "package my.api
              default allow=false
              allow {
                  input.path = [\"abc\"]
                  input.user == \"a random user\"
              }";

$client = new Client([ Client::OPT_AGENT_URL => 'http://127.0.0.1:8181/', Client::OPT_AUTH_TOKEN => 'MyToken']);

// Push a policy to the agent
$client->policyUpdate('my/api', $apiPolicy, false);

// Execute the policy
$inputs = [ 'path' => ['abc'],
            'user' => 'a random user'];

$res = $client->policy('my/api', $inputs, false, false, false, false );
if ($res->getByName('allow') === true ) {
    // Do stuff
}
```

### Authorization Middleware
Create the client, and add the Authorization object onto the middleware stack
```php
use Segrax\OpenPolicyAgent\Client;
use Segrax\OpenPolicyAgent\Middleware\Authorization;

$app = AppFactory::create();

$client = new Client([Client::OPT_AGENT_URL => 'http://127.0.0.1:8181/']);
$app->add(new Authorization(
                [Authorization::OPT_POLICY => 'auth/api'],
                $client,
                $app->getResponseFactory()));

```

### Distributor Middleware
Insert the middleware, it will respond to bundle requests at /opa/bundles/{service_name} for users with a valid JWT with the subfield 'opa'

```php
use Segrax\OpenPolicyAgent\Client;
use Segrax\OpenPolicyAgent\Middleware\Distributor;

$app = AppFactory::create();

$app->add(new Distributor(
                        [Distributor::OPT_POLICY_PATH => __DIR__ . '/opa',
                         Distributor::OPT_AGENT_USER => 'opa'],
                        $app->getResponseFactory(),
                        new StreamFactory(),
                        $app->getLogger()));

// Add a GET route for the opa bundle route
$app->get('/opa/bundles/{name}', function (Request $request, Response $response, array $args) {
    return $response->withStatus(404);
});

```

## Code Testing
``` bash
make tests
```

## Security

If you discover any security related issues, please email [segrax19@gmail.com](mailto:segrax19@gmail.com) instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE.txt) for more information.
