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

$bulkActions = ['bulk_generate_awb', 'bulk_remove_awb', 'clear_bulk_errors'];
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
    header('Content-Type: application/json');

    $employee = Context::getContext()->employee;
    if (!$employee || !(int) $employee->id || !$employee->isLoggedBack()) {
        die(json_encode(['success' => false, 'error' => 'Unauthorized']));
    }

    if (Tools::getValue('token') !== Tools::getAdminTokenLite('AdminOrders')) {
        die(json_encode(['success' => false, 'error' => 'Bad token']));
    }

    if (!Module::isInstalled(SamedayConstants::MODULE_NAME)) {
        die(json_encode(['success' => false, 'error' => 'Module not installed']));
    }

    /** @var SamedayCourier|false $module */
    $module = Module::getInstanceByName(SamedayConstants::MODULE_NAME);
    if (!$module || !$module->active) {
        die(json_encode(['success' => false, 'error' => 'Module not active']));
    }

    $bulkGridFeedback = static function (SamedayCourier $samedayModule, int $bulkOrderId): string {
        $bulkRows = SamedayOrderBulkAwb::getByOrderIds([$bulkOrderId]);
        $awbRows = SamedayAwb::getByOrderIds([$bulkOrderId]);

        return SamedayOrderBulkAwb::formatForGrid(
            $bulkRows[$bulkOrderId] ?? null,
            $awbRows[$bulkOrderId] ?? null,
            $samedayModule
        );
    };

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
    $awbId = (int)Tools::getValue('awb_id');
    $country = (Configuration::get('SAMEDAY_HOST_COUNTRY')) ?: SamedayConstants::API_HOST_LOCALE_RO;
    $testingMode = (Configuration::get('SAMEDAY_LIVE_MODE')) ?: '0';
    $api = $testingMode ? SamedayConstants::SAMEDAY_ENVS[$country]['API_URL_PROD'] : SamedayConstants::SAMEDAY_ENVS[$country]['API_URL_DEMO'];

    $sameday = new \Sameday\Sameday(
        new \Sameday\SamedayClient(
            Configuration::get('SAMEDAY_ACCOUNT_USER'),
            Configuration::get('SAMEDAY_ACCOUNT_PASSWORD'),
            $api,
            'Prestashop',
            _PS_VERSION_,
            'curl',
            new SamedayPersistenceDataHandler()
        )
    );

    $parcels = SamedayAwbParcel::findParcelsByAwbId($awbId);
    $summaries = array();
    $histories = array();
    foreach ($parcels as $parcel) {
        $request = new \Sameday\Requests\SamedayGetParcelStatusHistoryRequest($parcel['awb_number']);
        /** @var \Sameday\Responses\SamedayGetParcelStatusHistoryResponse $response */
        $response = $sameday->getParcelStatusHistory($request);
        $history = SamedayAwbParcelHistory::findByAwbNumber($parcel['awb_number']);
        if ($history) {
            $history = new SamedayAwbParcelHistory($parcel['id']);
        } else {
            $history = new SamedayAwbParcelHistory();
            $history->awb_number = $parcel['awb_number'];
        }
        $history->summary = serialize($response->getSummary());
        $history->history = serialize($response->getHistory());
        $history->expedition = serialize($response->getExpeditionStatus());
        $history->save();
        $summaries[$parcel['awb_number']] = array(
            'weight' => $response->getSummary()->getParcelWeight(),
            'delivered' => $response->getSummary()->isDelivered() ? 'Da' : 'Nu',
            'deliveredAttempts' => $response->getSummary()->getDeliveryAttempts(),
            'isPickedUp' => $response->getSummary()->isPickedUp() ? 'Da' : 'Nu',
            'isPickedUpAt' => $response->getSummary()->getPickedUpAt() ?: '',
        );

        /** @var \Sameday\Objects\ParcelStatusHistory\HistoryObject $responsHistory */
        foreach ($response->getHistory() as $historyObject) {
            $histories[$parcel['awb_number']][] = array(
                'name'    => $historyObject->getName(),
                'label'   => $historyObject->getLabel(),
                'state'   => $historyObject->getState(),
                'date'    => $historyObject->getDate(),
                'county'  => $historyObject->getCounty(),
                'transit' => $historyObject->getTransitLocation(),
                'reason'  => $historyObject->getReason()
            );
        }
    }

    die(json_encode(array('summary' => $summaries, 'histories' => $histories)));
}

die('No records');
