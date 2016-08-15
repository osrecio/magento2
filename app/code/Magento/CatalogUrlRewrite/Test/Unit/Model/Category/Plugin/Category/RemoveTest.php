<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\CatalogUrlRewrite\Test\Unit\Model\Category\Plugin\Category;

use Magento\CatalogUrlRewrite\Model\Category\Plugin\Category\Remove;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\UrlRewrite\Model\UrlPersistInterface;
use Magento\CatalogUrlRewrite\Model\Category\ChildrenCategoriesProvider;
use Magento\Catalog\Model\ResourceModel\Category as ResourceCategory;
use Magento\Catalog\Model\Category;

class RemoveTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var UrlPersistInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $urlPersistMock;

    /**
     * @var ChildrenCategoriesProvider|\PHPUnit_Framework_MockObject_MockObject
     */
    private $childrenCategoriesProviderMock;

    /**
     * @var ResourceCategory|\PHPUnit_Framework_MockObject_MockObject
     */
    private $subjectMock;

    /**
     * @var Category|\PHPUnit_Framework_MockObject_MockObject
     */
    private $objectMock;

    protected function setUp()
    {
        $this->objectManager = new ObjectManager($this);
        $this->urlPersistMock = $this->getMockBuilder(UrlPersistInterface::class)
            ->getMockForAbstractClass();
        $this->childrenCategoriesProviderMock = $this->getMockBuilder(ChildrenCategoriesProvider::class)
            ->getMock();
        $this->subjectMock = $this->getMockBuilder(ResourceCategory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->objectMock = $this->getMockBuilder(Category::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testBeforeDelete()
    {
        $expectedResult = [$this->objectMock];
        $plugin =  $this->objectManager->getObject(
            Remove::class,
            [
                'urlPersist' => $this->urlPersistMock,
                'childrenCategoriesProvider' => $this->childrenCategoriesProviderMock
            ]
        );
        $this->childrenCategoriesProviderMock->expects($this->once())
            ->method('getChildrenIds')
            ->with($this->objectMock, true)
            ->willReturn([]);
        $this->objectMock->expects($this->once())
            ->method('getId')
            ->willReturn(1);
        $this->assertEquals(
            $expectedResult,
            $plugin->beforeDelete($this->subjectMock, $this->objectMock)
        );
    }

    public function testAfterDelete()
    {
        $categoryIds = [1];
        $this->urlPersistMock->expects($this->exactly(2))
            ->method('deleteByData');
        $plugin =  $this->objectManager->getObject(
            Remove::class,
            [
                'urlPersist' => $this->urlPersistMock,
                'childrenCategoriesProvider' => $this->childrenCategoriesProviderMock,
                'categoryIds' => $categoryIds
            ]
        );
        $this->assertEquals(
            $this->subjectMock,
            $plugin->afterDelete($this->subjectMock, $this->subjectMock, $this->objectMock)
        );
    }
}
