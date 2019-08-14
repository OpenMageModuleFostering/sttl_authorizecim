<?php
/**
 * Silver Touch Technologies Limited.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the EULA
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.silvertouch.com/MagentoExtensions/LICENSE.txt
 *
 * @category   Sttl
 * @package    Sttl_Authorizecim
 * @copyright  Copyright (c) 2011 Silver Touch Technologies Limited. (http://www.silvertouch.com/MagentoExtensions)
 * @license    http://www.silvertouch.com/MagentoExtensions/LICENSE.txt
 */ 
class Sttl_Authorizecim_Model_System_Config_Source_Method
{

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array('value' => 'createCustomerProfileRequest', 'label'=>Mage::helper('authorizecim')->__('createCustomerProfileRequest')),
            array('value' => 'createCustomerPaymentProfileRequest', 'label'=>Mage::helper('authorizecim')->__('createCustomerPaymentProfileRequest')),
            array('value' => 'createCustomerShippingAddressRequest', 'label'=>Mage::helper('authorizecim')->__('createCustomerShippingAddressRequest')),
            array('value' => 'createCustomerProfileTransactionRequest', 'label'=>Mage::helper('authorizecim')->__('createCustomerProfileTransactionRequest')),
            array('value' => 'deleteCustomerProfileRequest', 'label'=>Mage::helper('authorizecim')->__('deleteCustomerProfileRequest')),
            array('value' => 'deleteCustomerPaymentProfileRequest', 'label'=>Mage::helper('authorizecim')->__('deleteCustomerPaymentProfileRequest')),
            array('value' => 'deleteCustomerShippingAddressRequest', 'label'=>Mage::helper('authorizecim')->__('deleteCustomerShippingAddressRequest')),
            array('value' => 'getCustomerProfileIdsRequest', 'label'=>Mage::helper('authorizecim')->__('getCustomerProfileIdsRequest')),
            array('value' => 'getCustomerProfileRequest', 'label'=>Mage::helper('authorizecim')->__('getCustomerProfileRequest')),
            array('value' => 'getCustomerPaymentProfileRequest', 'label'=>Mage::helper('authorizecim')->__('getCustomerPaymentProfileRequest')),
            array('value' => 'getCustomerShippingAddressRequest', 'label'=>Mage::helper('authorizecim')->__('getCustomerShippingAddressRequest')),
            array('value' => 'updateCustomerProfileRequest', 'label'=>Mage::helper('authorizecim')->__('updateCustomerProfileRequest')),
            array('value' => 'updateCustomerPaymentProfileRequest', 'label'=>Mage::helper('authorizecim')->__('updateCustomerPaymentProfileRequest')),
            array('value' => 'updateCustomerShippingAddressRequest', 'label'=>Mage::helper('authorizecim')->__('updateCustomerShippingAddressRequest')),
            array('value' => 'updateSplitTenderGroupRequest', 'label'=>Mage::helper('authorizecim')->__('updateSplitTenderGroupRequest')),
            array('value' => 'validateCustomerPaymentProfileRequest', 'label'=>Mage::helper('authorizecim')->__('validateCustomerPaymentProfileRequest')),
            );
    }

}
