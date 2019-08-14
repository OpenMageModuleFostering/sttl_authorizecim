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
class Sttl_Authorizecim_Block_Info_Cim extends Mage_Payment_Block_Info_Cc {

    protected function _prepareSpecificInformation($transport = null) {

        if (null !== $this->_paymentSpecificInformation) {
            return $this->_paymentSpecificInformation;
        }
        $info = $this->getInfo();
        if ($info->getData('last_trans_id')):
            $orderId = $this->getInfo()->getOrder()->getId();
            $CimCollection = Mage::getModel('authorizecim/transaction')->getCollection()
                ->addFieldToFilter('order_id', $orderId);
            if($CimCollection->count()){
                $cim = $CimCollection->getFirstItem();
                $cardType = $cim->getCardType();
                $cardno = $cim->getCclast4();
            }else{
                $card = Mage::getModel('authorizecim/api')->getPaymentDetail($info->getData('last_trans_id'));
                $cardType = $card->cardType;
                $cardno = $card->cardNumber;
            }
            $transport = new Varien_Object();
            $transport->addData(array(
                Mage::helper('payment')->__('Credit Card Type') => $cardType,
                Mage::helper('payment')->__('Credit Card Number') => $cardno,
            ));
        endif;
        $transport = parent::_prepareSpecificInformation($transport);
        return $transport;
    }

}
