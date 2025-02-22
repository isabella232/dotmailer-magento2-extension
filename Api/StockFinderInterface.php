<?php

namespace Dotdigitalgroup\Email\Api;

interface StockFinderInterface
{
    /**
     * @param \Magento\Catalog\Model\Product $product
     * This function calculates the stock Quantity for each Product.
     * @param int $websiteId
     * @return float
     */
    public function getStockQty($product, int $websiteId);
}
