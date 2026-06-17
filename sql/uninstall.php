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

$tablesToDrop = [
    SamedayService::TABLE_NAME,
    SamedayPickupPoint::TABLE_NAME,
    SamedayAwb::TABLE_NAME,
    SamedayAwbParcel::TABLE_NAME,
    SamedayAwbParcelHistory::TABLE_NAME,
    SamedayLocker::TABLE_NAME,
    SamedayOrderLocker::TABLE_NAME,
    SamedayOpenPackage::TABLE_NAME,
    SamedayCity::TABLE_NAME,
    SamedayOrderBulkAwb::TABLE_NAME,
];

$columnsToDrop = [
    [
        'columnName' => 'sameday_locker',
        'fromTable' => CartCore::$definition['table'],
    ],
];

$queryHelper = new SamedayGeneralQueryHelper();

foreach ($tablesToDrop as $table) {
    $queryHelper->dropTable(_DB_PREFIX_ . $table);
}

foreach ($columnsToDrop as $column) {
    $tableName = _DB_PREFIX_ . $column['fromTable'];
    if ($queryHelper->isTableExists($tableName)) {
        $queryHelper->dropColumn($tableName, $column['columnName']);
    }
}
