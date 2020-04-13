<?php
/**
 * 2007-2019 PrestaShop
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

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * This function updates your module from previous versions to the version 1.1,
 * useful when you modify your database, or register a new hook ...
 * Don't forget to create one file per version.
 */
function upgrade_module_1_1_0($object)
{
    $sql = array(
        'CREATE TABLE `'. _DB_PREFIX_ . SamedayLocker::TABLE_NAME . "` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=latin1;",
        'CREATE TABLE `'. _DB_PREFIX_ . SamedayOrderLocker::TABLE_NAME . '` (
          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
          `id_order` int(11) unsigned NOT NULL,
          `id_locker` int(11) unsigned NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB;'
    );

    foreach ($sql as $query) {
        if (Db::getInstance()->execute($query) === false) {
            return false;
        }
    }

    return (version_compare(_PS_VERSION_, '1.7.0.0') < 0
        ? $object->registerHook('extraCarrier')
        : $object->registerHook('displayCarrierExtraContent')) &&
        $object->registerHook('actionValidateOrder') &&
        $object->registerHook('actionCarrierProcess');
}
