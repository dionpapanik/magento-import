<?php

$starttime = (float) array_sum(explode(' ',microtime()));

Header('Content-Type: text/html; charset=utf-8');
ini_set('display_errors', 1);
ini_set('max_execution_time', 1800);

require_once('app/Mage.php');
umask(0);
Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);


// $processes = Mage::getSingleton('index/indexer')->getProcessesCollection();
// $processes->walk('setMode', array(Mage_Index_Model_Process::MODE_MANUAL));
// $processes->walk('save');


$connection = Mage::getSingleton('core/resource')->getConnection('core_read');
//$result=$connection->query("SELECT * FROM `d-sync_import_products` WHERE `row_change` = 1 LIMIT 1,1");

$result=$connection->query("SELECT * FROM `d-sync_import_products` WHERE `Code` = '00-10-940'");

while ($row = $result->fetch() ) {

	$sku = $row['Code'];
	$name = $row['Name'];
	$priceDom = $row['Price_Dom'];
	$priceMSA = $row['Price_MSA'];
	$offerDom = $row['Offer_DOM'];
	$offerMSA = $row['Offer_MSA'];
	$productDom = $row['Product_DOM'];
	$productMSA = $row['Product_MSA'];
	$qty = $row['Qty'];
	$polPos = $row['PolPos'];
	$catId = $row['CategID'];
	$hmiet = $row['HMIET'];
	$arrivalDate = $row['DeliveryDate'];

	/* --- control qty inventory & prices --- */

	$qty = ($qty <= 0) ? 0 : $qty ;
	$offerMSA = ($offerMSA < $priceMSA) ? $offerMSA : null ;
	$offerDom = ($offerDom < $priceDom) ? $offerDom : null ;
	$stock_availability = ($qty > 0) ? 1 : 0 ;
	$arrivalDate = (preg_match('/00/',$arrivalDate)) ? null : $arrivalDate ;
	$Date = Mage::getModel('core/date')->date('m/d/Y', strtotime($arrivalDate));

	/* --- end control qty inventory & prices --- */

	 /*@todo
	* make collection faster
	* $product->setNumSales(1234);
	* $product->getResource()->saveAttribute($product, 'num_sales');
	*
	* @todo
	* save product with images
	*
	*/

	try{

		$product = Mage::getModel('catalog/product')->loadByAttribute('sku', $sku);
		if(!$product){

			$product = Mage::getModel('catalog/product');

		//defaults
			$product
				->setData('website_ids',selectWebsite($productDom,$productMSA))
				->setData('attribute_set_id', 9) //default
				->setData('type_id','simple')
				->setData('created_at', strtotime('now'))
				->setData('sku', $sku)
				->setData('name', $name)
				->setData('weight', 0)
				->setData('status', 1)
				->setData('tax_class_id', 2)
				->setData('visibility', Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
				->setData('description', $name)
				->setData('category_ids', array($catId))
				->setStockData(array(
						'use_config_manage_stock' => 1, //'Use config settings' checkbox
						'is_in_stock' => $stock_availability, //Stock Availability
						'qty' => $qty //qty
					));

			//check for domus
			if($productDom == 1){
				unset ($data);
				$data = array('price'=>$priceDom, 'special_price'=>$offerDom);
				$product->setStoreId(1)->setData($data);
			}

			//checkfor msa
			if($productMSA == 1){
				unset ($data);
				$data = array('price'=>$priceMSA, 'special_price'=>$offerMSA, 'pack_size'=>$polPos);
				$product->setStoreId(14)->setData($data);
			}
			$product->save();
			echo $sku . " saved!</br>";
			$product->unsetData();
		} else {

			echo $product->getId() . " exists</br>";
//			echo $arrivalDate . " arrivalDate</br>";
//			echo $Date . " Date</br>";


			$product->setName($name);
			$product->getResource()->saveAttribute($product, 'name');

			/*
			 * @todo update categories
			 * $product->setCategoryIds(array(10,11,12,14));
			 * $product->getResource()->saveAttribute($product, 'category_ids');
			 *
			*/

			$product->setArrivalTruck($hmiet);
			$product->getResource()->saveAttribute($product, 'arrival_truck');

			$product->setArrivalDate($arrivalDate);
			$product->getResource()->saveAttribute($product, 'arrival_date');

			$stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product);
			$stock->setData('qty', $qty);
			$stock->setData('is_in_stock', $stock_availability);
			$stock->setData('use_config_manage_stock', 1); //'Use config settings' checkbox
			$stock->save();

			if($productDom == '1'){
				$product->setPrice($priceDom);
				$product->setStoreId(1)->getResource()->saveAttribute($product, 'price');

				$product->setSpecialPrice($offerDom);
				$product->setStoreId(1)->getResource()->saveAttribute($product, 'special_price');
			}

			if($productMSA == '1'){
				$product->setPrice($priceMSA);
				$product->setStoreId(14)->getResource()->saveAttribute($product, 'price');

				$product->setSpecialPrice($offerMSA);
				$product->setStoreId(14)->getResource()->saveAttribute($product, 'special_price');

				$product->setSpecialPrice($polPos);
				$product->setStoreId(14)->getResource()->saveAttribute($product, 'pack_size');
			}
		}
	}
	catch(Exception $e){
		Mage::log($e->getMessage(),null, "myImport.log", true);
	}

}


// $processes->walk('reindexAll');
// $processes->walk('setMode', array(Mage_Index_Model_Process::MODE_REAL_TIME));
// $processes->walk('save');


/* --- Helper Functions --- */

function selectWebsite($productDom , $productMSA){
	unset($site);
	$site = array();
	if ($productDom == '1') {
		$site[] = 1;
	}
	if ($productMSA == '1') {
		$site[] = 2;
	}
	return $site;
}

$endtime = (float) array_sum(explode(' ',microtime()));
echo "time: ". sprintf("%.3f", ($endtime-$starttime))." seconds";
echo '<br> Memory Usage: '.round(memory_get_usage(true)/1048576,2)." MB";