<?php
Header('Content-Type: text/html; charset=utf-8');
ini_set('display_errors', 1);

require_once('app/Mage.php');
umask(0);
Mage::app();

/*$product = Mage::getModel('catalog/product')->load(1004);
echo $product->toXml();*/


$product = Mage::getModel('catalog/product_api');


/**
 * Update product data
 *
 * @param int|string $productId
 * @param array $productData
 * @param string|int $store
 * @return boolean
 */

$product->update('config-1-man3', array(
    'name' => 'Testr423r',
    'description' => 'descriptionr423few'
), null, 'sku');


/**
 * Get product data
 */
// Zend_Debug::dump($product->info('config-1-man3', null, null, 'sku'));

echo 'ok';
