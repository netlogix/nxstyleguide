<?php

declare(strict_types=1);

namespace Netlogix\Nxstyleguide\ViewHelpers;

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper;

class XhrCacheViewHelper extends AbstractTagBasedViewHelper
{
    protected $tagName = 'script';

    public function __construct(
        protected ?PageRenderer $pageRenderer = null
    ) {
        parent::__construct();
        $this->pageRenderer ??= GeneralUtility::makeInstance(PageRenderer::class);
    }

    public function initializeArguments(): void
    {
        $this->registerArgument('url', 'string', '', false);
        $this->registerArgument('content', 'mixed', '', true, null);
    }

    public function render(): string
    {
        $body = <<<'JavaScript'
            window.TYPO3 = window.TYPO3 || {};
            (function(TYPO3){
                TYPO3.xhrCache = TYPO3.xhrCache || [];
                TYPO3.xhrCache.push({
                    url: "{url}",
                    data: "{data}"
                });
            })(window.TYPO3);
        JavaScript;

        $data = $this->hasArgument('content') ? $this->arguments['content'] : null;
        if ($data instanceof QueryResultInterface) {
            $data = $data->toArray();
        }

        $url = $this->hasArgument('url') ? \json_encode($this->arguments['url'], JSON_THROW_ON_ERROR) : 'window.location.href';
        $data = \json_encode($data, Environment::getContext()->isDevelopment() ? JSON_PRETTY_PRINT : 0);

        $replace = [
            '"{url}"' => $url,
            '"{data}"' => $data,
        ];

        $content = \str_replace(array_keys($replace), array_values($replace), $body);

        $this->pageRenderer->addInlineSettingArray('xhrCache', [
            [
                'data' => json_decode($data, true),
                'url' => json_decode($url, true),
            ],
        ]);

        $this->tag->setContent($content);

        return $this->tag->render();
    }
}
