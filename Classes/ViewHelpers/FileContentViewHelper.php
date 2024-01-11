<?php

declare(strict_types=1);

namespace Netlogix\Nxstyleguide\ViewHelpers;

use Closure;
use GuzzleHttp\Psr7\Uri;
use function mime_content_type;
use function preg_match;
use function preg_replace_callback;
use function sprintf;
use function str_replace;
use Throwable;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithContentArgumentAndRenderStatic;
use function vsprintf;

class FileContentViewHelper extends AbstractViewHelper
{
    use CompileWithContentArgumentAndRenderStatic;

    /**
     * @var boolean
     */
    protected $escapeChildren = false;

    /**
     * @var boolean
     */
    protected $escapeOutput = false;

    /**
     * @return bool|string
     */
    public static function renderStatic(
        array $arguments,
        Closure $renderChildrenClosure,
        RenderingContextInterface $renderingContext
    ) {
        try {
            $file = $arguments['file'];
            $args = $arguments['arguments'];
            $dataUri = $arguments['dataUri'];
            $baseUri = $arguments['baseUri'];
            if (!preg_match('/^(?:http|ftp)s?|s(?:ftp|cp):/', (string) $file)) {
                $file = GeneralUtility::getFileAbsFileName(ltrim((string) $file, '/'));
                $mimeType = mime_content_type($file);
            } else {
                $mimeType = $arguments['mimeType'];
            }
            $content = (string) GeneralUtility::getUrl($file);

            switch ($mimeType) {
                case 'image/svg':
                case 'image/svg+xml':
                    $content = vsprintf($content, $args);

                    break;
                case 'text/plain':
                case 'text/css':
                    if ($baseUri) {
                        $content = str_replace('../', $baseUri, $content);
                    }
                    $content = preg_replace_callback(
                        '~sourceMappingURL=(?<fileName>[^"]+.css)\.map~m',
                        function ($matches) use ($arguments) {
                            $fileUri = GeneralUtility::getFileAbsFileName($arguments['file']);
                            $fileUri = (string) (new Uri('/' . rtrim(PathUtility::getRelativePathTo($fileUri), '/')))
                                ->withScheme('https')
                                ->withHost($_SERVER['CDN_BASE'] ?? $_SERVER['HTTP_HOST']);

                            return sprintf('sourceMappingURL=%1$s.map', $fileUri);
                        },
                        $content
                    );

                    break;
                default:
                    break;
            }

            if ($content && $dataUri) {
                $content = sprintf('data:%1$s;base64,%2$s', $mimeType, base64_encode($content));
            }

            return trim($content);
        } catch (Throwable) {
            return '';
        }
    }

    public function initializeArguments()
    {
        $this->registerArgument('file', 'string', '', true);
        $this->registerArgument('arguments', 'array', 'The arguments for vsprintf', false, []);
        $this->registerArgument('mimeType', 'string', 'Mime type of the external file', false, '');
        $this->registerArgument('dataUri', 'boolean', 'Base46 encode file as data uri', false, false);
        $this->registerArgument('baseUri', 'string', 'base uri to replace assets in css file', false, false);
    }
}
