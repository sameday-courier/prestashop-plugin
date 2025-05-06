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

$sql[] = 'CREATE TABLE `'. _DB_PREFIX_ . SamedayCities::$definition['table'] . "` (
          `id` int(11) unsigned NOT NULL AUTO_INCREMENT, 
          `city_name` TEXT, 
          `county_id` int(11), 
          `sdk_id` int(11),
          PRIMARY KEY (`id`) 
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

foreach ($sql as $query) {
    if (!Db::getInstance()->execute($query)) {
        return false;
    }
}
