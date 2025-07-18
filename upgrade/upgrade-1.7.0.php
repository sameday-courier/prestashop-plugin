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
 * This function updates your module from previous versions to the version 1.7.0,
 * useful when you modify your database, or register a new hook ...
 * Don't forget to create one file per version.
 */
function upgrade_module_1_7_0($object)
{
    $generalHelper = new SamedayGeneralQueryHelper();

    $generalHelper->addNewColumn(
        _DB_PREFIX_ . SamedayOrderLocker::$definition['table'],
        'service_code'
    );

    return (version_compare(_PS_VERSION_, '1.7.0.0') < 0
            ? $object->registerHook('extraCarrier')
            : $object->registerHook('displayCarrierExtraContent')) &&
        $object->registerHook('actionValidateOrder') &&
        $object->registerHook('actionCarrierProcess')
    ;
}
