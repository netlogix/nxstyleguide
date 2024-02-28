<?php

declare(strict_types=1);

namespace Netlogix\Nxstyleguide\Tests\Functional\ViewHelpers;

use LogicException;
use Netlogix\Nxstyleguide\ViewHelpers\FileContentViewHelper;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

class FileContentViewHelperTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/nxstyleguide/Tests/Functional/Fixtures/Extensions/nxwebsite',
    ];

    #[Test]
    public function initializeArguments_should_registerArguments(): void
    {
        $subject = $this->getAccessibleMock(FileContentViewHelper::class, ['registerArgument']);

        $subject->expects($this->exactly(5))
            ->method('registerArgument')
            ->willReturnCallback(static fn ($name, $type, $description, $required, $defaultValue) => match (true) {
                $name === 'file' && $type === 'string' && $description === '' && $required === true => null,
                $name === 'arguments' && $type === 'array' && $description === 'The arguments for vsprintf' && $required === false && $defaultValue === [] => null,
                $name === 'mimeType' && $type === 'string' && $description === 'Mime type of the external file' && $required === false && $defaultValue === '' => null,
                $name === 'dataUri' && $type === 'boolean' && $description === 'Base46 encode file as data uri' && $required === false && $defaultValue === false => null,
                $name === 'baseUri' && $type === 'string' && $description === 'base uri to replace assets in css file' && $required === false && $defaultValue === false => null,
                // default attributes
                in_array($name, ['additionalAttributes', 'data', 'aria'], true) => null,
                default => throw new LogicException($name)
            });

        $subject->initializeArguments();
    }

    #[Test]
    public function renderStatic_should_return_svg(): void
    {
        $subject = new FileContentViewHelper();
        $subject->initializeArguments();

        $result = $subject->renderStatic([
            'file' => 'EXT:nxwebsite/Resources/Public/Icons/styleguide.svg',
            'baseUri' => '',
            'arguments' => [],
            'dataUri' => false,
        ], static fn (): string => '', $this->getMockBuilder(RenderingContextInterface::class)->getMock());

        self::assertEquals(
            '<svg xmlns="http://www.w3.org/2000/svg" height="100" width="100"><circle cx="50" cy="50" r="40" fill="red"/></svg>',
            $result
        );
    }

    #[Test]
    public function renderStatic_should_return_svg_with_placeholder_replaced(): void
    {
        $subject = new FileContentViewHelper();
        $subject->initializeArguments();

        $result = $subject->renderStatic([
            'file' => 'EXT:nxwebsite/Resources/Public/Icons/styleguide-placeholder.svg',
            'baseUri' => '',
            'arguments' => ['blue'],
            'dataUri' => false,
        ], static fn (): string => '', $this->getMockBuilder(RenderingContextInterface::class)->getMock());

        self::assertEquals(
            '<svg xmlns="http://www.w3.org/2000/svg" height="100" width="100"><circle cx="50" cy="50" r="40" fill="blue"/></svg>',
            $result
        );
    }

    #[Test]
    public function renderStatic_should_return_css_with_base_and_correct_source_map(): void
    {
        $subject = new FileContentViewHelper();
        $subject->initializeArguments();

        $_SERVER['CDN_BASE'] = 'cdn.example.com';

        $result = $subject->renderStatic([
            'file' => 'EXT:nxwebsite/Resources/Public/Build/Css/styleguide.css',
            'baseUri' => '/styleguide/',
            'arguments' => [],
            'dataUri' => false,
        ], static fn (): string => '', $this->getMockBuilder(RenderingContextInterface::class)->getMock());

        self::assertStringContainsString('/styleguide/test.jpg', $result);
        self::assertStringContainsString(
            'sourceMappingURL=https://cdn.example.com/../../../../typo3conf/ext/nxwebsite/Resources/Public/Build/Css/styleguide.css.map',
            $result
        );
    }

    #[Test]
    public function renderStatic_should_return_png_as_base64_data_uri(): void
    {
        $subject = new FileContentViewHelper();
        $subject->initializeArguments();

        $result = $subject->renderStatic([
            'file' => 'EXT:nxwebsite/Resources/Public/Icons/icon.png',
            'baseUri' => '',
            'arguments' => [],
            'dataUri' => true,
        ], static fn (): string => '', $this->getMockBuilder(RenderingContextInterface::class)->getMock());

        self::assertEquals(
            'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADkAAAA5CAYAAACMGIOFAAAABGdBTUEAALGPC/xhBQAAACBjSFJNAAB6JgAAgIQAAPoAAACA6AAAdTAAAOpgAAA6mAAAF3CculE8AAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAC4jAAAuIwF4pT92AAAAB3RJTUUH5QoHDh0UshKV4gAABlFJREFUaN7N2m+MXFUZx/HPndnZ3drt/0Va2mKjVMrWKtSWNqAosRax1mgrFGk1lkSpKFoUTYzRqkmjRon4BsoLSdAYIWqjxhfE+IYYkyoNMTFRKy8K0QpiWaptgba7O754zpTbYWc7/+5Of8lmNrtzzz3fe57z/Ds300Ptfnhx7ddBXIkb8AHMx1YcvPeWIx3fJ+sx3By8DR/GdbgE5fS/+3AXTncKOu2QCXAQ12NX+pw1yVf/g5vwGHQC2jfNcPAG3I2bhVk20kXYiQM41cm9S9MI2If34se4/TyANd0g9mpHKhwyAb4Gn8aDWKf5bbIQm3PjXHiQaWJzsQd7cXEbw2xMsBceZAKch29gt1jNdrQCb7rgIBPgEL4i9l9/B8PNEuGlbZPtOmSayAA+K0JEJ4A1XYUZ7V7cVcjck96OL3YysTotx6KeQ+YA1+BLmN2tsYXjWdJzyKTZ+AIu6/K4M/Fa2tuXXYHM3fiDUlzrssqaSx6Kg0y6CJ/QvX2YV6YD8+8YMreK79SFFGwKVdq9sFsJ+hB2eHXAn8BpvJw+T6e/lUVoGRAVSUWBFVFHkHc8spTqBLxV1IVwFIfwJP6SPp/BSZzBeLpvvwj0i3FF+nmjcFqTlV5jPYG8+tgJVx07Wf7hpcPrxrPsz/gF/pAgj4lVm7IWzJl7n/CgIyLD2STiYw34eLvzbMtERvdkhMmNTGQ2PT5/5oGHlyx4Yt7pseOodlLgJugSFggL2Y4NWVa9c6Ka/ezebUdkLc66pa8nOFiG2/BRUcFvxrPzv15tG64R8NDAsYGnnl+14Y9P3bjo7/+6Zn+l//gonNm3pfuQCXBQNJruFvlkCQ/gUxjvNiSUb/8l1ezNpdL4z6n+Dd8S3YLxZkHPG0JG92Q1wMXpBg8IM6pde6gowMqu/UrZuFJp7PVUl+J9+AnuwFBl1/7OIXPmOYIf4E7nBuWTwnsWrStFuIGl+LZ44Isqu/Y7H2xDyBzgauwT/Zb67/8P/ygYcLZUT+Y0Q5Rx39NEdTIpZB3g/Xh7g+tP4IWCIVcIS6pXGR/Cd7BwqtV8FWQOcAW+j6unmMDLeKkIstykN2rcGyqLxvRezGsE2shcL8Y3vZLFNNKYyGCK0kLhbKZSCR8RHn9gMtBzINMqzhA1YTMlU/ddqnNW8UbNJf0VEcZuRVYPehYyZ6a3iE1dbmLwsuI6fpfg417xqufTHHwV6+seVEywbh9+XlTizWiwhUk0pdzktmFti5cvE72lufk/5lehgk9iZQuDzqwfsEtaLlaxnQLiPaJDcfaB5SHXiUOYVjRbB120eqVJDYj9taLNYQZFh+LsvErJVCui6G21HT8knnq3AIkH/TGdFdGr8f7auLWVHBH1W6sqSZ253L7uBPBa0XWf0+Ez6xd7ekFtkkQsavfYaLU48+gUcCW+q0uWIZzW2hrkMDZo3zxGhFfrBPBykV2t7xIgsZU2ISuJsmlVB4MtkBLoVkw2AWYi2N+Pd3URsKb1WFJKE2zb3ERCsFkLoSQB9mGLqA+vLwCQsJA1faJf2mnWslY8tUdH92QaFdA58xwWMfkz6feiNITVJa0F/0aaLaqBhtlPAizjGjwkvGiRgMR2WNeHP4mTqGZTuUZ6d3pgT0wCVxI91R2i+bW0YLi8VmSje7JhsSe2izi1QPue9j7cNaF0etG/f0pUNCPi7aqtIqZOyxsnOb2U5TziTNGB2yj26RXCmbSSPz73zMTwbSuf+9HhSvbitSI0XSeaxtMNV1P1nBVLwJk4JluGtwgX/zpRSM8Vq9MviuVTopn1PJ7tU/3r3hM7Dt9zcuvnMtVVmivXCteUZpmDrp1bzEqQAznIE6KF/+L8p4+OVQYf68fXROE9bW98tQ3ZjpKjGRb786ZeA1LcPjmKL+O3vQakoDPBXNBfLlZ0Qw8Zq4WsZO6M4knR0v+1gppeTehkYd5v4uAjymu2wSh+J/LjEdPvjP45LS/1JvOdI15B262LLZMm9KtpCdDJfP+Le0Rr4/eKbUrXNIED0/p6ds4hLRZd752KTfVewK09edG+Lmm/WeS1l+tuD3ccv8HOnkBOArsY7xDtitW4NAG3Or9TOCIqq0cT5NM9hayDlcAuS6DrxYnaMuG0JntZaUzs9cM4iMdFqXdIOm07s2+L/wMx2V/JWzfCAQAAACV0RVh0ZGF0ZTpjcmVhdGUAMjAyMS0xMC0wN1QxNDoyOToxOSswMDowMCPXnacAAAAldEVYdGRhdGU6bW9kaWZ5ADIwMjEtMTAtMDdUMTQ6Mjk6MTkrMDA6MDBSiiUbAAAAIHRFWHRzb2Z0d2FyZQBodHRwczovL2ltYWdlbWFnaWNrLm9yZ7zPHZ0AAAAYdEVYdFRodW1iOjpEb2N1bWVudDo6UGFnZXMAMaf/uy8AAAAYdEVYdFRodW1iOjpJbWFnZTo6SGVpZ2h0ADE5MkBdcVUAAAAXdEVYdFRodW1iOjpJbWFnZTo6V2lkdGgAMTky06whCAAAABl0RVh0VGh1bWI6Ok1pbWV0eXBlAGltYWdlL3BuZz+yVk4AAAAXdEVYdFRodW1iOjpNVGltZQAxNjMzNjE2OTU5eECAAgAAAA90RVh0VGh1bWI6OlNpemUAMEJClKI+7AAAAFZ0RVh0VGh1bWI6OlVSSQBmaWxlOi8vL21udGxvZy9mYXZpY29ucy8yMDIxLTEwLTA3LzYwODU5NmRiZTA1OGNlZTExZGYxZjJiZjNmM2JmNjM4Lmljby5wbmcaq8FIAAAAAElFTkSuQmCC',
            $result
        );
    }

    #[Test]
    public function renderStatic_should_return_external_png_as_base64_data_uri(): void
    {
        $subject = new FileContentViewHelper();
        $subject->initializeArguments();

        $result = $subject->renderStatic([
            'file' => 'https://via.placeholder.com/1x1',
            'baseUri' => '',
            'mimeType' => 'image/png',
            'arguments' => [],
            'dataUri' => true,
        ], static fn (): string => '', $this->getMockBuilder(RenderingContextInterface::class)->getMock());

        self::assertEquals(
            'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGM4c+YMAATMAmU5mmUsAAAAAElFTkSuQmCC',
            $result
        );
    }

    #[Test]
    public function renderStatic_should_empty_string(): void
    {
        $subject = new FileContentViewHelper();
        $subject->initializeArguments();

        $result = $subject->renderStatic([
            'file' => null,
            'baseUri' => '',
            'arguments' => [],
            'dataUri' => false,
        ], static fn (): string => '', $this->getMockBuilder(RenderingContextInterface::class)->getMock());

        self::assertEquals('', $result);
    }
}
