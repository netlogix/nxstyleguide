<?php

declare(strict_types=1);

namespace Netlogix\Nxstyleguide\Tests\Functional\ViewHelpers;

use Override;
use DOMDocument;
use LogicException;
use Netlogix\Nxstyleguide\ViewHelpers\StencilViewHelper;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class StencilViewHelperTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/nxstyleguide/Tests/Functional/Fixtures/Extensions/nxwebsite',
    ];

    #[Override]
    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);
        parent::tearDown();
    }

    #[Test]
    public function initializeArguments_should_registerArguments_and_registerUniversalTagAttributes(): void
    {
        $subject = $this->getAccessibleMock(StencilViewHelper::class, [
            'registerArgument',
            'registerUniversalTagAttributes',
        ]);

        $subject
            ->expects($this->exactly(6))
            ->method('registerArgument')
            ->willReturnCallback(
                static fn($name, $type, $description, $required, $defaultValue): null => match (true) {
                    $name === 'resourcesUrl' && $type === 'string' && $description === '' && $required === true
                        => null,
                    $name === 'stencilNamespace' && $type === 'string' && $description === '' && $required === true
                        => null,
                    $name === 'components' &&
                        $type === 'array' &&
                        $description === 'Components to preload' &&
                        $required === false &&
                        $defaultValue === []
                        => null,
                    // default attributes
                    in_array($name, ['additionalAttributes', 'data', 'aria'], true) => null,
                    default => throw new LogicException($name),
                },
            );

        $subject->initializeArguments();
    }

    #[Test]
    public function render_should_return_script_elements_for_stencil_with_cdn_base(): void
    {
        $serverRequest = $this->createMock(ServerRequestInterface::class);

        $site = new Site('example', 1, [
            'cdnBase' => 'https://cdn.example.com',
        ]);

        $serverRequest->expects($this->atLeastOnce())->method('getAttribute')->willReturnCallback(
            static fn($key): Site|int => match ($key) {
                'applicationType' => SystemEnvironmentBuilder::REQUESTTYPE_FE,
                'site' => $site,
            },
        );

        $GLOBALS['TYPO3_REQUEST'] = $serverRequest;

        $subject = $this->getAccessibleMock(StencilViewHelper::class, ['getUrl']);

        $subject
            ->expects($this->once())
            ->method('getUrl')
            ->willReturnCallback(static function ($url): string|false|null {
                if (str_contains($url, 'Build/Scripts/styleguide.esm.js')) {
                    return GeneralUtility::getUrl(
                        GeneralUtility::getFileAbsFileName(
                            'EXT:nxwebsite/Resources/Public/Build/Scripts/styleguide.esm.js',
                        ),
                    );
                }

                return null;
            });

        $subject->setArguments([
            'resourcesUrl' => 'EXT:nxwebsite/Resources/Public/Build/Scripts/',
            'stencilNamespace' => 'styleguide',
        ]);

        $subject->initializeArguments();

        $result = $subject->render();

        $doc = new DOMDocument();
        $doc->loadHTML($result);

        $scriptTags = $doc->getElementsByTagName('script');
        $this->assertCount(2, $scriptTags);

        $this->assertEquals('module', $scriptTags->item(0)->getAttribute('type'));
        $this->assertEquals(
            'https://cdn.example.com/typo3conf/ext/nxwebsite/Resources/Public/Build/Scripts/',
            $scriptTags->item(0)->getAttribute('data-resources-url'),
        );
        $this->assertEquals('styleguide', $scriptTags->item(0)->getAttribute('data-stencil-namespace'));

        $this->assertEquals('', $scriptTags->item(1)->getAttribute('nomodule'));
        $this->assertEquals(
            'https://cdn.example.com/typo3conf/ext/nxwebsite/Resources/Public/Build/Scripts/styleguide.js',
            $scriptTags->item(1)->getAttribute('src'),
        );

        $this->assertStringContainsString(
            'export{hello as hello1}from"https://cdn.example.com/typo3conf/ext/nxwebsite/Resources/Public/Build/Scripts/hello.js"',
            $result,
        );
        $this->assertStringContainsString(
            'import{world as world1}from"https://cdn.example.com/typo3conf/ext/nxwebsite/Resources/Public/Build/Scripts/world.js"',
            $result,
        );
        $this->assertStringContainsString(
            'import"https://cdn.example.com/typo3conf/ext/nxwebsite/Resources/Public/Build/Scripts/foo.js"',
            $result,
        );

        $linkTags = $doc->getElementsByTagName('link');
        $this->assertCount(3, $linkTags);

        $this->assertEquals(
            'https://cdn.example.com/typo3conf/ext/nxwebsite/Resources/Public/Build/Scripts/hello.js',
            $linkTags->item(0)->getAttribute('href'),
        );
        $this->assertEquals(
            'https://cdn.example.com/typo3conf/ext/nxwebsite/Resources/Public/Build/Scripts/world.js',
            $linkTags->item(1)->getAttribute('href'),
        );
        $this->assertEquals(
            'https://cdn.example.com/typo3conf/ext/nxwebsite/Resources/Public/Build/Scripts/foo.js',
            $linkTags->item(2)->getAttribute('href'),
        );
    }

    #[Test]
    public function render_should_return_script_elements_for_stencil(): void
    {
        $serverRequest = $this->createMock(ServerRequestInterface::class);

        $site = new Site('example', 1, [
            'base' => 'https://www.example.com',
        ]);

        $serverRequest->expects($this->atLeastOnce())->method('getAttribute')->willReturnCallback(
            static fn($key): Site|int => match ($key) {
                'applicationType' => SystemEnvironmentBuilder::REQUESTTYPE_FE,
                'site' => $site,
            },
        );

        $GLOBALS['TYPO3_REQUEST'] = $serverRequest;

        $subject = $this->getAccessibleMock(StencilViewHelper::class, ['getUrl']);

        $subject
            ->expects($this->once())
            ->method('getUrl')
            ->willReturnCallback(static function ($url): string|false|null {
                if (str_contains($url, 'Build/Scripts/styleguide.esm.js')) {
                    return GeneralUtility::getUrl(
                        GeneralUtility::getFileAbsFileName(
                            'EXT:nxwebsite/Resources/Public/Build/Scripts/styleguide.esm.js',
                        ),
                    );
                }

                return null;
            });

        $subject->setArguments([
            'resourcesUrl' => 'EXT:nxwebsite/Resources/Public/Build/Scripts/',
            'stencilNamespace' => 'styleguide',
        ]);

        $subject->initializeArguments();

        $result = $subject->render();

        $doc = new DOMDocument();
        $doc->loadHTML($result);

        $scriptTags = $doc->getElementsByTagName('script');
        $this->assertCount(2, $scriptTags);

        $this->assertEquals('module', $scriptTags->item(0)->getAttribute('type'));
        $this->assertEquals(
            'https://www.example.com/typo3conf/ext/nxwebsite/Resources/Public/Build/Scripts/',
            $scriptTags->item(0)->getAttribute('data-resources-url'),
        );
        $this->assertEquals('styleguide', $scriptTags->item(0)->getAttribute('data-stencil-namespace'));

        $this->assertEquals('', $scriptTags->item(1)->getAttribute('nomodule'));
        $this->assertEquals(
            'https://www.example.com/typo3conf/ext/nxwebsite/Resources/Public/Build/Scripts/styleguide.js',
            $scriptTags->item(1)->getAttribute('src'),
        );

        $this->assertStringContainsString(
            'export{hello as hello1}from"https://www.example.com/typo3conf/ext/nxwebsite/Resources/Public/Build/Scripts/hello.js"',
            $result,
        );
        $this->assertStringContainsString(
            'import{world as world1}from"https://www.example.com/typo3conf/ext/nxwebsite/Resources/Public/Build/Scripts/world.js"',
            $result,
        );
        $this->assertStringContainsString(
            'import"https://www.example.com/typo3conf/ext/nxwebsite/Resources/Public/Build/Scripts/foo.js"',
            $result,
        );

        $linkTags = $doc->getElementsByTagName('link');
        $this->assertCount(3, $linkTags);

        $this->assertEquals(
            'https://www.example.com/typo3conf/ext/nxwebsite/Resources/Public/Build/Scripts/hello.js',
            $linkTags->item(0)->getAttribute('href'),
        );
        $this->assertEquals(
            'https://www.example.com/typo3conf/ext/nxwebsite/Resources/Public/Build/Scripts/world.js',
            $linkTags->item(1)->getAttribute('href'),
        );
        $this->assertEquals(
            'https://www.example.com/typo3conf/ext/nxwebsite/Resources/Public/Build/Scripts/foo.js',
            $linkTags->item(2)->getAttribute('href'),
        );
    }
}
