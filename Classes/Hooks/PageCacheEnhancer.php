<?php

declare(strict_types=1);

namespace Netlogix\Nxstyleguide\Hooks;

use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Reflection\ObjectAccess;

class PageCacheEnhancer
{
    public function insertPageCacheContent(array $params): void
    {
        $params['cache_data']['inlineLanguageLabels'] = $this->getLabels();
        $params['cache_data']['inlineSettings'] = $this->getSettings();
    }

    public function pageLoadedFromCache(array $params): void
    {
        $this->setLabels($params['cache_pages_row']['inlineLanguageLabels'] ?? []);
        $this->setSettings($params['cache_pages_row']['inlineSettings'] ?? []);
    }

    private function getLabels(): array
    {
        $pageRender = GeneralUtility::makeInstance(PageRenderer::class);

        return ObjectAccess::getProperty($pageRender, 'inlineLanguageLabels');
    }

    private function setLabels(array $labels): void
    {
        $pageRender = GeneralUtility::makeInstance(PageRenderer::class);
        ObjectAccess::setProperty($pageRender, 'inlineLanguageLabels', $labels);
    }

    private function getSettings(): array
    {
        $pageRender = GeneralUtility::makeInstance(PageRenderer::class);

        return ObjectAccess::getProperty($pageRender, 'inlineSettings');
    }

    private function setSettings(array $settings): void
    {
        $pageRender = GeneralUtility::makeInstance(PageRenderer::class);
        ObjectAccess::setProperty($pageRender, 'inlineSettings', $settings);
    }
}
