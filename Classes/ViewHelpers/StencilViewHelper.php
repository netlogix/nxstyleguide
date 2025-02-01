<?php

declare(strict_types=1);

namespace Netlogix\Nxstyleguide\ViewHelpers;

use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\Entity\Site;
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
        $this->registerArgument('resourcesUrl', 'string', '', true);
        $this->registerArgument('stencilNamespace', 'string', '', true);
        $this->registerArgument('components', 'array', 'Components to preload', false, []);
    }

    public function render(): string
    {
        $result = '';
        $namespace = trim((string) $this->arguments['stencilNamespace']);
        $resourcesUrl = rtrim(trim((string) $this->arguments['resourcesUrl']), '/') . '/';

        $javaScriptContent = (string) $this->getUrl(
            $this->getAbsoluteWebPath($resourcesUrl . $namespace . '.esm.js', true),
        );
        $assetUrl = rtrim(trim($this->getAbsoluteWebPath($resourcesUrl)), '/') . '/';

        $filesToPreload = [];
        $javaScriptContent = preg_replace_callback(
            '~(?<import>from|import)"(?<filePath>\.\/(?<fileName>[^"]+.js))"~m',
            static function (array $matches) use ($assetUrl, &$filesToPreload): string {
                $fileUri = sprintf('%1$s%2$s', $assetUrl, $matches['fileName']);
                $filesToPreload[] = $fileUri;

                return sprintf('%1$s"%2$s"', $matches['import'], $fileUri);
            },
            $javaScriptContent,
        );

        $javaScriptContent = preg_replace_callback(
            "~(?<import>from|import)\s'(?<filePath>\.\/(?<fileName>[^']+.js))'~m",
            static function (array $matches) use ($assetUrl, &$filesToPreload): string {
                $fileUri = sprintf('%1$s%2$s', $assetUrl, $matches['fileName']);
                $filesToPreload[] = $fileUri;

                return sprintf('%1$s"%2$s"', $matches['import'], $fileUri);
            },
            (string) $javaScriptContent,
        );

        $javaScriptContent = str_replace(
            'sourceMappingURL=',
            sprintf('sourceMappingURL=%s', $assetUrl),
            (string) $javaScriptContent,
        );

        $result .=
            implode(
                PHP_EOL,
                array_map(
                    fn($fileUri): string => sprintf(
                        '<link href="%s" rel="modulepreload" />',
                        $this->getAbsoluteWebPath($fileUri),
                    ),
                    array_unique($filesToPreload),
                ),
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
        $this->tag->addAttribute('src', $this->getAbsoluteWebPath($resourcesUrl . $namespace . '.js', true));
        $this->tag->forceClosingTag(true);

        return $result . ($this->tag->render() . PHP_EOL);
    }

    protected function getUrl(string $url): ?string
    {
        return GeneralUtility::getUrl($url) ?? null;
    }

    private function getAbsoluteWebPath(string $file, bool $cacheBreaker = false): string
    {
        if (PathUtility::hasProtocolAndScheme($file)) {
            return $file;
        }

        if (PathUtility::isExtensionPath($file)) {
            $file = Environment::getPublicPath() . '/' . PathUtility::getPublicResourceWebPath($file, false);
            // as the path is now absolute, make it "relative" to the current script to stay compatible
            $file = PathUtility::getRelativePathTo($file) ?? '';
            $file = rtrim($file, '/');
        } else {
            $file = GeneralUtility::resolveBackPath($file);
        }

        if ($cacheBreaker) {
            $file = GeneralUtility::createVersionNumberedFilename($file);
        }

        $file = PathUtility::getAbsoluteWebPath($file);

        $baseUri = $this->getBaseUri();

        return (string) (new Uri($file))->withScheme('https')->withHost($baseUri->getHost());
    }

    public function getBaseUri(): UriInterface
    {
        $request = $this->getRequest();
        $site = $request->getAttribute('site');
        assert($site instanceof Site);

        try {
            return new Uri($site->getAttribute('cdnBase'));
        } catch (InvalidArgumentException) {
            return $site->getBase();
        }
    }

    protected function getRequest(): ServerRequestInterface
    {
        return $GLOBALS['TYPO3_REQUEST'];
    }
}
