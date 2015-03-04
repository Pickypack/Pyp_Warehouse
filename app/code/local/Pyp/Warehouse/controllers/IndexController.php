<?php
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

class Pyp_Warehouse_IndexController extends Mage_Core_Controller_Front_Action {
    public function indexAction() {
    	$connector = Mage::getSingleton('warehouse/connector');
    	    	
        if (Mage::getStoreConfig('pyp/pyp_options/pyp_enabled', Mage::app()->getStore())){
            try {
                $connector->getPendingOperations();
            }
            catch (Exception $e){
                echo "ERROR: " . $e->getMessage();
            }
            echo '<p>Pyp Warehouse module '.$connector->getVersion().' is up and running.</p>';
        }
        else {
            echo '<p>Pyp Warehouse module '.$connector->getVersion().' is disabled.</p>';
        }
        
        //echo '<p>Update product stocks: '. (Mage::getStoreConfig('pyp/pyp_options/pyp_update_stock', Mage::app()->getStore())?'Yes':'No')."</p>";
    }
}
