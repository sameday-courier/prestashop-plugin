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
 *
 * Legacy entry point for PrestaShop &lt; 9. On PS9 use ModuleFrontController
 * (modules/.htaccess blocks direct PHP under /modules/).
 */

include(dirname(__FILE__) . '/libs/sameday-php-sdk/src/Sameday/autoload.php');
include __DIR__ . '/classes/autoload.php';

$action = isset($_GET['action']) ? (string) $_GET['action'] : '';
$isBulkAction = SamedayAjaxHandler::isBulkAction($action);

if ($isBulkAction && !defined('_PS_ADMIN_DIR_')) {
    $adminDirectories = glob(dirname(__FILE__) . '/../../admin*', GLOB_ONLYDIR) ?: [];
    if ($adminDirectories !== []) {
        define('_PS_ADMIN_DIR_', $adminDirectories[0]);
        define('PS_ADMIN_DIR', _PS_ADMIN_DIR_);
    }
}

include(dirname(__FILE__).'/../../config/config.inc.php');

// Bulk admin actions boot with _PS_ADMIN_DIR_ set; PS 1.7 skips customer init in that
// mode and init.php would run FrontController->init() against a missing customer.
if (!$isBulkAction) {
    include(dirname(__FILE__).'/../../init.php');
}

SamedayAjaxHandler::dispatch();
