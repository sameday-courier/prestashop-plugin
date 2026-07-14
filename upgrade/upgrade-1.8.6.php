<?php
/**
 * 2007-2020 PrestaShop
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_8_6($object)
{
    $tableName = _DB_PREFIX_ . SamedayOrderBulkAwb::TABLE_NAME;
    if (false === (new SamedayGeneralQueryHelper())->isTableExists($tableName)) {
        $query = 'CREATE TABLE `' . $tableName . "` (
          `id_order` int(11) unsigned NOT NULL,
          `status` tinyint(1) NOT NULL DEFAULT '0',
          `feedback` text,
          `date_add` datetime NOT NULL,
          `date_upd` datetime NOT NULL,
          PRIMARY KEY (`id_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

        if (!Db::getInstance()->execute($query)) {
            return false;
        }
    }

    $toolbarHooks = [
        'displayBackOfficeTop',
        'actionAdminControllerSetMedia',
    ];

    foreach ($toolbarHooks as $hook) {
        if (!$object->isRegisteredInHook($hook)) {
            if (!$object->registerHook($hook)) {
                return false;
            }
        }
    }

    if (version_compare(_PS_VERSION_, '1.7.7', '>=')) {
        $gridHooks = [
            'actionOrderGridDefinitionModifier',
            'actionOrderGridDataModifier',
        ];

        foreach ($gridHooks as $hook) {
            if (!$object->isRegisteredInHook($hook)) {
                if (!$object->registerHook($hook)) {
                    return false;
                }
            }
        }
    } else {
        $legacyListHooks = [
            'actionAdminOrdersListingFieldsModifier',
            'actionAdminOrdersListingResultsModifier',
        ];

        foreach ($legacyListHooks as $hook) {
            if (!$object->isRegisteredInHook($hook)) {
                if (!$object->registerHook($hook)) {
                    return false;
                }
            }
        }
    }

    return true;
}
