<?php

declare(strict_types=1);

namespace Netlogix\Nxstyleguide\Middleware;

use Exception;
use GuzzleHttp\Exception\ResponseTransferException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\TimeTracker\TimeTracker;

use function Sentry\captureException;

readonly class ServerSideRenderingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        #[Autowire(service: 'guzzle_http_client_with_timeout')] private ClientInterface $client,
        private PageRenderer $pageRenderer,
        private TimeTracker $timeTracker,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if (getenv('SSR_SUB_REQUEST_URI') === false) {
            return $response;
        }

        if (!str_contains($response->getHeaderLine('Content-Type'), 'text/html')) {
            return $response;
        }

        if (array_key_exists('type', $request->getQueryParams()) && $request->getQueryParams()['type'] > 0) {
            return $response;
        }

        $this->timeTracker->push('Server Side Rendering');
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

            if ($res->getStatusCode() === 200) {
                return $response->withBody($res->getBody());
            }
        } catch (Exception $exception) {
            // connection errors, timeouts and resets are expected while the ssr server is busy or restarting,
            // also after the response headers were already received
            if (
                function_exists('\Sentry\captureException') &&
                !($exception instanceof NetworkExceptionInterface) &&
                !($exception instanceof ResponseTransferException)
            ) {
                captureException($exception);
            }
        } finally {
            $this->timeTracker->pull();
        }

        // the page without ssr markup must not end up in a shared cache
        return $response->withHeader('Cache-Control', 'private, no-store');
    }

    private function getLabels(): array
    {
        $method = new ReflectionMethod($this->pageRenderer, 'parseLanguageLabelsForJavaScript');
        return $method->invoke($this->pageRenderer);
    }

    private function getSettings(): array
    {
        $property = new ReflectionProperty($this->pageRenderer, 'inlineSettings');
        return $property->getValue($this->pageRenderer);
    }
}
