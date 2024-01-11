<?php

declare(strict_types=1);

use Netlogix\Nxstyleguide\Middleware\ServerSideRenderingMiddleware;

return [
    'frontend' => [
        'netlogix/nxstyleguide/server-side-rendering-middleware' => [
            'target' => ServerSideRenderingMiddleware::class,
            'after' => ['typo3/cms-frontend/maintenance-mode'],
        ],
    ],
];
