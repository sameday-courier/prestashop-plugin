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

function upgrade_module_1_8_7($object)
{
    if (version_compare(_PS_VERSION_, '1.7.7', '>=')) {
        return true;
    }

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

    return true;
}
