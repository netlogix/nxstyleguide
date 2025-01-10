<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use GuzzleHttp\Client;
use Netlogix\Nxstyleguide\Factory\GuzzleClientWithTimeoutFactory;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();
    $services->load('Netlogix\\Nxstyleguide\\', '../Classes/');
    $services->set('guzzle_http_client_with_timeout', Client::class)
        ->factory([GuzzleClientWithTimeoutFactory::class, 'getClient']);
};
