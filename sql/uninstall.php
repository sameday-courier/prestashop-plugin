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
 * @copyright 2007-2019 PrestaShop SA
 * @license   http://addons.prestashop.com/en/content/12-terms-and-conditions-of-use
 * International Registered Trademark & Property of PrestaShop SA
 */

$sql = array(
    'DROP TABLE '._DB_PREFIX_. SamedayService::TABLE_NAME,
    'DROP TABLE '._DB_PREFIX_. SamedayPickupPoint::TABLE_NAME,
    'DROP TABLE '._DB_PREFIX_. SamedayAwb::TABLE_NAME,
    'DROP TABLE '._DB_PREFIX_. SamedayAwbParcel::TABLE_NAME,
    'DROP TABLE '._DB_PREFIX_. SamedayAwbParcelHistory::TABLE_NAME,
    'DROP TABLE '._DB_PREFIX_. SamedayLocker::TABLE_NAME,
    'DROP TABLE '._DB_PREFIX_. SamedayOrderLocker::TABLE_NAME,
    'DROP TABLE '._DB_PREFIX_. SamedayOpenPackage::TABLE_NAME,
);

foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        return false;
    }
}
