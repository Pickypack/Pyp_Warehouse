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


/**
 * Pyp module setup
 *
 * @category    Pyp
 * @package     Pyp_Warehouse
 * @author      Pickypack <hola@pickypack.es>
 */
class Pyp_Warehouse_Model_Resource_Setup extends Mage_Eav_Model_Entity_Setup
{
	public function getDefaultEntities()
	{
		return array(
				'catalog_product' => array(
						'entity_model'      => 'catalog/product',
						'attribute_model'   => 'catalog/resource_eav_attribute',
						'table'             => 'catalog/product',
						'additional_attribute_table' => 'catalog/eav_attribute',
						'entity_attribute_collection' => 'catalog/product_attribute_collection',
						'attributes'        => array(
								'pyp_barcode' => array(
										'group'             => 'Warehouse',
										'label'             => 'Barcode',
										'type'              => 'varchar',
										'input'             => 'text',
										'default'           => '0',
										'class'             => '',
										'backend'           => '',
										'frontend'          => '',
										'source'            => '',
										'global'            => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
										'visible'           => true,
										'required'          => false,
										'user_defined'      => false,
										'searchable'        => false,
										'filterable'        => false,
										'comparable'        => false,
										'visible_on_front'  => false,
										'visible_in_advanced_search' => false,
										'unique'            => true
								),
	
						)
				),
		);
	}
}
