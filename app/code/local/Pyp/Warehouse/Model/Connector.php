<?php 
define("NACEX_SHOP", 31);
define("PLUS_PACK", 26);
define("ENACEX", 27);

/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to hola@pickypack.es so we can send you a copy immediately.
 *
 * @category    Pyp
 * @package     Pyp_Warehouse
 * @copyright   Copyright (c) 2013 Pickypack KG (http://pickypack.es)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Pyp_Warehouse_Model_Connector extends Mage_Core_Model_Abstract {
	const AGENT = 'Pyp Conector v1.0';
    const PYP_DEFAULT_URL = 'http://api.pickypack.es/api';
	const PYP_BARCODE_URL = 'http://api.pickypack.es/api/barcode'; 	
	const HOLIDAY_FILE = "/tmp/holiday.data";
	
	var $PYP_GIFTS = array("ZAC002");

    private function getPypUrl(){
        $url = Mage::getStoreConfig('pyp/pyp_development/pyp_url', Mage::app()->getStore());
        if (!$url){
            $url = $this::PYP_DEFAULT_URL;
        }

        return $url;
    }
	
    public function _construct() {
        parent::_construct();
        $this->_init('warehouse/connector');
    }
 
    public function getVersion() {
        return '0.1.2';
    }
    
    /**
     * Is executed each time a shipment is created.
     */
    public function shipmentSaved($observer) {   
        if (!Mage::getStoreConfig('pyp/pyp_options/pyp_enabled', Mage::app()->getStore())) return;

    	$shipment = $observer->getEvent()->getShipment();
    	$data = $this->buildShipmentArray( $shipment );
    	
    	try {
    		$this->call($data, 'shipment.new');
       	} catch (Exception $e) {
    	}
    	
    }

    public function orderCanceled($observer) {   
        if (!Mage::getStoreConfig('pyp/pyp_options/pyp_enabled', Mage::app()->getStore())) return;

    	foreach($observer->getEvent()->getOrder()->getShipmentsCollection() as $shipment) {
	    	try {
	    		$this->call(array("increment_id" => $shipment->getIncrementId()), 'shipment.cancel');
	       	} catch (Exception $e) {
	    	}
    	}
    	
    }    
    
    /**
     * Is executed each time a shipment changes status.
     */
    public function statusChanged($observer) {
        if (!Mage::getStoreConfig('pyp/pyp_options/pyp_enabled', Mage::app()->getStore())) return;

        $original_data = $observer->getEvent()->getData('data_object')->getOrigData();
        $new_data = $observer->getEvent()->getData('data_object')->getData();
        if ($original_data['status'] !== $new_data['status']) {       	
        	$order = $observer->getEvent()->getOrder();
        	$status = $new_data['status'];
    
            if (!($readyStatus = Mage::getStoreConfig('pyp/pyp_options/pyp_ready_status', Mage::app()->getStore()))){
                $readyStatus = "dropship";
            }
        	if (count($order->getShipmentsCollection()) == 0 && $status == $readyStatus){
        		
        		$itemQty =  $order->getItemsCollection()->count();
    			$shipment = Mage::getModel('sales/service_order', $order)->prepareShipment($itemQty);
    			$shipment = new Mage_Sales_Model_Order_Shipment_Api();
    			try {
    				$shipmentId = $shipment->create($order->getIncrementId());
    			} catch (Exception $e) {
    				$fp = fopen("/tmp/s.out", "a");
        			fwrite($fp, $e->getMessage()." \n");
        			fclose($fp);				
    			}
        		
        	}
        }
    }
    
    /**
     * Is executed each time a shipment is created.
     */
    public function productSaved($observer) {   
        if (!Mage::getStoreConfig('pyp/pyp_options/pyp_enabled', Mage::app()->getStore())) return;

        $this->updateProduct( $observer->getEvent()->getProduct() );
    }

    public function updateProduct($product){
        if (!Mage::getStoreConfig('pyp/pyp_options/pyp_enabled', Mage::app()->getStore())) return;

        if ($product->getPypBarcode()){
            //$product = Mage::getModel('catalog/product')->load($item->getProductId());
            $imgUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . 'catalog/product' . $product->getImage();
            //$imgUrl = Mage::helper('catalog/image')->init($product, 'image')->resize(320);
            $data = array(
                "product_id" => $product->getId()
                , "sku" => $product->getSku()
                , "barcode" => $product->getPypBarcode()
                , "name" => $product->getName()
                , "image_url" => "$imgUrl"
                , "distributor" => $product->getDistribuidor()
            );

            try {
                return $this->call($data, 'product.update');
            } catch (Exception $e) {
                return $e->getMessage();
            }
        }
    }

    /**
     * Is executed when config parameters are saved
     */
    public function register() {        
    	$config = array("base_url" => Mage::getBaseUrl());
    	
    	try {
    		$res = $this->call($config, "register");
    		//$this->call($this->rollProducts(), "catalog", false);
       	} catch (Exception $e) {
    	}
    }
    
    public function buildShipmentArray($shipment){
        if (!Mage::getStoreConfig('pyp/pyp_options/pyp_enabled', Mage::app()->getStore())) return;

    	$items = array();
    	foreach ($shipment->getOrder()->getAllItems() as $item){
    		$product = Mage::getModel('catalog/product')->load($item->getProductId());
            $imgUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . 'catalog/product' . $product->getImage();
    		//$imgUrl = Mage::helper('catalog/image')->init($product, 'image')->resize(320);
    		
    		if (in_array($product->getSku(), $this->PYP_GIFTS)){
    			$barcode = $item->getSku(); 	
    		}
    		else {
    			$barcode = $product->getPypBarcode();
    		}
    		    	
            $options_str = "";
            try {
                foreach ($item->getProductOptions() as $option) {
                    if (is_array($option)){
                        foreach ($option as $key => $value) {
                            if (is_integer($key)){
                                $options_str .= ($options_str != ''?', ':'') . $value['label'] .": ". $value['value'];
                                //print_r($options_str);
                            }
                        }
                    }
                }                
            } catch (Exception $e) {}

            if (!$item->getData('has_children')){
        		$items[] = array(
        				"product_id" => $item->getProductId()
        				, "sku" => $product->getSku()
        				, "barcode" => $barcode
        				, "name" => $product->getName()
        				, "qty" => $item->getQtyOrdered()
        				, "qtyBackordered" => $item->getQtyBackordered()
        				, "image_url" => "$imgUrl"
        				, "disponibilidad" => $product->getDisponibilidad()
                        , "opciones" => $options_str
        		);
            }
    	}

    	$att = $shipment->getOrder()->getShippingAddress()->getFirstname() . " " . $shipment->getOrder()->getShippingAddress()->getLastname();
    	$streetArray = $shipment->getOrder()->getShippingAddress()->getStreet();
    	$street = $streetArray[0];
    	if(isset($streetArray[1])) $street .= " " . $streetArray[1];

		$method = $shipment->getOrder()->getShippingDescription();
        $code = $shipment->getOrder()->getShippingMethod();

	    $company = $shipment->getOrder()->getShippingAddress()->getCompany();
        //echo "($method)";

        $address = $shipment->getOrder()->getShippingAddress()->format('text');
        $cp = substr($shipment->getOrder()->getShippingAddress()->getPostcode(), 0, 15);
        $pob = substr($shipment->getOrder()->getShippingAddress()->getCity(), 0, 40);
        $countryCode = $shipment->getOrder()->getShippingAddress()->getCountryId();

        $codeAr = explode("_", $code);
        if ($codeAr[0] == "Nacexshipping"){
            $servicio = end ( $codeAr );    
            if ($servicio == NACEX_SHOP){
                $arr = explode("|", $company);
                $dropPoint =  trim($arr[0]);
            }
        }
        /*        
    	elseif(preg_match('/nacexshop/i', $method) || preg_match('/nacex.shop/i', $method) || preg_match('/Punto de Recogida/i', $method)
    			 || preg_match('/NACEXSHOP/i', $company)){
    		$servicio = NACEX_SHOP;
            $arr = explode("|", $company);
            $dropPoint =  trim($arr[0]);
    	}
        elseif(preg_match('/nacex/i', $method)) {
            $servicio = PLUS_PACK;
        }
        */
        elseif(preg_match('/pickupatstore_([0-9]+)/i', $code, $matches)) {
            $servicio = NACEX_SHOP;
            $pos = Mage::getModel('pointofsale/pointofsale')->load($matches[1]);
            //print_r($pos);
            //die;
            $arr = explode("<", $method);
            $company = $pos->getName();
            $dropPoint = $pos->getStoreCode();
            $data = $pos->getdata();
            $street = $data["address_line_1"];
            $cp = $data["postal_code"];
            $pob = $data["city"];
            $countryCode = $data["country_code"];
        }
        else {
            $servicio = ENACEX;
        }

        $onDeliveryAmount = 0;   
        $cashMethods = explode(",", Mage::getStoreConfig('pyp/pyp_options/pyp_cashondelivery_method', Mage::app()->getStore()));
        if (in_array($shipment->getOrder()->getPayment()->getMethod(), $cashMethods)) {
            $onDeliveryAmount = $shipment->getOrder()->getPayment()->getAmountOrdered();
        }
/*
        $onDeliveryString = Mage::getStoreConfig('pyp/pyp_options/pyp_cashondelivery_string', Mage::app()->getStore());
        if (!$onDeliveryString) $onDeliveryString = "cashondelivery";
        if(preg_match("/$onDeliveryString/i", $shipment->getOrder()->getPayment()->getMethod())){
            $onDeliveryAmount = $shipment->getOrder()->getPayment()->getAmountOrdered();
        }
        else {
            $onDeliveryAmount = 0;   
        }
*/
        
    	$ret = array(
    			"increment_id" => $shipment->getIncrementId()
    			, "order_id" => $shipment->getOrder()->getIncrementId()
    			, "email" => $shipment->getOrder()->getCustomerEmail()
    			, "ondelivery_amount" => $onDeliveryAmount
    			, "gift_wrap" => "false"
    			, "created_at" => strtotime( $shipment->getCreatedAt() )
    			, "items" => $items
    			, "address" => $address
    			, "region" => $shipment->getOrder()->getShippingAddress()->getRegion()
    			, "company" => $company
    			, "tip_ser" => "$servicio"
    			, "tip_env" => "2"
                , "nom_ent" => substr($shipment->getOrder()->getShippingAddress()->getName(), 0, 50) // Nombre destinatario
                , "customer_name" => $shipment->getOrder()->getShippingAddress()->getFirstname() // Nombre destinatario
                , "per_ent" => $att
                , "dir_ent" => ( $street )
                , "pais_ent" => $countryCode
                , "cp_ent" => $cp
                , "pob_ent" => $pob
                , "tel_ent" => substr($shipment->getOrder()->getShippingAddress()->getTelephone(), 0, 20)
                , "shop_codigo" => $servicio == NACEX_SHOP?"$dropPoint":""
    	);

/*
        try{
            $encoded = json_encode($ret);
        }
        catch( Exception $e ){
            echo "ERROR";
            echo $e->getMessage();
        }
        echo "($encoded)";
        die;
*/

    	//echo json_encode($ret);

    	
    	return $ret;
    }
    
    private function rollProducts(){
    		
    	foreach(Mage::getModel('catalog/product')->getCollection() as $p){
    		$product = Mage::getModel('catalog/product')->load($p->getId());
    		$ret = array();

    		if ($product->getPypBarcode()){
    			$product_data['product_id'] = $product->getId();
    			$product_data['name'] = $product->getName();
    			try {
    				//$img = Mage::helper('catalog/image')->init($product, 'image')->resize(320);
                    $img = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . 'catalog/product' . $product->getImage();
                } catch (Exception $e) {
    				$img = "";
    			}
    			$product_data['image_url'] = "$img";
    			$product_data['barcode'] = $product->getPypBarcode();
    			$product_data['sku'] = $product->getSku();
    			
    			$ret[] = $product_data;
    		}
    	}

    	return $ret;
    }
    
    public function call( $data, $op, $async = false, $user = false, $pass = false){
        if (!Mage::getStoreConfig('pyp/pyp_options/pyp_enabled', Mage::app()->getStore())) return;

    	$browser = curl_init();
    	curl_setopt($browser, CURLOPT_USERAGENT, Pyp_Warehouse_Model_Connector::AGENT);
    	curl_setopt($browser, CURLOPT_RETURNTRANSFER, 1);
    	curl_setopt($browser, CURLOPT_URL, $this->getPypUrl());
    	curl_setopt($browser, CURLOPT_POST, true);
    	$params = "op=$op&data=".urlencode(json_encode($data));

    	curl_setopt($browser, CURLOPT_POSTFIELDS, $params);
    	
    	curl_setopt($browser, CURLOPT_HTTPHEADER, array("x-wsse: ". $this->getWsse($user, $pass)));

		if ($async){
	    	curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
	    	curl_setopt($ch, CURLOPT_TIMEOUT, 1);
		}
    	
    	$res = curl_exec($browser);

    	$info = curl_getinfo($browser);

    	if ($info['http_code'] == 403){
    		throw new Exception("Acceso denegado. Compruebe credenciales en System->configuration->Pickypack->warehouse->Identificacion");
    	}
    	elseif ($info['http_code'] != 200){
    		throw new Exception("Server error ".$info['http_code'].".");
    	}
    	else{
    		$ret = json_decode($res);
    		if ($ret->code != 0){
    			throw new Exception("Error ".$ret->code.": ".$ret->msg);
    		}
    	}
    	
    	curl_close($browser);

    	return json_decode($res);
    }
    
    public function getPendingOperations() {
        if (!Mage::getStoreConfig('pyp/pyp_options/pyp_enabled', Mage::app()->getStore())) return;

        $res = $this->call(array(), "operations.pending");
    	
    	foreach($res->admissions as $admission){
    		try {
    			$this->updateStock($admission->items); 
    			$this->call(array("id" => $admission->id), "admission.finish"); 
    		} catch (Exception $e) {
    		}    		
    	}
    	
    	foreach($res->shipments as $shipment){
    		try {
    			$this->deliverOrder($shipment->increment_id, $shipment->tracking_number);
    			$this->call(array("id" => $shipment->id), "order.finish"); 
    		} catch (Exception $e) {
    			echo $e->getMessage();
    		}
    	}
    }
    
    private function getWsse($user, $pass){
    	$pypUsername = $user?$user:(Mage::getStoreConfig('pyp/pyp_config/pyp_email', Mage::app()->getStore()));
    	$pypApikey = $pass?$pass:(Mage::getStoreConfig('pyp/pyp_config/pyp_apikey', Mage::app()->getStore()));
    	$created = date('c');
    	$nonce = md5(rand(), true);
    	$base64_nonce = base64_encode($nonce);
    	$password_digest = base64_encode(sha1($nonce.$created.$pypApikey, true));
    	return "UsernameToken Username=\"$pypUsername\", PasswordDigest=\"$password_digest\", Nonce=\"$base64_nonce\", Created=\"$created\"";    	
    }
    
    public function getBarcode($number) {
        if (!Mage::getStoreConfig('pyp/pyp_options/pyp_enabled', Mage::app()->getStore())) return;

    	$browser = curl_init();
    	curl_setopt($browser, CURLOPT_USERAGENT, Pyp_Warehouse_Model_Connector::AGENT);
    	curl_setopt($browser, CURLOPT_RETURNTRANSFER, 1);    	
    	curl_setopt($browser, CURLOPT_URL, Pyp_Warehouse_Model_Connector::PYP_BARCODE_URL. "/$number/embed");
    	//echo Pyp_Warehouse_Model_Connector::PYP_BARCODE_URL. "/$number/embed";die;
    	curl_setopt($browser, CURLOPT_POST, false);

    	curl_setopt($browser, CURLOPT_HTTPHEADER, array("x-wsse: ". $this->getWsse()));
    	
    	curl_setopt($browser, CURLOPT_FOLLOWLOCATION, true);
    	
    	$fileName = "/tmp/$number.png";
		$fp = fopen($fileName, 'wb');
		curl_setopt($browser, CURLOPT_FILE, $fp);
		curl_setopt($browser, CURLOPT_HEADER, 0);
    	$res = curl_exec($browser);
		fclose($fp);
		
		curl_close($browser);
    	    	
		return $fileName;
    }

    public function updateStock($list){
        if (!Mage::getStoreConfig('pyp/pyp_options/pyp_enabled', Mage::app()->getStore())) return;

    	if (Mage::getStoreConfig('pyp/pyp_options/pyp_update_stock', Mage::app()->getStore())){
	    	foreach ($list as $product){
				if ($product->ean != NULL){
					$products = Mage::getModel('catalog/product')->getCollection()
                        ->addAttributeToSelect('distribuidor')
                        ->addAttributeToSelect('pyp_barcode')
                        ->addAttributeToSelect('sku')
                        ->addAttributeToSelect('name')
                        ->addAttributeToSelect('distribuidor')
                        ->addAttributeToSelect('image');
					$products->addAttributeToFilter('status', 1); //enabled
					$products->addAttributeToFilter('pyp_barcode', $product->ean);
                    $products = $products->load();
                    #print_r($prodIds);
				
					#$p = Mage::getModel('catalog/product');
					foreach($products as $p){
                        //echo "($productId)";
						#$p->load($productId);
						$needClearCache = false;
		    			$stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($p);
						if (!$stockItem->getIsInStock()){
							$needClearCache = true;
						}				
		    			$stockItem->addQty( $product->qty );
		    			$stockItem->setIsInStock(true);
		    			$stockItem->save();
		    			$stockItem = false;

                        //$p = Mage::getModel('catalog/product')->load( $p->getId() );
                        //echo $p->getName();
                        //echo $p->getImage();
                        $p->setName( $p->getName() );
                        $p->save();
/*
                        $fpc = Mage::getSingleton('fpc/fpc');
                        $fpc->clean(sha1('product_' . $p->getId()));
*/
		    			$p = false;
			    	}
				}
	    	}
    	}
    }
    
    public function deliverOrder($number, $trackingCode){
        if (!Mage::getStoreConfig('pyp/pyp_options/pyp_enabled', Mage::app()->getStore())) return;
        
    	$shipment = Mage::getModel('sales/order_shipment')
    	->getCollection()
    	->addAttributeToFilter('increment_id', $number)
    	->getFirstItem();
    
    	$ret['code'] = 0;
    
    	if (!$shipment->getId()){
    		throw new Exception("Shipment not found.");
    	}
    
    		$carrier = 'Nacex';
    		$number  =  $trackingCode;
    		$title  = 'Nacex';
    		if (empty($carrier)) {
    			Mage::throwException($this->__('The carrier needs to be specified.'));
    		}
    			
    		$order = Mage::getModel('sales/order')->load($shipment->getOrderId());
    		if ($order->getState() != 'processing' || ($order->getStatus() != 'processing' && $order->getStatus() != 'dropship')){
    			//Mage::throwException($this->__('Wrong status in order.'));
    		}
    		if (empty($number) || $number == '') {
    			$order->setState(self::STATE_PROCESSING, self::STATE_PACKING, "Procesado desde WH para enviar")->save();
    		}
    		else {
				if ($number == "0000/00000002" || empty($number) || $number == ''){
					$order->setState('processing', 'en_almacen', "Procesado desde WH para enviar")->save();
				}
				else {
					if ($number != "0000/00000000" && $number != "0000/00000001") {
		    			$track = Mage::getModel('sales/order_shipment_track')
		    			->setNumber($number)
		    			->setCarrierCode($carrier)
		    			->setTitle($title);
		    			$shipment->addTrack($track)
		    			->save();
					}
	    			 
	    			# state, status, comment
	    			$order->setState('processing', 'sent', "WH_SENT Enviado desde WH con seguimiento $number (cliente notificado)")->save();

                    if (Mage::getStoreConfig('pyp/pyp_options/pyp_notify_send', Mage::app()->getStore())){
	    			    $shipment->sendEmail(); 
                    }

                    //TODO: Activar y cambiar completeOrders.php para que únicamente envíe el email de la encuesta    
                    /*   */            
                    if($order->canInvoice()) {
                        try{
                            //$order->_setState(Mage_Sales_Model_Order::STATE_COMPLETE, true, 'auto por script', null, false)->save();
                            
                            $invoice = $order->prepareInvoice();
                            $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
                            
                            $invoice->register();
                            Mage::getModel('core/resource_transaction')
                            ->addObject($invoice)
                            ->addObject($invoice->getOrder())
                            ->save();
                             
                            //$invoice->sendEmail(true, '');   
                        } catch (Exception $e) {
                            echo "Error: ". $e->getMessage() ."\n";
                        }
                    }
                    /**/
                    
				}
    		}
    			
    		
    }
    
    public function getHolidays( ) {
        if (!Mage::getStoreConfig('pyp/pyp_options/pyp_enabled', Mage::app()->getStore())) return;

    	$hfile = file_exists(self::HOLIDAY_FILE);
    	if ($hfile && (time()-filemtime(self::HOLIDAY_FILE)) < 60*60*24){
    		$cacheContent = trim(file_get_contents(self::HOLIDAY_FILE));
    		$holidays = unserialize($cacheContent);
    	}
    	else {
		    try {
		    	$start = date('Y-m-d');
	
		    	$end = new DateTime($startDate);
		    	$end = $end->add(date_interval_create_from_date_string("1 year"));
		    	$holidays = $this->call(array("start" => "$start", "end" => $end->format('Y-m-d')), 'holidays.get', FALSE);
		    	file_put_contents(self::HOLIDAY_FILE, serialize($holidays));
		    } catch (Exception $e) {
		    	echo $e->getMessage();
		    }   		    
    	}    	
    	return $holidays;
    }
    
    /**
     * Is executed each time a shipment is created.
     */
    public function stockItemSaved($observer) {   
    	$stockItem = $observer->getItem();//->getStockItem();

    	$data = "<ul>\n";
    	$data .= "<li>Product: ".$stockItem->getProductName()." (".$stockItem->getProductId().")</li>\n";
    	$data .= "<li>Qty: ".$stockItem->getQty()."</li>\n";
    	$data .= "<li>in_stock: ".$stockItem->getData("is_in_stock")."</li>\n";
    	$data .= "<li>use_config_manage_stock: ".$stockItem->getData("use_config_manage_stock")."</li>\n";
    	$data .= "<li>manage_stock: ".$stockItem->getData("manage_stock")."</li>\n";
    	$data .= "</ul>\n";
    }
}
