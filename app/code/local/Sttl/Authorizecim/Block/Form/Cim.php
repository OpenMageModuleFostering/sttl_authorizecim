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
class Sttl_Authorizecim_Block_Form_Cim extends Mage_Payment_Block_Form {

    protected function _construct() {
        parent::_construct();
        $this->setTemplate('authorizecim/form/cim.phtml');
    }

    /**
     * Retrieve payment configuration object
     *
     * @return Mage_Payment_Model_Config
     */
    protected function _getConfig() {
        return Mage::getSingleton('payment/config');
    }

    /**
     * Retrieve availables credit card types
     *
     * @return array
     */
    public function getCcAvailableTypes() {
        $types = $this->_getConfig()->getCcTypes();
        if ($method = $this->getMethod()) {
            $availableTypes = $method->getConfigData('cctypes');
            if ($availableTypes) {
                $availableTypes = explode(',', $availableTypes);
                foreach ($types as $code => $name) {
                    if (!in_array($code, $availableTypes)) {
                        unset($types[$code]);
                    }
                }
            }
        }
        return $types;
    }

    /**
     * Retrieve credit card expire months
     *
     * @return array
     */
    public function getCcMonths() {
        $months = $this->getData('cc_months');
        if (is_null($months)) {
            $months[0] = $this->__('Month');
            $months = array_merge($months, $this->_getConfig()->getMonths());
            $this->setData('cc_months', $months);
        }
        return $months;
    }

    /**
     * Retrieve credit card expire years
     *
     * @return array
     */
    public function getCcYears() {
        $years = $this->getData('cc_years');
        if (is_null($years)) {
            $years = $this->_getConfig()->getYears();
            $years = array(0 => $this->__('Year')) + $years;
            $this->setData('cc_years', $years);
        }
        return $years;
    }

    /**
     * Retrive has verification configuration
     *
     * @return boolean
     */
    public function hasVerification() {
        if ($this->getMethod()) {
            $configData = $this->getMethod()->getConfigData('useccv');
            if (is_null($configData)) {
                return false;
            }
            return (bool) $configData;
        }
        return true;
    }

    /*
     * Whether switch/solo card type available
     */

    public function hasSsCardType() {
        $availableTypes = explode(',', $this->getMethod()->getConfigData('cctypes'));
        $ssPresenations = array_intersect(array('SS', 'SM', 'SO'), $availableTypes);
        if ($availableTypes && count($ssPresenations) > 0) {
            return true;
        }
        return false;
    }

    /*
     * solo/switch card start year
     * @return array
     */

    public function getSsStartYears() {
        $years = array();
        $first = date("Y");

        for ($index = 5; $index >= 0; $index--) {
            $year = $first - $index;
            $years[$year] = $year;
        }
        $years = array(0 => $this->__('Year')) + $years;
        return $years;
    }

    /**
     * Render block HTML
     *
     * @return string
     */
    protected function _toHtml() {
        Mage::dispatchEvent('payment_form_block_to_html_before', array(
            'block' => $this
        ));
        return parent::_toHtml();
    }

    public function getOldCreditCard() {
        if (Mage::getSingleton('customer/session')->isLoggedIn()) {
            $paymentArray = array();
            $customerEmail = Mage::getSingleton('checkout/session')->getQuote()->getCustomerEmail();
            $collection = Mage::getModel('authorizecim/transaction')->getCollection()
                    ->addFieldToFilter('email', $customerEmail)
                    ->getFirstItem();
            $card = Mage::getModel('authorizecim/api')->getPaymentCreditCard($collection->getProfileId());
            if ($card && count($card)) {
                $key = 0;
                foreach ($card as $profile) {
                    $paymentProfileId = $profile->customerPaymentProfileId;
                    $cardNumber = $profile->payment->creditCard->cardNumber;
                    $expirationDate = $profile->payment->creditCard->expirationDate;

                    foreach ($paymentProfileId as $payment) {
                        $value = (string) $payment;
                    }

                    foreach ($cardNumber as $payment) {
                        $cardNo = (string) $payment;
                    }
                    $paymentArray[$key] = array('value' => (string) $value, 'label' => (string) $cardNo);
                    $key++;
                }
            }

            return $paymentArray;
        } else {
            return false;
        }
    }

}
