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

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Add initial_order_status column used to restore order status after AWB removal.
 *
 * @param SamedayCourier $object
 *
 * @return bool
 */
function upgrade_module_1_8_12($object)
{
    $tableName = _DB_PREFIX_ . SamedayAwb::TABLE_NAME;
    $generalHelper = new SamedayGeneralQueryHelper();

    if (!$generalHelper->isColumnExists($tableName, 'initial_order_status')) {
        if (!Db::getInstance()->execute(
            'ALTER TABLE `' . $tableName . '` ADD `initial_order_status` INT(11) DEFAULT NULL'
        )) {
            return false;
        }
    }

    // Missing key => false; stored "Do not change" (0) is not false.
    if (Configuration::get('SAMEDAY_AWB_ORDER_STATUS') === false) {
        Configuration::updateValue('SAMEDAY_AWB_ORDER_STATUS', 0);
    }

    return true;
}
