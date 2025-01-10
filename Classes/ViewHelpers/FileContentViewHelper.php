<?php

declare(strict_types=1);

namespace Netlogix\Nxstyleguide\ViewHelpers;

use GuzzleHttp\Psr7\Uri;
use function mime_content_type;
use function preg_match;
use function preg_replace_callback;
use function sprintf;
use function str_replace;
use Throwable;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use function vsprintf;

class FileContentViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('file', 'string', '', true);
        $this->registerArgument('arguments', 'array', 'The arguments for vsprintf', false, []);
        $this->registerArgument('mimeType', 'string', 'Mime type of the external file', false, '');
        $this->registerArgument('dataUri', 'boolean', 'Base46 encode file as data uri', false, false);
        $this->registerArgument('baseUri', 'string', 'base uri to replace assets in css file', false, false);
    }

    public function render(): string
    {
        try {
            $file = $this->arguments['file'];
            $args = $this->arguments['arguments'];
            $dataUri = $this->arguments['dataUri'];
            $baseUri = $this->arguments['baseUri'];
            if (in_array(preg_match('/^(?:http|ftp)s?|s(?:ftp|cp):/', (string) $file), [0, false], true)) {
                $file = GeneralUtility::getFileAbsFileName(ltrim((string) $file, '/'));
                $mimeType = mime_content_type($file);
            } else {
                $mimeType = $this->arguments['mimeType'];
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
                        function ($matches): string {
                            $fileUri = PathUtility::getAbsoluteWebPath(GeneralUtility::getFileAbsFileName($this->arguments['file']));
                            $fileUri = (string) (new Uri('/' . rtrim($fileUri, '/')))
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
        } catch (Throwable $t) {
        }

        return trim((string) ($content ?? ''));
    }
}
