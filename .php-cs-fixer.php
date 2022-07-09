<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in('tests/')
    ->in('src/');

$config = new PhpCsFixer\Config();

return $config->setRules([
	'@PSR1' => true,
    '@PSR12' => true,
        'strict_param' => true,
        'array_syntax' => ['syntax' => 'short']
])
->setRiskyAllowed(true)
->setFinder($finder);
