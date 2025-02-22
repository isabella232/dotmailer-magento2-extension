<?php

namespace Dotdigitalgroup\Email\Test\Unit\Model\Sync\Catalog;

use Dotdigitalgroup\Email\Helper\Data;
use Dotdigitalgroup\Email\Model\Connector\ProductFactory;
use Dotdigitalgroup\Email\Model\ResourceModel\Catalog\Collection;
use Dotdigitalgroup\Email\Model\ResourceModel\Catalog\CollectionFactory;
use Dotdigitalgroup\Email\Model\Sync\Catalog\Exporter;
use Magento\Catalog\Model\Product;
use Dotdigitalgroup\Email\Logger\Logger;
use PHPUnit\Framework\TestCase;

class ExporterTest extends TestCase
{
    /**
     * @var Exporter
     */
    private $exporter;

    /**
     * @var CollectionFactory
     */
    private $collectionFactoryMock;

    /**
     * @var ProductFactory
     */
    private $productFactoryMock;

    /**
     * @var Collection
     */
    private $collectionMock;

    /**
     * @var Logger
     */
    private $loggerMock;

    protected function setUp() :void
    {
        $this->collectionFactoryMock = $this->createMock(CollectionFactory::class);
        $this->productFactoryMock = $this->createMock(ProductFactory::class);
        $this->collectionMock = $this->createMock(Collection::class);
        $this->loggerMock = $this->createMock(Logger::class);

        $this->exporter = new Exporter(
            $this->collectionFactoryMock,
            $this->productFactoryMock,
            $this->loggerMock
        );
    }

    /**
     * @dataProvider getProductAndStoreIds
     * @param $storeId
     * @param $product1Id
     * @param $product2Id
     * @param $productsToProcess
     */
    public function testThatExportKeysAndProductsMatches($storeId, $product1Id, $product2Id)
    {
        $productsToProcess = $this->getMockProductsToProcess();

        $this->collectionFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($this->collectionMock);

        $productMock1 = $this->getMockProducts($product1Id);
        $productMock2 = $this->getMockProducts($product2Id);

        $exposedProduct1 = $this->getExposedProduct($product1Id);
        $exposedProduct2 = $this->getExposedProduct($product2Id);

        $connectorProductMock1 = $this->getMockConnectorProducts($productMock1, $exposedProduct1);
        $connectorProductMock2 = $this->getMockConnectorProducts($productMock2, $exposedProduct2);

        $products = [$productMock1, $productMock2];

        $this->collectionMock->expects($this->once())
            ->method('filterProductsByStoreTypeAndVisibility')
            ->with($storeId, $productsToProcess)
            ->willReturn($products);

        $this->productFactoryMock->expects($this->exactly(2))
            ->method('create')
            ->willReturnOnConsecutiveCalls(
                $connectorProductMock1,
                $connectorProductMock2
            );

        $actual = $this->exporter->exportCatalog($storeId, $productsToProcess);

        $actualExposedProduct1 = $actual[$product1Id];
        $this->assertEquals($exposedProduct1, $actualExposedProduct1);

        $actualExposedProduct2 = $actual[$product2Id];
        $this->assertEquals($exposedProduct2, $actualExposedProduct2);
    }

    /**
     * Returns the mocked Products
     * @param $storeId
     * @param $productId
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function getMockProducts($productId)
    {
        $product = $this->createMock(Product::class);
        $product->expects($this->once())
            ->method('getId')
            ->willReturn($productId);

        return $product;
    }

    /**
     * Returns the connector Mock Products
     * @param $productMock
     * @param $exposedMock
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    private function getMockConnectorProducts($productMock, $exposedMock)
    {
        $connectorProduct = $this->createMock(\Dotdigitalgroup\Email\Model\Connector\Product::class);
        $connectorProduct->expects($this->once())
            ->method('setProduct')
            ->with($productMock)
            ->willReturnSelf();

        $connectorProduct->name = $exposedMock['name'];
        $connectorProduct->id = $exposedMock['id'];

        return $connectorProduct;
    }

    /**
     * @return array
     * Returns ids for products and store
     */
    public function getProductAndStoreIds()
    {
        return [
            [1, 1254, 337],
            [2, 2234, 554],
            [4, 332, 2445]
        ];
    }

    /**
     * Returns product array
     *
     * @return array
     */
    private function getMockProductsToProcess()
    {
        return [
            0 => '1205',
            1 => '1206',
            2 => '1207',
            3 => '1208',
            4 => '1209',
            5 => '1210',
            6 => '1211',
            7 => '1212',
            8 => '1213',
            9 => '1214'
        ];
    }

    /**
     * @param $id
     * @return array
     */
    private function getExposedProduct($id)
    {
        return [
            'id' => (int) $id,
            'name' => 'product' . $id,
            'parent_id' => '',
            'sku' => '',
            'status' => '',
            'visibility' => '',
            'price' => 0,
            'price_incl_tax' => 0,
            'specialPrice' => 0,
            'specialPrice_incl_tax' => 0,
            'tierPrices' => [],
            'categories' => [],
            'url' => '',
            'imagePath' => '',
            'shortDescription' => '',
            'stock' => 0,
            'websites' => [],
            'type' => ''
        ];
    }
}
