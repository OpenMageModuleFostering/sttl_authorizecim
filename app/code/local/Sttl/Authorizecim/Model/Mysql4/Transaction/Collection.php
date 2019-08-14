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

class Sttl_Authorizecim_Model_Mysql4_Transaction_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{
    
    protected $_previewFlag;
    
    public function _construct()
    {
        parent::_construct();
        $this->_init('authorizecim/transaction');
    }
   
}