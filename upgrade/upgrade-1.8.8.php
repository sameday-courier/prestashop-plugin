<?php
/**
 * Clear front controller / Symfony routing caches after adding ModuleFrontControllers.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * @param SamedayCourier $object
 *
 * @return bool
 */
function upgrade_module_1_8_8($object)
{
    if (method_exists('Tools', 'clearSf2Cache')) {
        Tools::clearSf2Cache();
    }

    if (method_exists('Tools', 'clearCache')) {
        Tools::clearCache();
    }

    return true;
}
