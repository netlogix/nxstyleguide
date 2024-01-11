<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use GuzzleHttp\Client;
use Netlogix\Nxstyleguide\Factory\GuzzleClientWithTimeoutFactory;
use Netlogix\Nxstyleguide\Middleware\ServerSideRenderingMiddleware;

return function (ContainerConfigurator $containerConfigurator) {
    $services = $containerConfigurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('Netlogix\\Nxstyleguide\\', '../Classes/');

    $services->set('guzzle_http_client_with_timeout', Client::class)
        ->factory([GuzzleClientWithTimeoutFactory::class, 'getClient']);

    $services->set(ServerSideRenderingMiddleware::class)
        ->arg('$requestFactory', service('Psr\Http\Message\RequestFactoryInterface'))
        ->arg('$streamFactory', service('Psr\Http\Message\StreamFactoryInterface'))
        ->arg('$client', service('guzzle_http_client_with_timeout'));
};
