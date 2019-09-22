<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\Quote;

use Magento\Catalog\Api\ProductCustomOptionRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;

/**
 * Add simple product with custom options to cart testcases
 */
class AddSimpleProductWithCustomOptionsToCartTest extends GraphQlAbstract
{
    /**
     * @var GetMaskedQuoteIdByReservedOrderId
     */
    private $getMaskedQuoteIdByReservedOrderId;

    /**
     * @var ProductCustomOptionRepositoryInterface
     */
    private $productCustomOptionsRepository;

    /**
     * @var GetCustomOptionsValuesForQueryBySku
     */
    private $getCustomOptionsValuesForQueryBySku;

    /**
     * @var GetEmptyOptionsValuesForQueryBySku
     */
    private $getEmptyOptionsValuesForQueryBySku;

    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->getMaskedQuoteIdByReservedOrderId = $objectManager->get(GetMaskedQuoteIdByReservedOrderId::class);
        $this->productCustomOptionsRepository = $objectManager->get(ProductCustomOptionRepositoryInterface::class);
        $this->getCustomOptionsValuesForQueryBySku = $objectManager->get(GetCustomOptionsValuesForQueryBySku::class);
        $this->getEmptyOptionsValuesForQueryBySku = $objectManager->get(GetEmptyOptionsValuesForQueryBySku::class);
    }

    /**
     * Test adding a simple product to the shopping cart with all supported
     * customizable options assigned
     *
     * @magentoApiDataFixture Magento/Catalog/_files/product_simple_with_options.php
     * @magentoApiDataFixture Magento/Checkout/_files/active_quote.php
     */
    public function testAddSimpleProductWithOptions()
    {
        $sku = 'simple';
        $quantity = 1;
        $maskedQuoteId = $this->getMaskedQuoteIdByReservedOrderId->execute('test_order_1');

        $customOptionsValues = $this->getCustomOptionsValuesForQueryBySku->execute($sku);
        /* Generate customizable options fragment for GraphQl request */
        $queryCustomizableOptionValues = preg_replace('/"([^"]+)"\s*:\s*/', '$1:', json_encode($customOptionsValues));

        $customizableOptions = "customizable_options: {$queryCustomizableOptionValues}";
        $query = $this->getQuery($maskedQuoteId, $sku, $quantity, $customizableOptions);

        $response = $this->graphQlMutation($query);

        self::assertArrayHasKey('items', $response['addSimpleProductsToCart']['cart']);
        self::assertCount(1, $response['addSimpleProductsToCart']['cart']);

        $customizableOptionsOutput = $response['addSimpleProductsToCart']['cart']['items'][0]['customizable_options'];
        $assignedOptionsCount = count($customOptionsValues);
        for ($counter = 0; $counter < $assignedOptionsCount; $counter++) {
            $expectedValues = $this->buildExpectedValuesArray($customOptionsValues[$counter]['value_string']);
            self::assertEquals(
                $expectedValues,
                $customizableOptionsOutput[$counter]['values']
            );
        }
    }

    /**
     * Test adding a simple product with empty values for required options
     *
     * @magentoApiDataFixture Magento/Catalog/_files/product_simple_with_options.php
     * @magentoApiDataFixture Magento/Checkout/_files/active_quote.php
     */
    public function testAddSimpleProductWithMissedRequiredOptionsSet()
    {
        $maskedQuoteId = $this->getMaskedQuoteIdByReservedOrderId->execute('test_order_1');
        $sku = 'simple';
        $quantity = 1;
        $customizableOptions = '';

        $query = $this->getQuery($maskedQuoteId, $sku, $quantity, $customizableOptions);

        self::expectExceptionMessage(
            'The product\'s required option(s) weren\'t entered. Make sure the options are entered and try again.'
        );
        $this->graphQlMutation($query);
    }

    /**
     * Test adding a simple product to the shopping cart with Date customizable option assigned
     *
     * @magentoApiDataFixture Magento/Catalog/_files/product_simple_with_option_date.php
     * @magentoApiDataFixture Magento/Checkout/_files/active_quote.php
     */
    public function testAddSimpleProductWithDateOption()
    {
        $sku = 'simple-product-1';
        $quantity = 1;
        $maskedQuoteId = $this->getMaskedQuoteIdByReservedOrderId->execute('test_order_1');

        $customOptionsValues = $this->getCustomOptionsValuesForQueryBySku->execute($sku);
        $queryCustomizableOptionValues = preg_replace('/"([^"]+)"\s*:\s*/', '$1:', json_encode($customOptionsValues));
        $customizableOptions = "customizable_options: {$queryCustomizableOptionValues}";
        $query = $this->getQuery($maskedQuoteId, $sku, $quantity, $customizableOptions);

        $response = $this->graphQlMutation($query);

        self::assertArrayHasKey('items', $response['addSimpleProductsToCart']['cart']);
        self::assertCount(1, $response['addSimpleProductsToCart']['cart']);

        $customizableOptionOutput = $response['addSimpleProductsToCart']['cart']['items'][0]['customizable_options'][0]['values'][0]['value'];
        $expectedValue = date("M d, Y", strtotime($customOptionsValues[0]['value_string']));

        self::assertEquals($expectedValue, $customizableOptionOutput);
    }

    /**
     * Test adding a simple product with empty values for date option
     *
     * @magentoApiDataFixture Magento/Catalog/_files/product_simple_with_option_date.php
     * @magentoApiDataFixture Magento/Checkout/_files/active_quote.php
     */
    public function testAddSimpleProductWithMissedDateOptionsSet()
    {
        $maskedQuoteId = $this->getMaskedQuoteIdByReservedOrderId->execute('test_order_1');
        $sku = 'simple-product-1';
        $quantity = 1;

        $customOptionsValues = $this->getEmptyOptionsValuesForQueryBySku->execute($sku);
        $queryCustomizableOptionValues = preg_replace('/"([^"]+)"\s*:\s*/', '$1:', json_encode($customOptionsValues));
        $customizableOptions = "customizable_options: {$queryCustomizableOptionValues}";
        $query = $this->getQuery($maskedQuoteId, $sku, $quantity, $customizableOptions);

        self::expectExceptionMessage(
            'Invalid format provided. Please use \'Y-m-d H:i:s\' format.'
        );

        $this->graphQlMutation($query);
    }

    /**
     * @param string $maskedQuoteId
     * @param string $sku
     * @param float $quantity
     * @param string $customizableOptions
     * @return string
     */
    private function getQuery(string $maskedQuoteId, string $sku, float $quantity, string $customizableOptions): string
    {
        return <<<QUERY
mutation {  
  addSimpleProductsToCart(
    input: {
      cart_id: "{$maskedQuoteId}", 
      cart_items: [
        {
          data: {
            quantity: $quantity
            sku: "$sku"
          }
          {$customizableOptions}
        }
      ]
    }
  ) {
    cart {
      items {
        ... on SimpleCartItem {
          customizable_options {
            label
              values {
                value
              }
            }
        }
      }
    }
  }
}
QUERY;
    }

    /**
     * Build the part of expected response.
     *
     * @param string $assignedValue
     * @return array
     */
    private function buildExpectedValuesArray(string $assignedValue) : array
    {
        $assignedOptionsArray = explode(',', trim($assignedValue, '[]'));
        $expectedArray = [];
        foreach ($assignedOptionsArray as $assignedOption) {
            $expectedArray[] = ['value' => $assignedOption];
        }
        return $expectedArray;
    }
}
