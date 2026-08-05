<?php
/**
 * Shared status-sync cron logic for legacy sync.php and ModuleFrontController.
 */

class SamedaySyncHandler
{
    /**
     * @return void
     */
    public static function run()
    {
        if (!SamedayTools::isValidCronUrlToken(Tools::getValue('token'))
            || !Module::isInstalled(SamedayConstants::MODULE_NAME)
        ) {
            die('Bad token');
        }

        $now = new DateTime();
        $lastSync = (int) Configuration::get('SAMEDAY_LAST_SYNC', 0);
        if (!$lastSync) {
            $lastSync = $now->getTimestamp() - 7200;
        }

        $endTimestamp = $lastSync + 7200;

        $country = Configuration::get('SAMEDAY_HOST_COUNTRY') ?: 'ro';
        $testingMode = (int) (Configuration::get('SAMEDAY_LIVE_MODE') ?: 0);
        $api = SamedayConstants::SAMEDAY_ENVS[$country][$testingMode]
            ?? SamedayConstants::SAMEDAY_ENVS['ro'][SamedayConstants::DEMO_MODE];

        $logger = new FileLogger(0);
        $logDir = dirname(__DIR__) . '/log';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $logger->setFilename($logDir . '/' . date('Ymd') . '_sameday_sync.log');

        $logger->log('Start sync', 0);
        try {
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
        } catch (Exception $exception) {
            $logger->log($exception->getMessage(), 3);
            die('Sync failed');
        }

        $request = new \Sameday\Requests\SamedayGetStatusSyncRequest($lastSync, $endTimestamp);
        $statuses = [];
        $page = 1;

        while (true) {
            $request->setPage($page++);
            try {
                $sync = $sameday->getStatusSync($request);
                if (!$sync->getStatuses()) {
                    break;
                }

                /** @var \Sameday\Objects\StatusSync\StatusObject $status */
                foreach ($sync->getStatuses() as $status) {
                    $statuses[$status->getParcelAwbNumber()][] = $status;
                }
            } catch (Exception $e) {
                $logger->log($e->getMessage(), 3);
                break;
            }
        }

        foreach ($statuses as $awb => $statusSync) {
            $parcel = SamedayAwbParcel::findByAwbNumber($awb);
            if ($parcel) {
                // Preserve historical behavior: last status object from the sync batch.
                $lastStatus = end($statusSync);
                SamedayAwbParcel::updateStatusSync($parcel['id'], $lastStatus);
            }
        }

        Configuration::updateValue('SAMEDAY_LAST_SYNC', $endTimestamp);

        $logger->log('End sync', 0);

        die('It works!');
    }
}
