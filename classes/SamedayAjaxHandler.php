<?php
/**
 * Shared AJAX / endpoint logic for legacy ajax.php and ModuleFrontController.
 */

class SamedayAjaxHandler
{
    /** @var string[] */
    public static $bulkActions = [
        'bulk_generate_awb',
        'bulk_remove_awb',
        'clear_bulk_errors',
        'download_awb_pdf',
        'awb_history',
    ];

    /**
     * @return string
     */
    public static function getRequestedAction()
    {
        return (string) Tools::getValue('action', '');
    }

    /**
     * @param string $action
     *
     * @return bool
     */
    public static function isBulkAction($action)
    {
        return in_array($action, self::$bulkActions, true);
    }

    /**
     * Dispatch based on request parameters. Never returns on success paths that die().
     *
     * @return void
     */
    public static function dispatch()
    {
        $action = self::getRequestedAction();

        if (self::isBulkAction($action)) {
            self::handleBulkAction($action);

            return;
        }

        if ($action === 'change_county') {
            self::handleChangeCounty();

            return;
        }

        if ($action === 'store_locker') {
            self::handleStoreLocker();

            return;
        }

        if (!Module::isInstalled(SamedayConstants::MODULE_NAME)
            || !SamedayTools::isValidCronUrlToken(Tools::getValue('token'))
        ) {
            die('Bad token');
        }

        if (Tools::getValue('awb_id')) {
            self::handleCronAwbHistory((int) Tools::getValue('awb_id'));

            return;
        }

        die('No records');
    }

    /**
     * @param string $action
     *
     * @return void
     */
    public static function handleBulkAction($action)
    {
        header('Content-Type: application/json');

        $token = (string) Tools::getValue('token');
        $authorized = false;

        // PS9: FO ModuleFrontController has no BO employee cookie — accept module AJAX token.
        if (SamedayTools::isPrestaShop9() && SamedayTools::isValidModuleAjaxToken($token)) {
            $authorized = true;
        }

        if (!$authorized) {
            $employee = self::resolveBackOfficeEmployee();
            if (!$employee || !(int) $employee->id) {
                die(json_encode(['success' => false, 'error' => 'Unauthorized']));
            }

            if ($token !== Tools::getAdminTokenLite('AdminOrders')) {
                die(json_encode(['success' => false, 'error' => 'Bad token']));
            }
        }

        if (!Module::isInstalled(SamedayConstants::MODULE_NAME)) {
            die(json_encode(['success' => false, 'error' => 'Module not installed']));
        }

        /** @var SamedayCourier|false $module */
        $module = Module::getInstanceByName(SamedayConstants::MODULE_NAME);
        if (!$module || !$module->active) {
            die(json_encode(['success' => false, 'error' => 'Module not active']));
        }

        if (!$module->isBulkAwbSupported()) {
            die(json_encode(['success' => false, 'error' => 'Not supported']));
        }

        if ($action === 'download_awb_pdf') {
            $orderId = (int) Tools::getValue('order_id');
            if ($orderId <= 0) {
                die(json_encode(['success' => false, 'error' => 'Missing order_id']));
            }

            $module->downloadAwbPdfForOrder($orderId);
        }

        if ($action === 'awb_history') {
            $awbId = (int) Tools::getValue('awb_id');
            if ($awbId <= 0) {
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
                $result['feedback'] = $module->getBulkAwbGridFeedback($orderId);
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

            $result['feedback'] = $module->getBulkAwbGridFeedback($orderId);
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

            $result['feedback'] = $module->getBulkAwbGridFeedback($orderId);
            die(json_encode($result));
        }

        die(json_encode(['success' => false, 'error' => 'Unknown action']));
    }

    /**
     * @return void
     */
    public static function handleChangeCounty()
    {
        if (!self::isValidFrontAjaxToken(Tools::getValue('token'))) {
            die('Bad request!');
        }

        header('Content-Type: application/json');
        die(json_encode([
            'cities' => (new SamedayApiHelper())->getSamedayCities(Tools::getValue('county_id')),
        ]));
    }

    /**
     * @return void
     */
    public static function handleStoreLocker()
    {
        if (!self::isValidFrontAjaxToken(Tools::getValue('token'))) {
            die('Bad request!');
        }

        $locker = self::parseLockerPayload(Tools::getValue('locker'));
        if (!$locker || empty($locker->locker_id)) {
            die(json_encode(['message' => 'Something went wrong and locker could not be saved!']));
        }

        $lockerPayload = json_encode(
            [
                'locker_id' => $locker->locker_id,
                'locker_name' => $locker->locker_name ?? '',
                'locker_address' => $locker->locker_address ?? '',
                'ooh_type' => isset($locker->ooh_type) ? $locker->ooh_type : 0,
            ],
            JSON_UNESCAPED_UNICODE
        );

        $samedayCart = new SamedayCart((int) Tools::getValue('idCart'));
        if (!(int) $samedayCart->id) {
            die(json_encode(['message' => 'Something went wrong and locker could not be saved!']));
        }

        $samedayCart->sameday_locker = $lockerPayload;

        try {
            if (!$samedayCart->save()) {
                die(json_encode(['message' => 'Something went wrong and locker could not be saved!']));
            }
        } catch (Exception $exception) {
            die(json_encode(['message' => 'Something went wrong and locker could not be saved!']));
        }

        header('Content-Type: application/json');
        die(json_encode(['message' => 'Locker updated!', 'success' => true]));
    }

    /**
     * Accept JSON string or already-decoded array from jQuery form encoding.
     *
     * @param mixed $raw
     *
     * @return object|null
     */
    private static function parseLockerPayload($raw)
    {
        if (is_array($raw)) {
            return (object) $raw;
        }

        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, false);

        return is_object($decoded) ? $decoded : null;
    }

    /**
     * @param int $awbId
     *
     * @return void
     */
    public static function handleCronAwbHistory($awbId)
    {
        /** @var SamedayCourier|false $module */
        $module = Module::getInstanceByName(SamedayConstants::MODULE_NAME);
        if (!$module || !$module->active) {
            die('No records');
        }

        header('Content-Type: application/json');
        try {
            die(json_encode($module->getAwbHistoryData($awbId)));
        } catch (Exception $e) {
            die(json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]));
        }
    }

    /**
     * FO locker/county tokens: module token on PS9, legacy AdminToken on older PS.
     *
     * @param string $token
     *
     * @return bool
     */
    private static function isValidFrontAjaxToken($token)
    {
        if (SamedayTools::isPrestaShop9()) {
            return SamedayTools::isValidModuleAjaxToken($token);
        }

        return (string) $token === (string) Tools::getAdminToken('Samedaycourier');
    }

    /**
     * Resolve BO employee when the request hits a front controller (PS9-safe).
     *
     * @return Employee|null
     */
    private static function resolveBackOfficeEmployee()
    {
        $context = Context::getContext();
        if (!empty($context->employee) && (int) $context->employee->id > 0) {
            if (method_exists($context->employee, 'isLoggedBack') && $context->employee->isLoggedBack()) {
                return $context->employee;
            }
            if (!method_exists($context->employee, 'isLoggedBack')) {
                return $context->employee;
            }
        }

        $employeeId = (int) Tools::getValue('employee_id');
        if ($employeeId <= 0) {
            return null;
        }

        $employee = new Employee($employeeId);
        if (!Validate::isLoadedObject($employee) || !(int) $employee->active) {
            return null;
        }

        $context->employee = $employee;

        return $employee;
    }
}
