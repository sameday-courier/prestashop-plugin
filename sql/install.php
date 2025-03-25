<?php
/**
 * 2007-2020 PrestaShop
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2020 PrestaShop SA
 * @license   http://addons.prestashop.com/en/content/12-terms-and-conditions-of-use
 * International Registered Trademark & Property of PrestaShop SA
 */

$sql = array();

$sql[] = "CREATE TABLE `". _DB_PREFIX_ . SamedayService::TABLE_NAME ."` (
          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
          `id_service` int(11) unsigned NOT NULL,
          `name` varchar(50) NOT NULL DEFAULT '',
          `code` varchar(10) NOT NULL DEFAULT '',
          `delivery_type` tinyint(1) NOT NULL,
          `delivery_type_name` varchar(50) NOT NULL,
          `price` decimal(10,2) NOT NULL DEFAULT '0.00',
          `free_delivery` tinyint(1) NOT NULL DEFAULT '0',
          `free_shipping_threshold` decimal(10,2) NULL DEFAULT '0.00',
          `id_carrier` int(11) DEFAULT NULL,
          `status` tinyint(1) NOT NULL DEFAULT '0',
          `disabled` tinyint(1) NOT NULL DEFAULT '0',
          `live_mode` tinyint(1) NOT NULL DEFAULT '0',
          `service_optional_taxes` TEXT,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$sql[] = "CREATE TABLE `". _DB_PREFIX_ . SamedayPickupPoint::TABLE_NAME . "` (
          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
          `id_pickup_point` int(11) unsigned NOT NULL,
          `sameday_alias` varchar(100) NOT NULL DEFAULT '',
          `county` varchar(100) NOT NULL DEFAULT '',
          `city` varchar(255) NOT NULL DEFAULT '',
          `address` varchar(255) NOT NULL DEFAULT '',
          `is_default` tinyint(1) NOT NULL DEFAULT '0',
          `live_mode` tinyint(1) NOT NULL DEFAULT '0',
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$sql[] = "CREATE TABLE `". _DB_PREFIX_ . SamedayAwb::TABLE_NAME . "` (
          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
          `id_order` int(11) NOT NULL,
          `awb_number` varchar(50) NOT NULL DEFAULT '',
          `awb_cost` decimal(10,2) DEFAULT NULL,
          `created` datetime DEFAULT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$sql[] = "CREATE TABLE `". _DB_PREFIX_ . SamedayAwbParcel::TABLE_NAME . "` (
          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
          `id_awb` int(11) unsigned NOT NULL,
          `awb_number` varchar(50) NOT NULL DEFAULT '',
          `position` tinyint(2) NOT NULL,
          `status_sync` text,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$sql[] = "CREATE TABLE `". _DB_PREFIX_ . SamedayAwbParcelHistory::TABLE_NAME . "` (
          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
          `awb_number` varchar(50) NOT NULL DEFAULT '',
          `summary` text,
          `history` text,
          `expedition` text,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$sql[] = 'CREATE TABLE `'. _DB_PREFIX_ . SamedayLocker::TABLE_NAME . "` (
          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
          `id_locker` int(11) unsigned NOT NULL,
          `name` varchar(100) NOT NULL DEFAULT '',
          `county` varchar(100) NOT NULL DEFAULT '',
          `city` varchar(100) NOT NULL DEFAULT '',
          `address` varchar(255) NOT NULL DEFAULT '',
          `postal_code` varchar(16) NOT NULL DEFAULT '',
          `lat` varchar(32) NOT NULL DEFAULT '',
          `long` varchar(32) NOT NULL DEFAULT '',
          `live_mode` tinyint(1) NOT NULL DEFAULT '0',
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$sql[] = 'CREATE TABLE `'. _DB_PREFIX_ . SamedayOrderLocker::$definition['table'] . "` (
          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
          `id_order` int(11) unsigned NOT NULL,
          `id_locker` int(11) unsigned NOT NULL,
          `name_locker` TEXT,
          `address_locker` TEXT,
          `service_code` varchar(5),
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$sql[] = 'ALTER TABLE ' . _DB_PREFIX_ . CartCore::$definition['table'] . '
            ADD `sameday_locker` TEXT';

$sql[] = 'CREATE TABLE `' . _DB_PREFIX_ . SamedayOpenPackage::TABLE_NAME ."` (
          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
          `id_order` int(11) unsigned NOT NULL,
          `is_open_package` tinyint(1) NOT NULL DEFAULT '0',
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$sql[] = 'CREATE TABLE `'. _DB_PREFIX_ . SamedayCities::$definition['table'] . "` (
          `id` int(11) unsigned NOT NULL AUTO_INCREMENT, 
          `city_name` TEXT, 
          `county_id` int(11), 
          PRIMARY KEY (`id`) 
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

foreach ($sql as $query) {
    if (!Db::getInstance()->execute($query)) {
        return false;
    }
}
