<?php

declare(strict_types=1);

namespace Netlogix\Nxstyleguide\Tests\Unit\ViewHelpers;

use LogicException;
use Netlogix\Nxstyleguide\ViewHelpers\XhrCacheViewHelper;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class XhrCacheViewHelperTest extends UnitTestCase
{
    #[Test]
    public function initializeArguments_should_registerArguments(): void
    {
        $subject = $this->getAccessibleMock(XhrCacheViewHelper::class, ['registerArgument']);
        $subject->expects($this->exactly(2))
            ->method('registerArgument')
            ->willReturnCallback(static fn ($name, $type, $description, $required) => match (true) {
                $name === 'url' && $type === 'string' && $description === '' && $required === false => null,
                $name === 'content' && $type === 'mixed' && $description === '' && $required === true => null,
                default => throw new LogicException()
            });

        $subject->initializeArguments();
    }

    #[Test]
    public function should_set_window_location_href_when_no_url_given(): void
    {
        $subject = new XhrCacheViewHelper();
        $result = $subject->initializeArgumentsAndRender();

        $this->assertStringContainsString('url: window.location.href', $result);
    }

    #[Test]
    public function should_set_data_null_when_no_data_given(): void
    {
        $subject = new XhrCacheViewHelper();
        $result = $subject->initializeArgumentsAndRender();

        $this->assertStringContainsString('data: null', $result);
    }

    #[Test]
    public function should_set_data_when_data_is_given(): void
    {
        $subject = new XhrCacheViewHelper();
        $subject->setArguments([
            'content' => 42,
        ]);
        $result = $subject->initializeArgumentsAndRender();

        $this->assertStringContainsString('data: 42', $result);
    }

    #[Test]
    public function should_convert_data_to_array_if_QueryResultInterface(): void
    {
        $queryResult = $this->getMockBuilder(QueryResultInterface::class)
            ->getMock();

        $queryResult->method('toArray')
            ->willReturn([['1'], ['2']]);

        $subject = new XhrCacheViewHelper();
        $subject->setArguments([
            'content' => $queryResult,
        ]);
        $result = $subject->initializeArgumentsAndRender();

        $this->assertStringContainsString('data: [["1"],["2"]]', $result);
    }
}
