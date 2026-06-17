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

include(dirname(__FILE__) . '/libs/sameday-php-sdk/src/Sameday/autoload.php');

$bulkActions = ['bulk_generate_awb', 'bulk_remove_awb', 'clear_bulk_errors', 'download_awb_pdf', 'awb_history'];
$action = isset($_GET['action']) ? (string) $_GET['action'] : '';

if (in_array($action, $bulkActions, true) && !defined('_PS_ADMIN_DIR_')) {
    $adminDirectories = glob(dirname(__FILE__) . '/../../admin*', GLOB_ONLYDIR) ?: [];
    if ($adminDirectories !== []) {
        define('_PS_ADMIN_DIR_', $adminDirectories[0]);
        define('PS_ADMIN_DIR', _PS_ADMIN_DIR_);
    }
}

include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__).'/../../init.php');
include __DIR__ . '/classes/autoload.php';

if (in_array($action, $bulkActions, true)) {
    $employee = Context::getContext()->employee;
    if (!$employee || !(int) $employee->id || !$employee->isLoggedBack()) {
        header('Content-Type: application/json');
        die(json_encode(['success' => false, 'error' => 'Unauthorized']));
    }

    if (Tools::getValue('token') !== Tools::getAdminTokenLite('AdminOrders')) {
        header('Content-Type: application/json');
        die(json_encode(['success' => false, 'error' => 'Bad token']));
    }

    if (!Module::isInstalled(SamedayConstants::MODULE_NAME)) {
        header('Content-Type: application/json');
        die(json_encode(['success' => false, 'error' => 'Module not installed']));
    }

    /** @var SamedayCourier|false $module */
    $module = Module::getInstanceByName(SamedayConstants::MODULE_NAME);
    if (!$module || !$module->active) {
        header('Content-Type: application/json');
        die(json_encode(['success' => false, 'error' => 'Module not active']));
    }

    $bulkGridFeedback = static function (SamedayCourier $samedayModule, int $bulkOrderId): string {
        return $samedayModule->getBulkAwbGridFeedback($bulkOrderId);
    };

    if ($action === 'download_awb_pdf') {
        $orderId = (int) Tools::getValue('order_id');
        if ($orderId <= 0) {
            header('Content-Type: application/json');
            die(json_encode(['success' => false, 'error' => 'Missing order_id']));
        }

        $module->downloadAwbPdfForOrder($orderId);
    }

    if ($action === 'awb_history') {
        $awbId = (int) Tools::getValue('awb_id');
        if ($awbId <= 0) {
            header('Content-Type: application/json');
            die(json_encode(['success' => false, 'error' => 'Missing awb_id']));
        }

        try {
            die(json_encode($module->getAwbHistoryData($awbId)));
        } catch (Exception $e) {
            die(json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]));
        }
    }

    header('Content-Type: application/json');

    if ($action === 'clear_bulk_errors') {
        $orderIds = SamedayOrderBulkAwb::clearWithoutGeneratedAwb();
        die(json_encode([
            'success' => true,
            'deleted' => count($orderIds),
            'order_ids' => $orderIds,
        ]));
    }

    $orderId = (int) Tools::getValue('order_id');
    if ($orderId <= 0) {
        die(json_encode(['success' => false, 'error' => 'Missing order_id']));
    }

    if ($action === 'bulk_generate_awb') {
        SamedayOrderBulkAwb::bulkEntry($orderId);
        $result = $module->addAwbBulk($orderId);

        if (!empty($result['skipped'])) {
            $result['feedback'] = $bulkGridFeedback($module, $orderId);
            die(json_encode($result));
        }

        if (!empty($result['success'])) {
            SamedayOrderBulkAwb::updateFeedback($orderId, SamedayOrderBulkAwb::STATUS_SUCCESS, [
                'awb_number' => $result['awb_number'] ?? '',
            ]);
        } else {
            SamedayOrderBulkAwb::updateFeedback($orderId, SamedayOrderBulkAwb::STATUS_ERROR, [
                'error' => $result['error'] ?? 'Unknown error',
            ]);
        }

        $result['feedback'] = $bulkGridFeedback($module, $orderId);
        die(json_encode($result));
    }

    if ($action === 'bulk_remove_awb') {
        $result = $module->cancelAwbBulk($orderId);

        if (!empty($result['success'])) {
            SamedayOrderBulkAwb::deleteByOrderId($orderId);
        } else {
            SamedayOrderBulkAwb::updateFeedback($orderId, SamedayOrderBulkAwb::STATUS_ERROR, [
                'error' => $result['error'] ?? 'Unknown error',
            ]);
        }

        $result['feedback'] = $bulkGridFeedback($module, $orderId);
        die(json_encode($result));
    }
}

if (Tools::getValue('action') === 'change_county') {
    if (Tools::getValue('token') !== Tools::getAdminToken('Samedaycourier')) {
        die('Bad request!');
    }

    header('Content-Type: application/json');
    die(
        json_encode(
            [
                'cities' => (new SamedayApiHelper())->getSamedayCities(Tools::getValue('county_id'))
            ]
        )
    );
}

if (Tools::getValue('action') === 'store_locker') {
    if (Tools::getValue('token') !== Tools::getAdminToken('Samedaycourier')) {
        die('Bad request!');
    }

    $locker = json_decode(Tools::getValue('locker'), false);

    $locker = json_encode(
        [
            'locker_id' => $locker->locker_id,
            'locker_name' => $locker->locker_name,
            'locker_address' => $locker->locker_address,
            'ooh_type' => $locker->ooh_type,
        ],
        JSON_UNESCAPED_UNICODE
    );

    $samedayCart = new SamedayCart(Tools::getValue('idCart'));
    $samedayCart->sameday_locker = $locker;

    try {
        $samedayCart->save();
    } catch (Exception $exception) {
        die(json_encode(['message' => 'Something went wrong and locker could not be saved!']));
    }

    header('Content-Type: application/json');
    die(json_encode(['message' => 'Locker updated!']));
}

if (!Module::isInstalled(SamedayConstants::MODULE_NAME)
    || Tools::substr(Tools::encrypt(Configuration::get('SAMEDAY_CRON_TOKEN')), 0, 10) !== Tools::getValue('token')
) {
    die('Bad token');
}

if (Tools::getValue('awb_id')) {
    /** @var SamedayCourier|false $module */
    $module = Module::getInstanceByName(SamedayConstants::MODULE_NAME);
    if (!$module || !$module->active) {
        die('No records');
    }

    header('Content-Type: application/json');
    try {
        die(json_encode($module->getAwbHistoryData((int) Tools::getValue('awb_id'))));
    } catch (Exception $e) {
        die(json_encode([
            'success' => false,
            'error' => $e->getMessage(),
        ]));
    }
}

die('No records');
