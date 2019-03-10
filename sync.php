<?php
/**
 * 2007-2019 PrestaShop
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2019 PrestaShop SA
 * @license   http://addons.prestashop.com/en/content/12-terms-and-conditions-of-use
 * International Registered Trademark & Property of PrestaShop SA
 */

include(dirname(__FILE__) . '/libs/sameday-php-sdk/src/Sameday/autoload.php');
include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__).'/../../init.php');
include(dirname(__FILE__) . '/classes/SamedayAwbParcel.php');
include(dirname(__FILE__) . '/classes/SamedayConstants.php');

if (Tools::substr(Tools::encrypt(Configuration::get('SAMEDAY_CRON_TOKEN')), 0, 10) != Tools::getValue('token') ||
    !Module::isInstalled('sameday')
) {
    die('Bad token');
}

$now = new DateTime();
$lastSync = (int)Configuration::get('SAMEDAY_LAST_SYNC', 0);
if (!$lastSync) {
    $lastSync = $now->getTimestamp() - 7200;
}

$endTimestamp = $lastSync + 7200;

$api = Configuration::get('SAMEDAY_LIVE_MODE') ? SamedayConstants::API_URL_PROD : SamedayConstants::API_URL_DEMO;

$logger = new FileLogger(0);
$logger->setFilename(dirname(__FILE__) . '/log/' . date('Ymd') . '_sameday_sync.log');

$logger->log('Start sync', 0);
try {
    $sameday = new \Sameday\Sameday(
        new \Sameday\SamedayClient(
            Configuration::get('SAMEDAY_ACCOUNT_USER'),
            Configuration::get('SAMEDAY_ACCOUNT_PASSWORD'),
            $api,
            'Prestashop',
            _PS_VERSION_,
            'curl'
        )
    );
} catch (Exception $exception) {
    $logger->log($exception->getMessage(), 3);
}

$request = new \Sameday\Requests\SamedayGetStatusSyncRequest($lastSync, $endTimestamp);
$statuses = array();
$page = 1;

while (true) {
    $request->setPage($page++);
    try {
        $sync = $sameday->getStatusSync($request);
        if (!$sync->getStatuses()) {
            //no more statuses
            break;
        }

        /** @var \Sameday\Objects\StatusSync\StatusObject $status */
        foreach ($sync->getStatuses() as $status) {
            $statuses[$status->getParcelAwbNumber()][] = $status;
        }
    } catch (\Exception $e) {
        $logger->log($e->getMessage(), 3);
        break;
    }
}

foreach ($statuses as $awb => $statusSync) {
    $parcel = SamedayAwbParcel::findByAwbNumber($awb);
    if ($parcel) {
        SamedayAwbParcel::updateStatusSync($parcel['id'], $status);
    }
}

Configuration::updateValue('SAMEDAY_LAST_SYNC', $endTimestamp);


$logger->log('End sync', 0);

die('It works!');
