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
$installer = $this;

$installer->startSetup();
$installer->run("ALTER TABLE {$this->getTable('authorizecim/transaction')} ADD COLUMN `cclast4` VARCHAR(50) NULL;");	
$installer->run("ALTER TABLE {$this->getTable('authorizecim/transaction')} ADD COLUMN `card_type` VARCHAR(50) NULL;");	
$installer->endSetup();
?>