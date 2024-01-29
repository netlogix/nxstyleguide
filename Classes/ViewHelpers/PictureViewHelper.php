<?php

declare(strict_types=1);

namespace Netlogix\Nxstyleguide\ViewHelpers;

use InvalidArgumentException;
use RuntimeException;
use TYPO3\CMS\Core\Imaging\ImageManipulation\CropVariantCollection;
use TYPO3\CMS\Core\Resource\Exception\ResourceDoesNotExistException;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Service\ImageService;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Exception;
use TYPO3Fluid\Fluid\Core\ViewHelper\TagBuilder;
use UnexpectedValueException;

/**
 * Renders an image as a <picture> tag with multiple image sizes.
 */
class PictureViewHelper extends AbstractTagBasedViewHelper
{
    /**
     * @var int
     */
    private const MAX_INLINE_IMAGE_SIZE = 50000; // 5KB

    /**
     * @var string
     */
    protected $tagName = 'picture';

    protected ImageService $imageService;

    public function __construct(?ImageService $imageService = null)
    {
        parent::__construct();
        $this->imageService = $imageService ?? GeneralUtility::makeInstance(ImageService::class);
    }

    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerUniversalTagAttributes();
        $this->registerArgument('path', 'string', 'File path', false, '');
        $this->registerArgument('pageData', 'array', 'Page data', false, []);
        $this->registerArgument('image', 'object', 'a FAL object');
        $this->registerArgument('imageTitle', 'string', 'Title of the image tag');
        $this->registerArgument('src', 'array', 'Src configuration', false, [
            'cropVariant' => 'desktop',
            'width' => 1120,
        ]);
        $this->registerArgument('srcset', 'array', 'Srcset configuration', true);
        $this->registerArgument('lazy', 'boolean', 'Use lazy loading markup', false, true);
        $this->registerArgument('fileExtension', 'string', 'Custom file extension to use');
        $this->registerArgument('aspectRatio', 'float', 'Custom aspect ratio to use');
    }

    public function render(): string
    {
        if (
            ($this->arguments['path'] === '' && $this->arguments['image'] === null && $this->arguments['pageData'] === []) ||
            ($this->arguments['path'] !== '' && $this->arguments['image'] !== null && $this->arguments['pageData'] !== [])
        ) {
            throw new Exception('You must either specify a string path, a File object or page data.', 1586532065);
        }

        try {
            if ($this->arguments['pageData'] === []) {
                $image = $this->imageService->getImage(
                    (string) $this->arguments['path'],
                    $this->arguments['image'],
                    false
                );
            } else {
                $image = $this->getImageByPageData($this->arguments['pageData']);
                if (!$image instanceof FileReference) {
                    return '';
                }
            }

            $this->tag->addAttribute('class', $this->arguments['class'] ?? '');

            if (!empty($this->arguments['aspectRatio'])) {
                $this->tag->addAttribute('class', ($this->arguments['class'] ?? '') . ' ratio');
            }

            if ($this->isSvg($image)) {
                $width = $image->getProperty('width');
                $aspectRatio = $this->getAspectRatio($image);
                $srcWidth = $this->arguments['src']['width'] ?? $this->arguments['src']['maxWidth'] ?? $width;
                if ($width > $srcWidth) {
                    $width = $srcWidth;
                }
            } else {
                $processedImage = $this->getProcessedImage($image, $this->arguments['src']);
                $width = $processedImage->getProperty('width');
                $aspectRatio = $this->arguments['aspectRatio'] ?? $this->getAspectRatio($processedImage);
            }

            $this->tag->addAttribute(
                'style',
                sprintf(
                    '--aspect-ratio: %1$s;--width: %2$spx; %3$s',
                    $aspectRatio . '%',
                    $width,
                    $this->arguments['style'] ?? ''
                )
            );
            $this->tag->setContent(
                implode(PHP_EOL, [$this->getSourceSets($image), $this->getImgTag($image), $this->renderChildren()])
            );

            return $this->tag->render();
        } catch (ResourceDoesNotExistException) {
            // thrown if file does not exist
        } catch (UnexpectedValueException) {
            // thrown if a file has been replaced with a folder
        } catch (RuntimeException) {
            // RuntimeException thrown if a file is outside a storage
        } catch (InvalidArgumentException) {
            // thrown if file storage does not exist
        }

        return '';
    }

    private function getImageByPageData(array $data): ?FileReference
    {
        $fileRepository = GeneralUtility::makeInstance(FileRepository::class);
        $fileObjects = $fileRepository->findByRelation('pages', 'media', $data['uid']);

        return $fileObjects[0] ?? null;
    }

    private function getSourceSets(FileInterface $image): string
    {
        if ($this->isSvg($image)) {
            return $this->renderSourceTag($image, 1);
        }

        $tags = [];
        foreach ((array) $this->arguments['srcset'] as $minWidth => $imageConfiguration) {
            $processedImage = $this->getProcessedImage($image, $imageConfiguration);
            $tags[(int) $minWidth] = $this->renderSourceTag($processedImage, $minWidth);
        }

        krsort($tags);

        return implode(PHP_EOL, $tags);
    }

    private function getImgTag(FileInterface $image): string
    {
        $tag = new TagBuilder('img');
        $processedImage = $this->getProcessedImage($image, $this->arguments['src']);
        $processedImageUri = $this->imageService->getImageUri($processedImage);

        $width = $processedImage->getProperty('width');
        $height = $processedImage->getProperty('height');

        $tag->addAttribute('width', $width);
        $tag->addAttribute('height', $height);
        $tag->addAttribute('class', 'img-fluid');
        $tag->addAttribute('itemprop', 'image');
        $tag->addAttribute('alt', $this->getImageAlt($image));
        $tag->addAttribute('title', $this->getImageTitle($image));
        $tag->addAttribute('src', $processedImageUri);

        if ($this->arguments['lazy']) {
            $tag->addAttribute('loading', 'lazy');
            $tag->addAttribute('decoding', 'async');
            $tag->addAttribute('fetchpriority', 'low');
        } else {
            if ($this->supportWebpConversion($image)) {
                $processedInline = $this->getProcessedImage($image, [
                    'width' => 10,
                ]);
                $placeholder = $this->getBase64EncodedImageContents($processedInline);
                $tag->addAttribute('onload', "this.removeAttribute('style')");
                $tag->addAttribute(
                    'style',
                    implode(';', ['background-size: cover', sprintf("background-image: url('%s')", $placeholder)])
                );
            }

            $tag->addAttribute('fetchpriority', 'high');
        }

        return $tag->render();
    }

    private function getProcessedImage(FileInterface $image, array $processingInstructions): ProcessedFile
    {
        if ($image->hasProperty('crop') && $image->getProperty('crop')) {
            $cropString = $image->getProperty('crop');
            $cropVariantCollection = CropVariantCollection::create($cropString);
            $cropVariant = $processingInstructions['cropVariant'] ?? $this->arguments['cropVariant'] ?? 'default';
            $cropArea = $cropVariantCollection->getCropArea($cropVariant);
            $processingInstructions['crop'] = $cropArea->isEmpty() ? null : $cropArea->makeAbsoluteBasedOnFile($image);
        }

        if (!empty($this->arguments['fileExtension'] ?? '')) {
            $processingInstructions['fileExtension'] = $this->arguments['fileExtension'];
        }

        return $this->imageService->applyProcessingInstructions($image, $processingInstructions);
    }

    private function renderSourceTag(FileInterface $image, int $minWidth): string
    {
        $tag = new TagBuilder('source');
        $tag->addAttribute('media', sprintf('(min-width: %dpx)', $minWidth));
        $tag->addAttribute('srcset', $this->imageService->getImageUri($image));

        return $tag->render();
    }

    private function getImageAlt(FileInterface $image): string
    {
        if ($this->arguments['additionalAttributes'] && array_key_exists(
            'alt',
            $this->arguments['additionalAttributes']
        )) {
            return $this->arguments['additionalAttributes']['alt'];
        }

        return $image->getProperty('alternative') ?: $this->getImageTitle($image);
    }

    private function getImageTitle(FileInterface $image): string
    {
        return $this->arguments['imageTitle'] ?? $this->arguments['title'] ?? $image->getProperty('title');
    }

    private function isSvg(FileInterface $image): bool
    {
        return match ($image->getMimeType()) {
            'image/svg', 'image/svg+xml' => true,
            default => false,
        };
    }

    private function supportWebpConversion(FileInterface $image): bool
    {
        return match ($image->getMimeType()) {
            'image/png', 'image/jpeg' => true,
            default => false,
        };
    }

    private function getBase64EncodedImageContents(ProcessedFile $image): string
    {
        if ($image->getSize() > self::MAX_INLINE_IMAGE_SIZE) {
            return $this->imageService->getImageUri($image);
        }

        $thumbnailContent = base64_encode((string) $image->getContents());
        if ($this->isSvg($image)) {
            return 'data:image/svg+xml;base64,' . $thumbnailContent;
        }

        return 'data:image/' . $image->getType() . ';base64,' . $thumbnailContent;
    }

    private function getAspectRatio(FileInterface $image): float
    {
        $width = (int) $image->getProperty('width');
        $height = (int) $image->getProperty('height');

        if ($width === 0 || $height === 0) {
            return 1.00;
        }

        return round($image->getProperty('width') / $image->getProperty('height'), 2);
    }
}
