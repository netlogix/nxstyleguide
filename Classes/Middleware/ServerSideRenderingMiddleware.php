<?php

declare(strict_types=1);

namespace Netlogix\Nxstyleguide\Middleware;

use Exception;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionClass;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ServerSideRenderingMiddleware implements MiddlewareInterface
{
    private RequestFactoryInterface $requestFactory;

    private StreamFactoryInterface $streamFactory;

    private ClientInterface $client;

    public function __construct(
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        ClientInterface $client
    ) {
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->client = $client;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if (getenv('SSR_SUB_REQUEST_URI') === false) {
            return $response;
        }

        if (strpos($response->getHeaderLine('Content-Type'), 'text/html') === false) {
            return $response;
        }

        if (
            array_key_exists('type', $request->getQueryParams())
            && $request->getQueryParams()['type'] > 0
        ) {
            return $response;
        }

        $requestBody = [
            'body' => (string) $response->getBody(),
            'url' => (string) $request->getUri(),
            'labels' => $this->getLabels(),
            'settings' => $this->getSettings(),
        ];

        $req = $this->requestFactory
            ->createRequest('POST', getenv('SSR_SUB_REQUEST_URI'))
            ->withBody($this->streamFactory->createStream(json_encode($requestBody, JSON_THROW_ON_ERROR)));

        try {
            $res = $this->client->sendRequest($req);

            return ($res->getStatusCode() === 200)
                ? $response->withBody($res->getBody())
                : $response;
        } catch (Exception $e) {
            return $response;
        }
    }

    private function getLabels(): array
    {
        $pageRender = GeneralUtility::makeInstance(PageRenderer::class);

        $reflectionClass = new ReflectionClass($pageRender);
        $reflectionMethod = $reflectionClass->getMethod('parseLanguageLabelsForJavaScript');
        $reflectionMethod->setAccessible(true);

        return $reflectionMethod->invoke($pageRender);
    }

    private function getSettings(): array
    {
        $pageRender = GeneralUtility::makeInstance(PageRenderer::class);

        $reflectionClass = new ReflectionClass($pageRender);
        $reflectionProperty = $reflectionClass->getProperty('inlineSettings');
        $reflectionProperty->setAccessible(true);

        return $reflectionProperty->getValue($pageRender);
    }
}
