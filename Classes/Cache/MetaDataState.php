<?php

declare(strict_types=1);

namespace Netlogix\Nxstyleguide\Cache;

use ReflectionProperty;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

readonly class MetaDataState extends \TYPO3\CMS\Frontend\Cache\MetaDataState
{

    public function getState(): array
    {
        $state = parent::getState();
        $state['PageRenderer::$inlineLanguageLabels'] = $this->getInlineLanguageLabels();
        $state['PageRenderer::$inlineSettings'] = $this->getInlineSettings();

        return $state;
    }

    public function updateState(array $state): void
    {
        parent::updateState($state);
        foreach ($state as $name => $value) {
            switch ($name) {
                case 'PageRenderer::$inlineLanguageLabels':
                    $this->setInlineLanguageLabels($value);
                    break;
                case 'PageRenderer::$inlineSettings':
                    $this->setInlineSettings($value);
                    break;
            }
        }
    }

    private function getInlineLanguageLabels(): array
    {
        return $this->getProperty('inlineLanguageLabels');
    }

    private function setInlineLanguageLabels(array $labels): void
    {
        $this->setProperty('inlineLanguageLabels', $labels);
    }

    private function getInlineSettings(): array
    {
        return $this->getProperty('inlineSettings');
    }

    private function setInlineSettings(array $settings): void
    {
        $this->setProperty('inlineSettings', $settings);
    }

    private function getProperty(string $propertyName): mixed
    {
        $subject = GeneralUtility::makeInstance(PageRenderer::class);
        $property = new ReflectionProperty($subject, $propertyName);
        $property->setAccessible(true);
        return $property->getValue($subject);
    }

    private function setProperty(string $propertyName, mixed $value): void
    {
        $subject = GeneralUtility::makeInstance(PageRenderer::class);
        $property = new ReflectionProperty($subject, $propertyName);
        $property->setAccessible(true);
        $property->setValue($subject, $value);
    }
}
