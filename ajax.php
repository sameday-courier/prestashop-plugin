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
include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__).'/../../init.php');
include(dirname(__FILE__) . '/classes/SamedayAwbParcel.php');
include(dirname(__FILE__) . '/classes/SamedayAwbParcelHistory.php');
include(dirname(__FILE__) . '/classes/SamedayConstants.php');
include(dirname(__FILE__) . '/classes/SamedayPersistenceDataHandler.php');


if (Tools::substr(Tools::encrypt(Configuration::get('SAMEDAY_CRON_TOKEN')), 0, 10) != Tools::getValue('token') ||
    !Module::isInstalled('samedaycourier')
) {
    die('Bad token');
}

if (Tools::getValue('updateStatus') == 'true') {
    include(dirname(__FILE__) . '/classes/SamedayOrderLocker.php');
    $lockerDetails = json_decode(Tools::getValue('lockerDetails'), true);
    $lockerId = $lockerDetails['id'];
    $lockerName = $lockerDetails['name'];
    $lockerAddress = $lockerDetails['address'];

    $orderLocker = new SamedayOrderLocker(Tools::getValue('samedayOrderLockerId'));
    $orderLocker->id_locker = $lockerId;
    $orderLocker->name_locker = $lockerName;
    $orderLocker->address_locker = $lockerAddress;

    $orderLocker->save();

    die($lockerId);
}
if (Tools::getValue('awb_id')) {
    $awbId = (int)Tools::getValue('awb_id');
    $country = (Configuration::get('SAMEDAY_HOST_COUNTRY')) ? Configuration::get('SAMEDAY_HOST_COUNTRY') : 'ro';
    $testingMode = (Configuration::get('SAMEDAY_LIVE_MODE')) ? Configuration::get('SAMEDAY_LIVE_MODE') : '0';
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
