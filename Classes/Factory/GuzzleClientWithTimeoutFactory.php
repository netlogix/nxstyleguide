<?php

declare(strict_types=1);

namespace Netlogix\Nxstyleguide\Factory;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;

/**
 * This class is mainly a copy of the TYPO3\CMS\Core\Http\Client\GuzzleClientFactory with
 * the difference that it allows to set a request timeout and can be called statically.
 */
class GuzzleClientWithTimeoutFactory
{
    public static function getClient(): ClientInterface
    {
        $httpOptions = $GLOBALS['TYPO3_CONF_VARS']['HTTP'];
        $httpOptions['verify'] = filter_var(
            $httpOptions['verify'],
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        ) ?? $httpOptions['verify'];

        if (isset($GLOBALS['TYPO3_CONF_VARS']['HTTP']['handler']) && is_array(
            $GLOBALS['TYPO3_CONF_VARS']['HTTP']['handler']
        )) {
            $stack = HandlerStack::create();
            foreach ($GLOBALS['TYPO3_CONF_VARS']['HTTP']['handler'] ?? [] as $name => $handler) {
                $stack->push($handler, (string) $name);
            }
            $httpOptions['handler'] = $stack;
        }

        $httpOptions['timeout'] = getenv('SSR_SUB_REQUEST_TIMEOUT') ?: 15;

        return new Client($httpOptions);
    }
}
