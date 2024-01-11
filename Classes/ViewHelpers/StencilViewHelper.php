<?php

declare(strict_types=1);

namespace Netlogix\Nxstyleguide\ViewHelpers;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper;

/**
 * Inserts stencil script inline and add simple preloading for next request
 */
class StencilViewHelper extends AbstractTagBasedViewHelper
{
    /**
     * @var string
     */
    protected $tagName = 'script';

    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerUniversalTagAttributes();
        $this->registerArgument('resourcesUrl', 'string', '', true);
        $this->registerArgument('stencilNamespace', 'string', '', true);
        $this->registerArgument('components', 'array', 'Components to preload', false, []);
    }

    public function render(): string
    {
        $result = '';
        $namespace = trim((string) $this->arguments['stencilNamespace']);
        $resourcesUrl = rtrim(trim((string) $this->arguments['resourcesUrl']), '/') . '/';

        $javaScriptContent = (string) GeneralUtility::getUrl(
            GeneralUtility::getFileAbsFileName($resourcesUrl . $namespace . '.esm.js')
        );
        $assetUrl = PathUtility::getAbsoluteWebPath(GeneralUtility::getFileAbsFileName($resourcesUrl));

        $filesToPreload = [];
        $javaScriptContent = preg_replace_callback(
            '~(?<import>from|import)"(?<filePath>\.\/(?<fileName>[^"]+.js))"~m',
            function ($matches) use ($assetUrl, &$filesToPreload) {
                $fileUri = sprintf('%1$s%2$s', $assetUrl, $matches['fileName']);
                $filesToPreload[] = $fileUri;

                return sprintf('%1$s"%2$s"', $matches['import'], $fileUri);
            },
            $javaScriptContent
        );

        $javaScriptContent = preg_replace_callback(
            "~(?<import>from|import)\s'(?<filePath>\.\/(?<fileName>[^']+.js))'~m",
            function ($matches) use ($assetUrl, &$filesToPreload) {
                $fileUri = sprintf('%1$s%2$s', $assetUrl, $matches['fileName']);
                $filesToPreload[] = $fileUri;

                return sprintf('%1$s"%2$s"', $matches['import'], $fileUri);
            },
            $javaScriptContent
        );

        $result .= implode(
            PHP_EOL,
            array_map(
                fn ($fileUri) => "<link href=\"$fileUri\" rel=\"modulepreload\" />",
                array_unique($filesToPreload)
            )
        ) . PHP_EOL;

        $this->tag->addAttribute('type', 'module');
        $this->tag->addAttribute('data-resources-url', $assetUrl);
        $this->tag->addAttribute('data-stencil-namespace', $namespace);
        $this->tag->setContent($javaScriptContent);
        $this->tag->forceClosingTag(true);
        $result .= $this->tag->render() . PHP_EOL;

        $this->tag->reset();
        $this->tag->setTagName($this->tagName);
        $this->tag->addAttribute('nomodule', '');
        $this->tag->addAttribute('src', $this->getAbsoluteWebPath($resourcesUrl . $namespace . '.js'));
        $this->tag->forceClosingTag(true);
        $result .= $this->tag->render() . PHP_EOL;

        return $result;
    }

    private function getAbsoluteWebPath(string $file): string
    {
        // @codeCoverageIgnoreStart
        if (PathUtility::hasProtocolAndScheme($file)) {
            return $file;
        }
        // @codeCoverageIgnoreEnd

        $file = PathUtility::getAbsoluteWebPath(GeneralUtility::getFileAbsFileName($file));

        return GeneralUtility::createVersionNumberedFilename($file);
    }
}
