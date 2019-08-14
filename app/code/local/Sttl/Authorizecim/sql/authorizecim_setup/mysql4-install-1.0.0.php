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
$installer->run("
	CREATE TABLE {$this->getTable('authorizecim/transaction')} (
	`authorize_id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
	`email` VARCHAR( 50 ) NOT NULL,
	`order_id` INT( 11 ) NOT NULL ,
	`order_payment_id` INT( 11 ) NOT NULL ,
	`profile_id` double  NOT NULL ,
	`payment_id` double  NOT NULL ,
	`shipping_id` double  NOT NULL ,
	`txn_id` double  NOT NULL ,
	`response` TEXT NOT NULL ,
	`created` DATETIME NOT NULL
	) ENGINE = innodb;
");

$installer->endSetup();
?>