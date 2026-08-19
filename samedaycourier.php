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

include(__DIR__ . '/libs/sameday-php-sdk/src/Sameday/autoload.php');
include (__DIR__ . '/classes/autoload.php');

/**
 * Class SamedayCourier
 */
class SamedayCourier extends CarrierModule
{
    /**
     * @var SamedayGeneralHelper $generalHelper
     */
    protected $generalHelper;

    /**
     * @var SamedayApiHelper $samedayApiHelper
     */
    protected $samedayApiHelper;

    /**
     * @var array
     */
    protected $_errors = [];

    /**
     * @var string
     */
    protected $currentIndex;

    /**
     * @var string
     */
    protected $html;

    /**
     * @var FileLogger
     */
    protected $logger;

    /**
     * @var array
     */
    protected $messages;

    /**
     * @var string
     */
    protected $ajaxRoute;

    /**
     * @var array
     */
    protected $servicePriceCache = array();

    /**
     * @var int
     */
    public $id_carrier;

    const TEMPLATE_VERSION = [
        '1.6' => [
            'locker_options_map' => 'checkout_lockers.v16.tpl',
            'locker_options_selector' => 'checkout_lockers_selector.v16.tpl',
            'open_package_option' => 'checkout_open_package.v16.tpl',
        ],
        '1.7' => [
            'locker_options_map' => 'checkout_lockers.v17.tpl',
            'locker_options_selector' => 'checkout_lockers_selector.v17.tpl',
            'open_package_option' => 'checkout_open_package.v17.tpl',
        ]
    ];

    const DEFAULT_VALUE_LOCKER_MAX_ITEMS = 5;
    const DEFAULT_HOST_COUNTRY = 'ro';
    private static $COD = null;
    private static $COD_CACHE_KEY = null;
    public static function getCOD()
    {
        // Get host country for default values
        $hostCountry = Configuration::get('SAMEDAY_HOST_COUNTRY');
        if (empty($hostCountry)) {
            $hostCountry = SamedayConstants::DEFAULT_HOST_COUNTRY;
        }

        // Create cache key that includes host country to handle country changes
        $cacheKey = $hostCountry . '_' . Configuration::get('SAMEDAY_COD_REFERENCES');

        // Reset cache if host country or COD references changed
        if (self::$COD === null || self::$COD_CACHE_KEY !== $cacheKey) {
            $codReferences = Configuration::get('SAMEDAY_COD_REFERENCES');

            // Get country-specific defaults
            $defaultCod = SamedayConstants::COD_DEFAULTS[$hostCountry] ?? SamedayConstants::COD_DEFAULTS[SamedayConstants::DEFAULT_HOST_COUNTRY];

            // If empty, use default values based on host country
            if (empty($codReferences)) {
                self::$COD = $defaultCod;
                self::$COD_CACHE_KEY = $cacheKey;
                return self::$COD;
            }

            // Try to decode as JSON first (if stored as JSON array)
            $decoded = json_decode($codReferences, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                self::$COD = $decoded;
            } else {
                // If not JSON, treat as comma-separated string
                $codArray = array_map('trim', explode(',', $codReferences));
                self::$COD = array_filter($codArray); // Remove empty values

                // If still empty after processing, use defaults based on host country
                if (empty(self::$COD)) {
                    self::$COD = $defaultCod;
                }
            }

            self::$COD_CACHE_KEY = $cacheKey;
        }
        return self::$COD;
    }

    /**
     * Get COD references formatted for form display (comma-separated string)
     *
     * @return string
     */
    private function getCodReferencesForForm(): string
    {
        $codReferences = Configuration::get('SAMEDAY_COD_REFERENCES');

        // Get host country for default values
        $hostCountry = $this->generalHelper->getHostCountry();

        // Get country-specific defaults
        $defaultCod = SamedayConstants::COD_DEFAULTS[$hostCountry] ?? SamedayConstants::COD_DEFAULTS[SamedayConstants::DEFAULT_HOST_COUNTRY];

        if (empty($codReferences)) {
            return implode(', ', $defaultCod);
        }

        // Try to decode as JSON first
        $decoded = json_decode($codReferences, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return implode(', ', $decoded);
        }

        // If not JSON, return as is (already comma-separated)
        return $codReferences;
    }

    const CURRENCIES = [
        'RON' => SamedayConstants::API_HOST_LOCALE_RO,
        'HUF' => SamedayConstants::API_HOST_LOCALE_HU,
        'EUR' => SamedayConstants::API_HOST_LOCALE_BG,
    ];

    /**
     * SamedayCourier constructor.
     */
    public function __construct()
    {
        $this->name = 'samedaycourier';
        $this->tab = 'shipping_logistics';

        $this->version = '1.8.9';
        $this->author = 'Sameday Courier';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->module_key = 'db5f332a6ba61a4cc18c00b74c78137d';

        parent::__construct();

        $this->displayName = $this->l('Sameday Courier');
        $this->description = $this->l('Shipping module for Sameday Courier.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall this module?');
        $this->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->logger = new FileLogger(0);
        $this->logger->setFilename(__DIR__ . '/log/' . md5(date('Ymd')) . '_sameday.log');
        $this->messages = array();
        $this->ajaxRoute = SamedayTools::getEndpointUrl('ajax', 'ajax.php', [
            'token' => SamedayTools::getCronUrlToken(),
        ]);
        $this->generalHelper = new SamedayGeneralHelper();
        $this->samedayApiHelper = new SamedayApiHelper();
    }

    private function getMajorVersion(): int
    {
        return (int) explode('.', _PS_VERSION_, 3)[0];
    }

    /**
     * @return string
     */
    private function baseUrl(): string
    {
        return Tools::getShopDomainSsl(true);
    }

    /**
     * @return int
     */
    private function getMinorVersion(): int
    {
        return (int) explode('.', _PS_VERSION_, 3)[1];
    }

    /**
     * Bulk AWB toolbar and AJAX (PS 1.6+ legacy list, 1.7.7+ Symfony grid).
     *
     * @return bool
     */
    public function isBulkAwbSupported()
    {
        return version_compare(_PS_VERSION_, '1.6', '>=');
    }

    /**
     * Bulk AWB feedback column requires the Symfony order grid (PS 1.7.7+).
     *
     * @return bool
     */
    public function isBulkAwbGridSupported()
    {
        return version_compare(_PS_VERSION_, '1.7.7', '>=');
    }

    /**
     * Legacy AdminOrders list toolbar path (PS 1.6 and PS 1.7.0–1.7.6).
     *
     * @return bool
     */
    public function isBulkAwbLegacyOrdersListSupported()
    {
        return $this->isBulkAwbSupported() && !$this->isBulkAwbGridSupported();
    }

    /**
     * Feedback column (legacy list or Symfony grid). Omitted on PS 1.6.
     *
     * @return bool
     */
    public function isBulkAwbFeedbackColumnSupported()
    {
        return version_compare(_PS_VERSION_, '1.7.0', '>=');
    }

    /**
     * Feedback column on the legacy AdminOrders list (PS 1.7.0–1.7.6 only).
     *
     * @return bool
     */
    public function isBulkAwbLegacyFeedbackColumnSupported()
    {
        return $this->isBulkAwbFeedbackColumnSupported()
            && $this->isBulkAwbLegacyOrdersListSupported();
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        if (extension_loaded('curl') === false) {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');

            return false;
        }

        Configuration::updateValue('SAMEDAY_CRON_TOKEN', uniqid('', ''));

        include(__DIR__ . '/sql/install.php');

        $hookDisplayAdminOrder = 'displayAdminOrderSide';
        $hookExtraCarrier = 'displayCarrierExtraContent';
        $hookHeader = 'displayHeader';
        if (($this->getMajorVersion() === 1) && ($this->getMinorVersion() === 6)) {
            $hookDisplayAdminOrder = 'displayAdminOrderContentShip';
            $hookExtraCarrier = 'extraCarrier';
            $hookHeader = 'Header';
        }

        $result = parent::install()
            && $this->registerHook('actionCarrierUpdate')
            && $this->registerHook('displayAdminAfterHeader')
            && $this->registerHook('actionValidateOrder')
            && $this->registerHook('actionCarrierProcess')
            && $this->registerHook('actionValidateStepComplete')
            && $this->registerHook($hookDisplayAdminOrder)
            && $this->registerHook($hookExtraCarrier)
            && $this->registerHook($hookHeader);

        if ($this->isBulkAwbSupported()) {
            $result = $result
                && $this->registerHook('displayBackOfficeTop')
                && $this->registerHook('actionAdminControllerSetMedia');
        }

        if ($this->isBulkAwbGridSupported()) {
            $result = $result
                && $this->registerHook('actionOrderGridDefinitionModifier')
                && $this->registerHook('actionOrderGridDataModifier');
        }

        if ($this->isBulkAwbLegacyFeedbackColumnSupported()) {
            $result = $result
                && $this->registerHook('actionAdminOrdersListingFieldsModifier')
                && $this->registerHook('actionAdminOrdersListingResultsModifier');
        }

        return $result;
    }

    /**
     * @return bool
     */
    public function uninstall()
    {
        Configuration::deleteByName('SAMEDAY_LIVE_MODE');
        Configuration::deleteByName('SAMEDAY_TOKEN');
        Configuration::deleteByName('SAMEDAY_DELIVERY_FEE');
        Configuration::deleteByName('SAMEDAY_ACCOUNT_PASSWORD');
        Configuration::deleteByName('SAMEDAY_ACCOUNT_USER');
        Configuration::deleteByName('SAMEDAY_CRON_TOKEN');
        Configuration::deleteByName('SAMEDAY_DEBUG_MODE');
        Configuration::deleteByName('SAMEDAY_ESTIMATED_COST');
        Configuration::deleteByName('SAMEDAY_OPEN_PACKAGE');
        Configuration::deleteByName('SAMEDAY_USE_CITIES_NOMENCLATURE');
        Configuration::deleteByName('SAMEDAY_LOCKERS_MAP');
        Configuration::deleteByName('SAMEDAY_OPEN_PACKAGE_LABEL');
        Configuration::deleteByName('SAMEDAY_LOCKER_MAX_ITEMS');
        Configuration::deleteByName('SAMEDAY_AWB_PDF_FORMAT');
        Configuration::deleteByName('SAMEDAY_LAST_SYNC');
        Configuration::deleteByName('SAMEDAY_STATUS_MODE');
        Configuration::deleteByName('SAMEDAY_LAST_LOCKERS');
        Configuration::deleteByName('SAMEDAY_TOKEN');
        Configuration::deleteByName('SAMEDAY_TOKEN_EXPIRES_AT');
        Configuration::deleteByName('SAMEDAY_COD_REFERENCES');

        $queryHelper = new SamedayGeneralQueryHelper();
        if ($queryHelper->isTableExists(_DB_PREFIX_ . SamedayService::TABLE_NAME)) {
            $services = SamedayService::getAllServices();
            if (is_array($services)) {
                foreach ($services as $service) {
                    Configuration::deleteByName($this->getCarrierKey($service['code']));
                    if (!empty($service['id_carrier'])) {
                        $carrier = new Carrier((int) $service['id_carrier']);
                        if (Validate::isLoadedObject($carrier)) {
                            $carrier->delete();
                        }
                    }
                }
            }
        }

        include __DIR__ . '/sql/uninstall.php';

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */

    public function getContent()
    {
        $this->html = '';

        $this->postProcess();

        if (Tools::isSubmit('updatesameday_services')) {
            return $this->renderServiceForm();
        }

        if (Tools::isSubmit('deletesameday_pickup_points')) {
            $this->deletePickupPoint();
        }

        $this->context->smarty->assign('module_dir', $this->_path);
        $this->context->smarty->assign('action_url', $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name);

        $this->renderForm();

        if (Configuration::get('SAMEDAY_USE_CITIES_NOMENCLATURE')) {
            $this->renderSamedayCitiesImportButton();
        }

        $this->renderServicesList();
        $this->renderPickupPointsList();
        $this->renderLockersList();

        if (Configuration::get('SAMEDAY_LIVE_MODE', 0) === 0) {
            $this->addMessage('warning', $this->l('Module Sameday Courier is working in testing mode'));
        }

        return $this->html;
    }

    private function importCities()
    {
        if (!file_exists($file = __DIR__ . '/utils/cities.json')) {
            return;
        }

        if (false === ($json = file_get_contents($file))) {
            return;
        }

        $samedayCities = json_decode($json, true);
        foreach ($samedayCities as $samedayCity) {
            if (false === $city = SamedayCity::findByCityId($samedayCity['city_id'])) {
                $city = new SamedayCity();
            } else {
                $city = new SamedayCity($city['id']);
            }

            $city->city_id = $samedayCity['city_id'];
            $city->city_name = $samedayCity['city_name'];
            $city->county_code = $samedayCity['county_code'];
            $city->country_code = $samedayCity['country_code'];
            $city->postal_code = $samedayCity['postal_code'];

            $city->save();
        }

        /**
         * After import, store cities in Cache in order to use them
         */
        Cache::getInstance()->set('sameday_cities', SamedayCity::getCities());

        $this->addMessage('success', $this->l('All cities have been imported!'));
    }

    /**
     * @throws Sameday\Exceptions\SamedaySDKException
     */
    private function importServices()
    {
        $client = $this->samedayApiHelper->getSamedayClient();

        $remoteServices = [];
        $page = 1;

        do {
            $servicesRequest = new \Sameday\Requests\SamedayGetServicesRequest();
            $servicesRequest->setPage($page++);

            if (Configuration::get('SAMEDAY_DEBUG_MODE', 0)) {
                $this->log('Import services', SamedayConstants::DEBUG);
                $this->log($servicesRequest, SamedayConstants::DEBUG);
            }

            $sameday = new \Sameday\Sameday($client);

            $lockerService = null;
            try {
                $response = $sameday->getServices($servicesRequest);
                SamedayService::deactivateAllServices();

                foreach ($response->getServices() as $service) {
                    $oldService = SamedayService::findByCode($service->getCode());
                    $optionalTaxes = null;
                    if (!empty($service->getOptionalTaxes())) {
                        foreach ($service->getOptionalTaxes() as $optionalTaxObject) {
                            $optionalTaxes[] = array(
                                'id' => $optionalTaxObject->getId(),
                                'type' => $optionalTaxObject->getPackageType()->getType(),
                                'code' => $optionalTaxObject->getCode()
                            );
                        }
                    }

                    $optionalTaxes = null !== $optionalTaxes ? serialize($optionalTaxes) : '';

                    if (!$oldService) {
                        $samedayService = new SamedayService();
                        $samedayService->id_service = $service->getId();
                        $samedayService->name = $service->getName();
                        $samedayService->code = $service->getCode();
                        $samedayService->delivery_type = $service->getDeliveryType()->getId();
                        $samedayService->delivery_type_name = $service->getDeliveryType()->getName();
                        $samedayService->live_mode = (int) Configuration::get('SAMEDAY_LIVE_MODE', 0);
                        $samedayService->service_optional_taxes = $optionalTaxes;
                        $samedayService->save();
                    } else {
                        SamedayService::updateService($service->getCode(), $optionalTaxes, $oldService['id']);

                        if ($oldService['code'] === SamedayConstants::LOCKER_NEXT_DAY_CODE) {
                            $lockerService = $oldService;
                        }
                    }

                    // Save as current sameday service.
                    $remoteServices[] = $service->getId();
                }

            } catch (Exception $e) {
                $this->addMessage('danger', $e->getMessage());
                $this->log($e->getMessage(), SamedayConstants::ERROR);

                return;
            }
        } while ($page <= $response->getPages());

        // Build array of local services.
        $localServices = array_map(
            static function ($oldService) {
                return array(
                    'id' => $oldService['id'],
                    'id_service' => $oldService['id_service']
                );
            },

            SamedayService::getServices()
        );

        // Delete local services that aren't present in remote services anymore.
        foreach ($localServices as $localService) {
            if (!in_array((int) $localService['id_service'], $remoteServices, true)) {
                SamedayService::deleteService($localService['id']);
            }
        }

        // Update PUDO status to be same as Locker NextDay
        if (null !== $lockerService) {
            if (false !== $pudoService = SamedayService::findByCode(SamedayConstants::PUDO_CODE) ?? false) {
                $pudoService['status'] = $lockerService['status'];

                SamedayService::updateServiceStatus($pudoService);
            }
        }

        $this->addMessage('success', $this->l('The services were successfully imported'));
    }

    /**
     * @return void
     *
     * @throws PrestaShopException
     */
    private function processSaveSamedayService()
    {
        $id = Tools::getValue('id');
        $service = new SamedayService($id);
        $service->name = Tools::getValue('name');
        if ($this->generalHelper->isOohDeliveryOption($service->code)) {
            $service->name = SamedayConstants::OOH_SERVICES_LABELS[$this->generalHelper->getHostCountry()];
        }
        $service->price = (float) Tools::getValue('price');
        $service->free_delivery = (bool) Tools::getValue('free_delivery');
        $service->free_shipping_threshold = (float) Tools::getValue('free_shipping_threshold');
        $service->status = Tools::getValue('status');
        if (true === $service->validateFields()) {
            $service->save();

            // Update PUDO status to be same as Locker NextDay
            if ($service->code === SamedayConstants::LOCKER_NEXT_DAY_CODE) {
                if (false !== $pudoService = SamedayService::findByCode(SamedayConstants::PUDO_CODE) ?? false) {
                    $pudoService['status'] = $service->status;

                    SamedayService::updateServiceStatus($pudoService);
                }
            }

            $this->html .= $this->displayConfirmation($this->l('Sameday service updated'));
        } else {
            $this->html .= $this->displayError($this->l('An error occurred while attempting to update Sameday service'));
        }
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    private function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submit_sameday';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages'    => $this->context->controller->getLanguages(),
            'id_language'  => $this->context->language->id,
        );

        $this->html .= $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend'  => array(
                    'title' => $this->l('Settings'),
                    'icon'  => 'icon-cogs',
                ),
                'input'   => array(
                    array(
                        'col'    => 2,
                        'type'   => 'text',
                        'prefix' => '<i class="icon icon-user"></i>',
                        'name'   => 'SAMEDAY_ACCOUNT_USER',
                        'label'  => $this->l('User'),
                    ),
                    array(
                        'col'    => 2,
                        'type'   => 'password',
                        'prefix' => '<i class="icon icon-lock"></i>',
                        'name'   => 'SAMEDAY_ACCOUNT_PASSWORD',
                        'label'  => $this->l('Password'),
                    ),
                    array(
                        'type'  => 'select',
                        'name'  => 'SAMEDAY_AWB_PDF_FORMAT',
                        'label' => $this->l('AWB format'),
                        'options'  => array(
                            'query' => array(
                                array('id' => \Sameday\Objects\Types\AwbPdfType::A4, 'name' => 'A4'),
                                array('id' => \Sameday\Objects\Types\AwbPdfType::A6, 'name' => 'A6'),
                            ),
                            'id' => 'id',
                            'name' => 'name'
                        ),
                    ),
                    array(
                        'type'    => 'switch',
                        'label'   => $this->l('Use estimated cost'),
                        'name'    => 'SAMEDAY_ESTIMATED_COST',
                        'is_bool' => true,
                        'values'  => array(
                            array(
                                'id'    => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled'),
                            ),
                            array(
                                'id'    => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled'),
                            ),
                        ),
                    ),
                    array(
                        'type'    => 'switch',
                        'label'   => $this->l('Delivery method enabled'),
                        'name'    => 'SAMEDAY_STATUS_MODE',
                        'is_bool' => true,
                        'values'  => array(
                            array(
                                'id'    => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled'),
                            ),
                            array(
                                'id'    => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled'),
                            ),
                        ),
                    ),
                    array(
                        'type'    => 'switch',
                        'label'   => $this->l('Open package'),
                        'name'    => 'SAMEDAY_OPEN_PACKAGE',
                        'is_bool' => true,
                        'desc'    => $this->l('Enable this option if you want your client to open the package at delivery'),
                        'values'  => array(
                            array(
                                'id'    => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled'),
                            ),
                            array(
                                'id'    => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled'),
                            ),
                        ),
                    ),
                    array(
                        'type'    => 'switch',
                        'label'   => $this->l('Use Sameday Cities Nomenclature'),
                        'name'    => 'SAMEDAY_USE_CITIES_NOMENCLATURE',
                        'is_bool' => true,
                        'desc'    => $this->l('Enable this option if you want to use sameday 
                            cities nomenclature in your Checkout form. 
                            Note! If you enable this, please Proceed to import cities nomenclature!'
                        ),
                        'values'  => array(
                            array(
                                'id'    => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled'),
                            ),
                            array(
                                'id'    => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled'),
                            ),
                        ),
                    ),
                    array(
                        'type'    => 'select',
                        'label'   => $this->l('Show locker map method'),
                        'name'    => 'SAMEDAY_LOCKERS_MAP',
                        'desc'    => $this->l('This will show in the checkout page of your site as a drop-down list or 
                        as interactive map'),
                        'options'  => array(
                            'query' => array(
                                array('id' => 1, 'name' => 'Interactive map'),
                                array('id' => 0, 'name' => 'Drop-down list'),
                            ),
                            'id' => 'id',
                            'name' => 'name'
                        ),
                    ),
                    array(
                        'col'    => 2,
                        'type'   => 'text',
                        'name'   => 'SAMEDAY_OPEN_PACKAGE_LABEL',
                        'desc'   => $this->l('This will be shown on checkout page'),
                        'label'  => $this->l('Open package label'),
                    ),
                    array(
                        'col'    => 2,
                        'type'   => 'text',
                        'name'   => 'SAMEDAY_LOCKER_MAX_ITEMS',
                        'desc'   => $this->l('Set the maximum amount of items to fit in locker! 
                            In order to work Locker NextDay service do not leave this field blank !!'
                        ),
                        'label'  => $this->l('Locker max. items'),
                    ),
                    array(
                        'col'    => 5,
                        'type'   => 'text',
                        'name'   => 'SAMEDAY_COD_REFERENCES',
                        'desc'   => $this->l('Add third party COD (cash on delivery) references. Default: Cod, Ramburs. Delimited by comma'),
                        'label'  => $this->l('COD References'),
                    ),
                    array(
                        'type'    => 'switch',
                        'label'   => $this->l('Debug'),
                        'name'    => 'SAMEDAY_DEBUG_MODE',
                        'is_bool' => true,
                        'values'  => array(
                            array(
                                'id'    => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled'),
                            ),
                            array(
                                'id'    => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled'),
                            ),
                        ),
                    ),
                ),
                'submit'  => array(
                    'title' => $this->l('Save'),
                )
            ),
        );
    }

    /**
     * Config keys stored as 0/1 in ps_configuration.
     *
     * @return string[]
     */
    private function getBooleanConfigKeys()
    {
        return [
            'SAMEDAY_STATUS_MODE',
            'SAMEDAY_ESTIMATED_COST',
            'SAMEDAY_OPEN_PACKAGE',
            'SAMEDAY_USE_CITIES_NOMENCLATURE',
            'SAMEDAY_LOCKERS_MAP',
            'SAMEDAY_DEBUG_MODE',
        ];
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        $lockerMaxItems = Tools::getValue(
            'SAMEDAY_LOCKER_MAX_ITEMS',
            Configuration::get('SAMEDAY_LOCKER_MAX_ITEMS', null)
        );

        if (false === $lockerMaxItems || $lockerMaxItems === '') {
            $lockerMaxItems = self::DEFAULT_VALUE_LOCKER_MAX_ITEMS;
        }

        // HelperForm password inputs always POST empty; keep stored password for login/display defaults.
        $postedPassword = Tools::getValue('SAMEDAY_ACCOUNT_PASSWORD');
        if ($postedPassword === false || $postedPassword === null || $postedPassword === '') {
            $password = Configuration::get('SAMEDAY_ACCOUNT_PASSWORD');
        } else {
            $password = $postedPassword;
        }

        $values = array(
            'SAMEDAY_STATUS_MODE'      => Tools::getValue(
                'SAMEDAY_STATUS_MODE',
                Configuration::get('SAMEDAY_STATUS_MODE', 0)
            ),
            'SAMEDAY_ACCOUNT_USER'     => Tools::getValue(
                'SAMEDAY_ACCOUNT_USER',
                Configuration::get('SAMEDAY_ACCOUNT_USER', null)
            ),
            'SAMEDAY_ACCOUNT_PASSWORD' => $password,
            'SAMEDAY_ESTIMATED_COST' => Tools::getValue(
                'SAMEDAY_ESTIMATED_COST',
                Configuration::get('SAMEDAY_ESTIMATED_COST', 0)
            ),
            'SAMEDAY_OPEN_PACKAGE' => Tools::getValue(
                'SAMEDAY_OPEN_PACKAGE',
                Configuration::get('SAMEDAY_OPEN_PACKAGE', 0)
            ),
            'SAMEDAY_USE_CITIES_NOMENCLATURE' => Tools::getValue(
                'SAMEDAY_USE_CITIES_NOMENCLATURE',
                Configuration::get('SAMEDAY_USE_CITIES_NOMENCLATURE', 0)
            ),
            'SAMEDAY_LOCKERS_MAP' => Tools::getValue(
                'SAMEDAY_LOCKERS_MAP',
                Configuration::get('SAMEDAY_LOCKERS_MAP', 0)
            ),
            'SAMEDAY_OPEN_PACKAGE_LABEL' => Tools::getValue(
                'SAMEDAY_OPEN_PACKAGE_LABEL',
                Configuration::get('SAMEDAY_OPEN_PACKAGE_LABEL', null)
            ),
            'SAMEDAY_LOCKER_MAX_ITEMS' => $lockerMaxItems,
            'SAMEDAY_DEBUG_MODE'       => Tools::getValue(
                'SAMEDAY_DEBUG_MODE',
                Configuration::get('SAMEDAY_DEBUG_MODE', 0)
            ),
            'SAMEDAY_COD_REFERENCES' => Tools::getValue(
                'SAMEDAY_COD_REFERENCES',
                $this->getCodReferencesForForm()
            ),
            'SAMEDAY_AWB_PDF_FORMAT'   => Tools::getValue(
                'SAMEDAY_AWB_PDF_FORMAT',
                Configuration::get('SAMEDAY_AWB_PDF_FORMAT', null)
            ),
        );

        foreach ($this->getBooleanConfigKeys() as $boolKey) {
            $values[$boolKey] = (int) $values[$boolKey];
        }

        return $values;
    }

    /**
     * @return mixed
     */
    private function renderSamedayCitiesImportButton()
    {
        $this->context->smarty->assign([
            'importCitiesLabel' => $this->l('Import cities'),
            'href' => $this->currentIndex . '&import_cities&token=' . Tools::getAdminTokenLite('AdminModules'),
        ]);

        $this->html .= $this->display(__FILE__, 'views/templates/admin/importCities.tpl');
    }

    /**
     * @return void
     */
    private function renderServicesList()
    {
        $services = SamedayService::getServicesToDisplay();

        $fields = array(
            'name' => array(
                'title'   => $this->l('Name'),
                'orderby' => false,
                'hint' => $this->l(SamedayConstants::OOH_POPUP_TITLE[$this->generalHelper->getHostCountry()]),
                'tooltip' => 'Tooltip',
            ),
            'code'                    => array(
                'title'   => $this->l('Service code'),
                'orderby' => false,
            ),
            'delivery_type_name'      => array(
                'title'   => $this->l('Delivery type'),
                'orderby' => false,
            ),
            'price'                   => array(
                'title'   => $this->l('Shipping price'),
                'orderby' => false,
            ),
            'free_delivery' => array(
                'title'   => $this->l('Free delivery'),
                'icon'    => array(
                    0         => 'disabled.gif',
                    1         => 'enabled.gif',
                ),
                'class'   => 'fixed-width-xs',
                'align'   => 'center',
                'orderby' => false,
                'search'  => false,
            ),
            'free_shipping_threshold' => array(
                'title'   => $this->l('Free delivery threshold'),
                'orderby' => false,
            ),
            'status'                  => array(
                'title'   => $this->l('Status'),
                'icon'    => array(
                    0         => 'disabled.gif',
                    1         => 'enabled.gif',
                    2         => 'date.png',
                    'default' => 'disabled.gif',
                ),
                'class'   => 'fixed-width-xs',
                'align'   => 'center',
                'orderby' => false,
                'search'  => false,
            ),
        );

        if (SamedayTools::isPrestaShop9()) {
            unset($fields['free_delivery']['icon'], $fields['status']['icon']);
            $fields['free_delivery']['callback'] = 'displayHelperListEnabledDisabledIcon';
            $fields['free_delivery']['callback_object'] = $this;
            $fields['status']['callback'] = 'displayHelperListStatusIcon';
            $fields['status']['callback_object'] = $this;
        }

        $helper = new HelperList();
        $helper->toolbar_btn['new'] = array(
            'href' => $this->currentIndex . '&import_services&token=' . Tools::getAdminTokenLite('AdminModules'),
            'desc' => $this->l('Import services from Sameday server'),
        );
        $helper->toolbar_btn['import'] = array(
            'href' => $this->currentIndex . '&update_carriers&token=' . Tools::getAdminTokenLite('AdminModules'),
            'desc' => $this->l('Update carriers using Sameday services'),
        );

        $helper->simple_header = false;
        $helper->listTotal = count($services);
        $helper->identifier = 'id';
        $helper->table = SamedayService::TABLE_NAME;
        $helper->actions = array('edit');
        $helper->show_toolbar = true;
        $helper->module = $this;
        $helper->title = $this->l('Services');
        $helper->shopLinkType = '';
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = $this->currentIndex;

        $this->html .= $helper->generateList($services, $fields);
    }

    /**
     * PS9 HelperList: relative admin gif paths break under deep configure URLs.
     *
     * @param mixed $value
     * @param array $tr
     *
     * @return string
     */
    public function displayHelperListEnabledDisabledIcon($value, $tr)
    {
        $icon = ((int) $value === 1) ? 'enabled.gif' : 'disabled.gif';

        return '<img src="' . htmlspecialchars(SamedayTools::getAdminImgBaseUrl() . $icon, ENT_QUOTES, 'UTF-8') . '" alt="" />';
    }

    /**
     * @param mixed $value
     * @param array $tr
     *
     * @return string
     */
    public function displayHelperListStatusIcon($value, $tr)
    {
        $map = [
            0 => 'disabled.gif',
            1 => 'enabled.gif',
            2 => 'date.png',
        ];
        $icon = isset($map[(int) $value]) ? $map[(int) $value] : 'disabled.gif';

        return '<img src="' . htmlspecialchars(SamedayTools::getAdminImgBaseUrl() . $icon, ENT_QUOTES, 'UTF-8') . '" alt="" />';
    }

    /**
     * @return void
     */
    private function renderPickupPointsList()
    {
        $pickupPoints = SamedayPickupPoint::getPickupPoints();
        $fields = array(
            'id_pickup_point' => array(
                'title'   => $this->l('Sameday Id'),
                'orderby' => true,
                'search'  => false,
            ),
            'sameday_alias'   => array(
                'title'   => $this->l('Sameday Alias'),
                'orderby' => true,
                'search'  => false,
            ),
            'county'          => array(
                'title'   => $this->l('County'),
                'orderby' => true,
                'search'  => false,
            ),
            'city'            => array(
                'title'   => $this->l('City'),
                'orderby' => false,
                'search'  => false,
            ),
            'address'         => array(
                'title'   => $this->l('Address'),
                'orderby' => false,
                'search'  => false,
            ),
        );

        $helper = new HelperList();
        $helper->toolbar_btn = array();
        $helper->toolbar_btn['new'] = array(
            'href' => $this->currentIndex . '&import_pickup_points&token=' . Tools::getAdminTokenLite('AdminModules'),
            'desc' => $this->l('Import pickup-points assigned to your Sameday account'),
        );
        $helper->simple_header = false;
        $helper->listTotal = count($pickupPoints);
        $helper->identifier = 'id_pickup_point';
        $helper->table = SamedayPickupPoint::TABLE_NAME;
        $helper->actions = array('delete');
        $helper->show_toolbar = true;
        $helper->module = $this;
        $helper->title = $this->l('Pickup Points');
        $helper->shopLinkType = '';
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = $this->currentIndex;

        $this->html .= $this->generateAddPickupPointForm();

        $this->html .= $helper->generateList($pickupPoints, $fields);
    }

    /**
     * @return string
     */
    private function generateAddPickupPointForm(): string
    {
        $this->context->smarty->assign([
            'countries' => [SamedayConstants::DEFAULTS_COUNTRIES[$this->generalHelper->getHostCountry()]],
            'counties' => $this->samedayApiHelper->getSamedayCounties(),
            'cities' => $this->samedayApiHelper->getSamedayCities(),
            'token' => SamedayTools::isPrestaShop9()
                ? SamedayTools::getModuleAjaxToken()
                : Tools::getAdminToken('Samedaycourier'),
            'changeCountyAction' => SamedayTools::getEndpointUrl('ajax', 'ajax.php'),
        ]);

        return $this->display(__FILE__, 'views/templates/admin/addNewPickupPoint.tpl');
    }

    /**
     * @throws PrestaShopDatabaseException
     */
    private function renderLockersList()
    {
        $lockers = SamedayLocker::getLockers(false);
        $fields = array(
            'id_locker' => array(
                'title' => $this->l('Sameday Id'),
                'orderby' => true,
                'search' => false,
            ),
            'name' => array(
                'title' => $this->l('Name'),
                'orderby' => true,
                'search' => false,
            ),
            'county' => array(
                'title' => $this->l('County'),
                'orderby' => true,
                'search' => false,
            ),
            'city' => array(
                'title' => $this->l('City'),
                'orderby' => false,
                'search' => false,
            ),
            'address' => array(
                'title' => $this->l('Address'),
                'orderby' => false,
                'search' => false,
            ),
            'postal_code' => array(
                'title' => $this->l('Postal code'),
                'orderby' => false,
                'search' => false,
            ),
            'lat' => array(
                'title' => $this->l('Latitude'),
                'orderby' => false,
                'search' => false,
            ),
            'long' => array(
                'title' => $this->l('Longitude'),
                'orderby' => false,
                'search' => false,
            ),
        );

        $helper = new HelperList();
        $helper->toolbar_btn = array();
        $helper->toolbar_btn['new'] = array(
            'href' => $this->currentIndex . '&import_lockers&token=' . Tools::getAdminTokenLite('AdminModules'),
            'desc' => $this->l('Local import of easybox. !Note: If you choose for easyBox map, you don\'t need anymore a local import.'),
        );
        $helper->simple_header = false;
        $helper->listTotal = count($lockers);
        $helper->identifier = 'id_locker';
        $helper->table = SamedayLocker::TABLE_NAME;
        $helper->actions = array();
        $helper->show_toolbar = true;
        $helper->module = $this;
        $helper->title = $this->l('Lockers');
        $helper->shopLinkType = '';
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = $this->currentIndex;

        $this->html .= $helper->generateList($lockers, $fields);
    }

    /**
     * @return string
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function renderServiceForm()
    {
        $id = (int)Tools::getValue('id');
        $service = new SamedayService($id);
        if ($service->disabled) {
            Tools::redirectAdmin($this->currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'));
        }

        $serviceName = $service->name;
        $greyedOut = false;
        if ($this->generalHelper->isOohDeliveryOption($service->code)) {
            $serviceName = $this->l(SamedayConstants::OOH_SERVICES_LABELS[$this->generalHelper->getHostCountry()]);
            $greyedOut = true;
        }

        $fields = array(
            'form' => array(
                'legend'  => array(
                    'title' => $this->l('Edit Sameday Service'),
                ),
                'input'   => array(
                    array(
                        'type' => 'hidden',
                        'name' => 'id',
                    ),
                    array(
                        'type'     => 'text',
                        'name'     => 'name',
                        'label'    => $this->l('Name'),
                        'disabled' => $greyedOut,
                        'col' => 3,
                        'required' => true,
                    ),
                    array(
                        'type'     => 'text',
                        'name'     => 'price',
                        'label'    => $this->l('Price'),
                        'col' => 3,
                        'required' => true,
                    ),
                    array(
                        'type'    => 'switch',
                        'label'   => $this->l('Free delivery'),
                        'name'    => 'free_delivery',
                        'is_bool' => true,
                        'values'  => array(
                            array(
                                'id'    => 'active_on',
                                'value' => true,
                                'label' => $this->l('Yes'),
                            ),
                            array(
                                'id'    => 'active_off',
                                'value' => false,
                                'label' => $this->l('No'),
                            ),
                        ),
                    ),
                    array(
                        'type'     => 'text',
                        'name'     => 'free_shipping_threshold',
                        'label'    => $this->l('Free shipping amount threshold'),
                        'desc' => $this->l('Minimum order value to receive free delivery'),
                        'col' => 3,
                        'required' => false,
                    ),
                    array(
                        'type'    => 'select',
                        'name'    => 'status',
                        'label'   => $this->l('Status'),
                        'id'      => 'service_status',
                        'options' => array(
                            'query' => array(
                                array(
                                    'id_option' => 0,
                                    'name'      => $this->l('Disabled'),
                                ),
                                array(
                                    'id_option' => 1,
                                    'name'      => $this->l('Always'),
                                )
                            ),
                            'id'    => 'id_option',
                            'name'  => 'name',
                        ),
                    ),
                ),
                'submit'  => array(
                    'title' => $this->l('Save'),
                ),
                'buttons' => array(
                    array(
                        'href'  => $this->currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                        'title' => $this->l('Back to list'),
                        'icon'  => 'process-icon-back',
                    ),
                ),
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'save_sameday_service';
        $helper->currentIndex = $this->currentIndex;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $fieldsValues = array(
            'id' => $service->id,
            'name' => $serviceName,
            'price' => $service->price,
            'free_delivery' => $service->free_delivery,
            'free_shipping_threshold' => $service->free_shipping_threshold,
            'status' => $service->status,
        );

        $helper->tpl_vars = array(
            'fields_value' => $fieldsValues,
            'languages'    => $this->context->controller->getLanguages(),
            'id_language'  => $this->context->language->id,
        );

        $form = $helper->generateForm(array($fields));

        Context::getContext()->smarty->assign('token', Tools::getAdminTokenLite('AdminModules'));

        return $form;
    }

    /**
     * Save form data.
     * @throws Sameday\Exceptions\SamedaySDKException
     *
     * @throws PrestaShopException
     */
    protected function postProcess()
    {
        if ((Tools::isSubmit('submit_sameday')) === true) {
            $form_values = $this->getConfigFormValues();
            $passwordPosted = Tools::getValue('SAMEDAY_ACCOUNT_PASSWORD');
            $shouldUpdatePassword = !($passwordPosted === false || $passwordPosted === null || $passwordPosted === '');

            if ($this->connectionLogin($form_values)) {
                //Reset old token
                $form_values[SamedayPersistenceDataHandler::KEYS[\Sameday\SamedayClient::KEY_TOKEN]] = '';
                $form_values[SamedayPersistenceDataHandler::KEYS[\Sameday\SamedayClient::KEY_TOKEN_EXPIRES]] = '';

                foreach ($form_values as $key => $value) {
                    // Password inputs always POST empty; never wipe the stored password.
                    if ($key === 'SAMEDAY_ACCOUNT_PASSWORD' && !$shouldUpdatePassword) {
                        continue;
                    }

                    // Convert COD references from comma-separated string to JSON array
                    if ($key === 'SAMEDAY_COD_REFERENCES') {
                        if ($value !== null && $value !== '') {
                            $codArray = array_map('trim', explode(',', (string) $value));
                            $codArray = array_filter($codArray); // Remove empty values
                            $value = json_encode(array_values($codArray));
                        } else {
                            $value = null;
                        }
                    }

                    if (in_array($key, $this->getBooleanConfigKeys(), true)) {
                        $value = (int) $value;
                    }

                    Configuration::updateValue($key, $value);
                }

                // Import local data Services and PickupPoints
                $this->importServices();
                $this->importPickupPoints();

                $this->addMessage('success', $this->l('Settings updated.'));
            } else {
                $this->addMessage('danger',
                    $this->l('Connection failed! Verify your credentials and try again later!')
                );
            }
        }

        if (Tools::isSubmit('import_services')) {
            $this->importServices();
        }

        if (Tools::isSubmit('import_cities')) {
            $this->importCities();
        }

        if (Tools::isSubmit('import_pickup_points')) {
            $this->importPickupPoints();
        }

        if (Tools::isSubmit('import_lockers')) {
            $this->importLockers();
        }

        if (Tools::isSubmit('save_sameday_service')) {
            $this->processSaveSamedayService();
        }

        if (Tools::isSubmit('add_new_pickup_point')) {
            $this->addNewPickupPoint();
        }

        if (Tools::isSubmit('update_carriers')) {
            $services = SamedayService::getEnabledServices();

            // Remove unused Sameday Carriers
            SamedayCarrierCore::removeCarriers(
                array_filter(
                    SamedayCarrierCore::getSamedayCarrier(),
                    static function (array $carrier) use ($services) {
                        return !in_array(
                            $carrier['id_carrier'],
                            array_map(static function(array $service) { return $service['id_carrier']; }, $services),
                            true
                        );
                    }
                )
            );

            // Update Carriers
            $this->updateCarriers($services);

            $this->html .= $this->displayConfirmation($this->l('Carriers list successfully updated'));
        }
    }

    /**
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws Sameday\Exceptions\SamedaySDKException
     */
    private function importPickupPoints()
    {
        $client = $this->samedayApiHelper->getSamedayClient();
        $sameday = new \Sameday\Sameday($client);

        $remotePickupPoints = [];
        $page = 1;
        do {
            $request = new \Sameday\Requests\SamedayGetPickupPointsRequest();
            $request->setPage($page++);

            if (Configuration::get('SAMEDAY_DEBUG_MODE', 0)) {
                $this->log('Import pickup points', SamedayConstants::DEBUG);
                $this->log($request, SamedayConstants::DEBUG);
            }

            try {
                $pickupPoints = $sameday->getPickupPoints($request);
            } catch (Exception $e) {
                $this->addMessage('danger', $e->getMessage());
                $this->log($e->getMessage(), SamedayConstants::ERROR);

                return;
            }

            foreach ($pickupPoints->getPickupPoints() as $pickupPointObject) {
                $pickupPoint = SamedayPickupPoint::findBySamedayId($pickupPointObject->getId());
                if (!$pickupPoint) {
                    // Pickup point not found, add it.
                    $pickupPoint = new SamedayPickupPoint();
                } else {
                    $pickupPoint = new SamedayPickupPoint($pickupPoint['id']);
                }

                $pickupPoint->id_pickup_point = $pickupPointObject->getId();
                $pickupPoint->sameday_alias = $this->generalHelper->sanitizeInput($pickupPointObject->getAlias());
                $pickupPoint->county = $pickupPointObject->getCounty()->getName();
                $pickupPoint->city = $pickupPointObject->getCity()->getName();
                $pickupPoint->address = $pickupPointObject->getAddress();
                $pickupPoint->is_default = $pickupPointObject->isDefault();
                $pickupPoint->live_mode = (int) Configuration::get('SAMEDAY_LIVE_MODE', 0);
                $pickupPoint->save();

                // Save as current pickup points.
                $remotePickupPoints[] = $pickupPointObject->getId();
            }
        } while ($page <= $pickupPoints->getPages());

        // Build array of local pickup points.
        $localPickupPoints = array_map(
            static function ($pickupPoint) {
                return array(
                    'id' => (int) $pickupPoint['id'],
                    'sameday_id' => (int) $pickupPoint['id_pickup_point']
                );
            },
            SamedayPickupPoint::getPickupPoints()
        );

        // Delete local pickup points that aren't present in remote pickup points anymore.
        foreach ($localPickupPoints as $localPickupPoint) {
            if (!in_array($localPickupPoint['sameday_id'], $remotePickupPoints, true)) {
                $toDelete = new SamedayPickupPoint($localPickupPoint['id']);
                $toDelete->delete();
            }
        }
    }

    /**
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws Sameday\Exceptions\SamedaySDKException
     */
    public function importLockers()
    {
        $client = $this->samedayApiHelper->getSamedayClient();
        $sameday = new \Sameday\Sameday($client);

        $remoteLockers = [];
        $page = 1;
        do {
            $request = new \Sameday\Requests\SamedayGetLockersRequest();
            $request->setPage($page++);

            if (Configuration::get('SAMEDAY_DEBUG_MODE', 0)) {
                $this->log('Import lockers', SamedayConstants::DEBUG);
                $this->log($request, SamedayConstants::DEBUG);
            }

            try {
                $lockers = $sameday->getLockers($request);
            } catch (Exception $e) {
                $this->addMessage('danger', $e->getMessage());
                $this->log($e->getMessage(), SamedayConstants::ERROR);

                return;
            }

            foreach ($lockers->getLockers() as $lockerObject) {
                $locker = SamedayLocker::findBySamedayId($lockerObject->getId());
                if (!$locker) {
                    // Locker not found, add it.
                    $locker = new SamedayLocker();
                } else {
                    $locker = new SamedayLocker($locker['id']);
                }

                $locker->id_locker = $lockerObject->getId();
                $locker->name = $lockerObject->getName();
                $locker->county = $lockerObject->getCounty();
                $locker->city = $lockerObject->getCity();
                $locker->address = $lockerObject->getAddress();
                $locker->postal_code = $lockerObject->getPostalCode();
                $locker->lat = $lockerObject->getLat();
                $locker->long = $lockerObject->getLong();
                $locker->live_mode = (int) Configuration::get('SAMEDAY_LIVE_MODE', 0);
                if (null !== $locker->name) {
                    $locker->save();
                }

                // Save as current lockers.
                $remoteLockers[] = $lockerObject->getId();
            }
        } while ($page <= $lockers->getPages());

        // Build array of local lockers.
        $localLockers = array_map(
            static function ($locker) {
                return array(
                    'id' => $locker['id'],
                    'sameday_id' => (int) $locker['id_locker']
                );
            },
            SamedayLocker::getLockers(true)
        );

        // Delete local lockers that aren't present in remote lockers anymore.
        foreach ($localLockers as $localLocker) {
            if (!in_array($localLocker['sameday_id'], $remoteLockers, true)) {
                $toDelete = new SamedayLocker($localLocker['id']);
                $toDelete->delete();
            }
        }
    }

    /**
     * @param $params
     * @param $shipping_cost
     *
     * @return false|float|mixed
     *
     * @throws \Sameday\Exceptions\SamedaySDKException
     */
    public function getOrderShippingCost($params, $shipping_cost)
    {
        $service = SamedayService::findByCarrierId($this->id_carrier);

        if (!Configuration::get('SAMEDAY_STATUS_MODE') || !$this->carrierDeliveryAvailable($service)) {
            return false;
        }

        if ($service['code'] === SamedayConstants::SAMEDAY_6H_CODE
            && !in_array('Bucuresti', SamedayConstants::ELIGIBLE_TO_6H_SERVICE, true)
        ) {
            return false;
        }

        if ($this->generalHelper->isNotInUseService($service['code'])) {
            return false;
        }

        if (array_key_exists($service['id'], $this->servicePriceCache)) {
            return $this->servicePriceCache[$service['id']];
        }

        $countryCode = SamedayConstants::DEFAULT_HOST_COUNTRY;
        if (false === $hostCountry = Configuration::get('SAMEDAY_HOST_COUNTRY')) {
            $hostCountry = $countryCode;
        }

        if (
            (null !== $address_delivery_id = $params->id_address_delivery ?? null)
            && (null !== $address = new AddressCore($address_delivery_id))
        ) {
            // Expedition Country
            $countryCode = strtolower(CountryCore::getIsoById($address->id_country));

            if ($service['code'] === SamedayConstants::SAMEDAY_6H_CODE
                && !in_array($address->city, SamedayConstants::ELIGIBLE_TO_6H_SERVICE, true)
            ) {
                return false;
            }
        }

        $eligibleService = SamedayConstants::ELIGIBLE_SERVICES;
        if ($hostCountry !== $countryCode) {
            $eligibleService = SamedayConstants::ELIGIBLE_FOR_CROSSBORDER;
        }

        if (!in_array($service['code'], $eligibleService, true)) {
            return false;
        }

        if ($this->isServiceEligibleToLocker($service['code'])) {
            if (false === $lockerMaxItems = Configuration::get('SAMEDAY_LOCKER_MAX_ITEMS')) {
                $lockerMaxItems = self::DEFAULT_HOST_COUNTRY;
            }

            if ($params->nbProducts() > $lockerMaxItems) {
                // Limit nr. of products to locker delivery
                return false;
            }
        }

        if (!Configuration::get('SAMEDAY_ESTIMATED_COST')) {
            return $shipping_cost;
        }

        $pickupPoint = SamedayPickupPoint::getDefaultPickupPoint();
        $weight = $params->getTotalWeight() < 1 ? 1 : $params->getTotalWeight();

        $sameday = new \Sameday\Sameday($this->samedayApiHelper->getSamedayClient());
        $request = new \Sameday\Requests\SamedayPostAwbEstimationRequest(
            $pickupPoint['id_pickup_point'],
            null,
            new \Sameday\Objects\Types\PackageType(\Sameday\Objects\Types\PackageType::PARCEL),
            array(new \Sameday\Objects\ParcelDimensionsObject($weight)),
            $service['id_service'],
            new \Sameday\Objects\Types\AwbPaymentType(\Sameday\Objects\Types\AwbPaymentType::CLIENT),
            new \Sameday\Objects\PostAwb\Request\AwbRecipientEntityObject(
                ucwords($address->city) !== 'Bucuresti' ? $address->city : 'Sector 1',
                StateCore::getNameById($address->id_state),
                ltrim($address->address1) . $address->address2,
                null,
                null,
                null,
                null,
                (!empty($address->postcode)) ? $address->postcode : null
            ),
            0,
            $params->getOrderTotal(true, 4),
            null,
            array(),
            $this->getDestCurrencyByDestCountryCode(strtolower(CountryCore::getIsoById($address->id_country)))
        );

        try {
            $estimation = $sameday->postAwbEstimation($request);
            $this->servicePriceCache[$service['id']] = $estimation->getCost();
        } catch (Exception $exception) {
            $this->servicePriceCache[$service['id']] = $shipping_cost;
        }

        return $this->servicePriceCache[$service['id']];
    }

    /**
     * @param $service
     *
     * @return bool
     */
    private function carrierDeliveryAvailable($service): bool
    {
        return $service && ((bool) $service['live_mode']) === ((bool) Configuration::get('SAMEDAY_LIVE_MODE', 0));
    }

    /**
     * @param $params
     *
     * @return bool
     */
    public function getOrderShippingCostExternal($params)
    {
        return true;
    }

    /**
     * @param $services
     *
     * @return void
     */
    private function updateCarriers($services)
    {
        foreach ($services as $service) {
            if (false === $carrier = SamedayCarrierCore::findByCarrierId($service['id_carrier'])) {
                $carrier = new CarrierCore();
            }

            $carrier = $this->updateCarrier($service, $carrier);
            if (false !== $carrier) {
                $this->addGroups($carrier);
                $this->addRanges($carrier, $service);

                SamedayService::updateCarrierId($service['id'], $carrier->id);
            }
        }
    }

    /**
     * @param $carrier
     * @param $service
     *
     * @return void
     */
    protected function addRanges($carrier, $service)
    {
        // If already exist Ranges, remove it
        foreach (['carrier_zone', 'delivery', 'range_price'] as $table) {
            try {
                Db::getInstance()->delete(
                    $table,
                    sprintf(
                        "%s = '%s'",
                        'id_carrier',
                        $carrier->id
                    )
                );
            } catch (Exception $exception) { return; }
        }

        // Refresh Ranges
        $ranges = [
            [0, 99999, $service['price']]
        ];
        if (((float) $service['free_shipping_threshold']) > 0) {
            $ranges = array_merge(
                [
                    [
                        .0,
                        $service['free_shipping_threshold'],
                        $service['price']
                    ]
                ],
                [
                    [
                        $service['free_shipping_threshold'],
                        99999,
                        $service['free_delivery'] ? 0 : $service['price']
                    ]
                ]
            );
        }

        foreach ($ranges as $range) {
            list($from, $to, $price) = $range;
            $rangePrice = new RangePriceCore();
            $rangePrice->id_carrier = $carrier->id;
            $rangePrice->delimiter1 = $from;
            $rangePrice->delimiter2 = $to;
            try {
                $rangePrice->save();
                $rangePrice->clearCache(true);
            } catch (Exception $exception) { return; }


            // Associate carrier to all zones
            $zones = Zone::getZones(true);
            foreach ($zones as $zone) {
                try {
                    Db::getInstance()->insert(
                        'carrier_zone',
                        [
                            'id_carrier' => (int) $carrier->id,
                            'id_zone' => (int) $zone['id_zone']
                        ]
                    );
                    Db::getInstance()->insert(
                        'delivery',
                        [
                            'id_carrier' => (int) $carrier->id,
                            'id_range_price' => (int) $rangePrice->id,
                            'id_range_weight' => null,
                            'id_zone' => (int) $zone['id_zone'],
                            'price' => $price
                        ]
                    );
                } catch (Exception $e) { return; }
            }
        }
    }

    /**
     * @param $service
     * @param CarrierCore $carrier
     *
     * @return CarrierCore|false
     */
    protected function updateCarrier($service, CarrierCore $carrier)
    {
        if (Configuration::get('SAMEDAY_DEBUG_MODE', 0)) {
            $this->log('Create/Update Carrier', SamedayConstants::DEBUG);
        }

        $name = $this->l('Sameday Courier');

        $carrier->name = $name;
        $carrier->is_module = true;
        $carrier->active = (bool) $service['status'];
        $carrier->deleted = 0;
        $carrier->need_range = true;
        $carrier->shipping_external = true;
        $carrier->shipping_handling = false;
        $carrier->shipping_method = 2;
        $carrier->range_behavior = 0;
        $carrier->external_module_name = $this->name;

        if ($service['free_delivery'] && ((int) $service['free_shipping_threshold']) === 0) {
            $carrier->is_free = true;
        }

        foreach (Language::getLanguages() as $lang) {
            $carrier->delay[$lang['id_lang']] = $this->l($service['name']);
        }

        try {
            if (true === (bool) $carrier->save()) {
                @copy(__DIR__ . '/views/img/carrier_image.jpg', _PS_SHIP_IMG_DIR_
                    . '/' . (int)$carrier->id . '.jpg');

                Configuration::updateValue($this->getCarrierKey($service['code']), (int) $carrier->id);

                return $carrier;
            }
        } catch (Exception $e) {
            $this->log($e->getMessage(), SamedayConstants::ERROR);
        }

        return false;
    }

    /**
     * @param CarrierCore $carrier
     *
     * @return void
     */
    protected function addGroups(CarrierCore $carrier)
    {
        $carrier->setGroups([]);
        $groups_ids = [];

        $groups = Group::getGroups(Context::getContext()->language->id);
        if (!empty($groups)) {
            foreach ($groups as $group) {
                $groups_ids[] = $group['id_group'];
            }
        }

        $carrier->setGroups($groups_ids);
    }

    /**
     * @return false|string
     */
    public function hookDisplayAdminAfterHeader()
    {
        $this->smarty->assign('messages', $this->messages);

        $output = $this->display(__FILE__, 'displayAdminAfterHeader.tpl');

        if ($this->shouldRenderBulkAwbOnAdminAfterHeader()) {
            $output .= $this->renderBulkAwbOrdersListMarkup();
        }

        return $output;
    }

    /**
     * @return void
     */
    public function hookHeader()
    {
        $this->insertNewHeader();
    }

    /**
     * @return void
     */
    public function hookDisplayHeader()
    {
        $this->insertNewHeader();
    }

    /**
     * @return void
     */
    private function insertNewHeader()
    {
        if (!in_array($this->context->controller->php_self, ['address', 'checkout', 'order'], true)) {
            return;
        }

        Media::addJsDef([
            'SamedayCities' => SamedayCity::getCitiesCachedResult(),
        ]);

        $this->context->controller->addJS($this->_path .  'views/js/citiesHandler.js');

        if ($this->context->controller->php_self === 'order') {
            $totalWeight = 0;
            $cartProducts = $this->context->cart->getProducts();
            foreach($cartProducts as $product) {
                $totalWeight += $product['weight'];
            }

            $samedayCarriers = SamedayService::getEnabledServices();
            $samedayCarrierIds = [];
            foreach ($samedayCarriers as $carrier) {
                $samedayCarrierIds[] = $carrier['id_carrier'];
            }

            if (($this->getMajorVersion() === 1) && ($this->getMinorVersion() === 7)) {
                $errorFile = 'views/js/weightError-17.js';
            } else {
                $errorFile = 'views/js/weightError.js';
            }

            Media::addJsDef([
                'cartTotalWeight' => $totalWeight,
                'samedayCarrierIds' => $samedayCarrierIds,
                'weightErrorJsPath' => $this->_path . $errorFile
            ]);

            // Add the carrier change handler
            $this->context->controller->addJS($this->_path . 'views/js/carrierWeightHandler.js');

        }
    }

    /**
     * @param $params
     * @return mixed
     * @throws \Sameday\Exceptions\SamedayAuthenticationException
     * @throws \Sameday\Exceptions\SamedayAuthorizationException
     * @throws \Sameday\Exceptions\SamedayBadRequestException
     * @throws \Sameday\Exceptions\SamedayNotFoundException
     * @throws \Sameday\Exceptions\SamedaySDKException
     * @throws \Sameday\Exceptions\SamedayServerException
     */
    private function displayAdminOrderContent($params)
    {
        $order = $params['order'];

        if (Tools::isSubmit('addAwb')) {
            $this->addAwb($order);
        }

        if (Tools::isSubmit('addParcel')) {
            $this->addParcel($order);
        }

        if (Tools::isSubmit('cancelAwb')) {
            $this->cancelAwb($order->id);
        }

        if (Tools::isSubmit('downloadAwb')) {
            $this->downloadAwb($order->id);
        }

        $pickupPoints = SamedayPickupPoint::getPickupPoints();

        $services = [];
        $activeServices = SamedayService::getServices(true);
        foreach ($activeServices as $service) {
            $service['isPDOtoShow'] = $this->toggleHtmlElement(
                $this->isServiceEligibleToPdo($service['service_optional_taxes'])
            );

            $service['isLastMileToShow'] = $this->toggleHtmlElement(
                $this->isServiceEligibleToLocker((string) $service['code'])
            );

            $services[] = $service;
        }

        $packageTypes = array(
            0 => $this->l('Package'),
            1 => $this->l('Envelope'),
            2 => $this->l('Large package'),
        );

        $awb = SamedayAwb::getOrderAwb($params['order']->id);
        $allowParcel = false;
        if ($awb) {
            $now = new DateTime();
            $allowParcel =
                DateTime::createFromFormat('Y-m-d H:i:s', $awb['created'])->format('Ymd') === $now->format('Ymd');
        }

        $serviceId = null;
        $isLastMileToShow = $this->toggleHtmlElement(false);
        $lockerId = null;
        $lockerName = null;
        $lockerAddress = null;
        $samedayOrderLockerId = null;
        if (false !== $service = SamedayService::findByCarrierId($order->id_carrier)) {
            $serviceId = $service['id_service'];
            if ($this->isServiceEligibleToLocker((string) $service['code'])) {
                $isLastMileToShow = $this->toggleHtmlElement(true);

                if (null !== $locker = SamedayOrderLocker::getLockerForOrder($order->id)) {
                    $samedayOrderLockerId = $locker['id'] ?? null;
                    $lockerId = $locker['id_locker'] ?? null;
                    $lockerName = $locker['name_locker'] ?? null;
                    $lockerAddress = $locker['address_locker'] ?? null;
                    $lockerService = $locker['service_code'] ?? null;

                    if (null !== $lockerService) {
                        $serviceId = SamedayService::findByCode($lockerService)['id_service'];
                    }
                }
            }
        }

        $repayment = 0.0;
        if ($this->checkForCashPayment($order->payment)) {
            $repayment = number_format($order->total_paid, 2);
        }

        $destCountryCode = strtolower(CountryCore::getIsoById((new AddressCore($order->id_address_delivery))->id_country))
            ?? SamedayConstants::DEFAULT_HOST_COUNTRY
        ;

        $orderCurrency = CurrencyCore::getCurrency($order->id_currency)['iso_code'];
        $destCurrency = $this->getDestCurrencyByDestCountryCode($destCountryCode);

        $xBorderWarning = '';
        if (self::CURRENCIES[$orderCurrency] !== $destCountryCode
            && $repayment > 0
        ) {
            $xBorderWarning = sprintf(
                'Be aware that the intended currency is %s but the Repayment value is expressed in %s and please consider a conversion !!',
                $destCurrency,
                $orderCurrency
            );
        }

        $this->smarty->assign(
            array(
                'orderId'       => $order->id,
                'pickup_points' => $pickupPoints,
                'services'      => $services,
                'current_service' => $serviceId,
                'package_types' => $packageTypes,
                'repayment'     => $repayment,
                'xBorderWarning' => $xBorderWarning,
                'orderCurrency' => $orderCurrency,
                'awb'           => $awb,
                'allowParcel'   => $allowParcel,
                'lockerId'      => ((int) $lockerId) > 0,
                'samedayUser'   => Configuration::get('SAMEDAY_ACCOUNT_USER'),
                'countryCode'   => $destCountryCode,
                'lockerDetails' => sprintf('%s  %s', $lockerName, $lockerAddress),
                'idLocker'      => $lockerId,
                'lockerName'    => $lockerName,
                'lockerAddress' => $lockerAddress,
                'samedayOrderLockerId' => $samedayOrderLockerId,
                'packageWeight' => $order->getTotalWeight() ?? 1,
                'isPDOtoShow'   => $this->toggleHtmlElement(
                    $this->isServiceEligibleToPdo($service['service_optional_taxes'])
                ),
                'isLastMileToShow' => $isLastMileToShow,
                'isOpenPackage' => ((int) SamedayOpenPackage::checkOrderIfIsOpenPackage($order->id)) > 0,
                'ajaxRoute'     => $this->ajaxRoute,
                'messages' => $this->messages
            )
        );

        return $this->display(__FILE__, 'displayAdminOrder.tpl');
    }

    /**
     * @param $paymentType
     *
     * @return bool
     */
    private function checkForCashPayment($paymentType): bool
    {
        $codReferences = self::getCOD();

        if (empty($codReferences) || !is_array($codReferences)) {
            return false;
        }

        foreach ($codReferences as $value) {
            if (stripos($paymentType, $value) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $destCountryCode
     * @return string
     */
    private function getDestCurrencyByDestCountryCode($destCountryCode)
    {
        return array_keys(self::CURRENCIES, $destCountryCode, true)[0] ?? null;
    }

    /**
     * @param $params
     * @return false|string
     */
    public function hookDisplayAdminOrderContentShip($params)
    {
        try {
            return $this->displayAdminOrderContent($params);
        } catch (Exception $exception) {
            return $exception;
        }
    }

    /**
     * @param $params
     * @return Exception|false|string
     */
    public function hookDisplayAdminOrderSide($params)
    {
        $params['order'] = new Order((int) $params['id_order']);

        try {
            return $this->displayAdminOrderContent($params);
        } catch (Exception $exception) {
            return $exception;
        }
    }

    /**
     * @param $params
     */
    public function hookActionCarrierUpdate($params)
    {
        $oldCarrier = (int)$params['id_carrier'];
        $newCarrier = (int)$params['carrier']->id;

        if ($oldCarrier != $newCarrier) {
            $service = SamedayService::findByCarrierId($oldCarrier);
            if ($service) {
                SamedayService::updateCarrierId($service['id'], $newCarrier);
                $carrier_key = $this->getCarrierKey($service['code']);
                Configuration::updateValue($carrier_key, $newCarrier);
            }
        }
    }

    /**
     * @param $params
     * @return false|string
     *
     * @throws PrestaShopDatabaseException
     */
    public function hookExtraCarrier($params)
    {
        $service = SamedayService::findByCarrierId($params['cart']->id_carrier);
        if (!$service) {
            return '';
        }

        return $this->displayCarrierExtraContent(
            $params,
            $service,
            '1.6'
        );
    }

    /**
     * @param $params
     * @return false|string
     *
     * @throws PrestaShopDatabaseException
     */
    public function hookDisplayCarrierExtraContent($params)
    {
        $carrierId = 0;
        if (isset($params['carrier'])) {
            if (is_array($params['carrier']) && isset($params['carrier']['id'])) {
                $carrierId = (int) $params['carrier']['id'];
            } elseif (is_object($params['carrier'])) {
                if (isset($params['carrier']->id_carrier)) {
                    $carrierId = (int) $params['carrier']->id_carrier;
                } elseif (isset($params['carrier']->id)) {
                    $carrierId = (int) $params['carrier']->id;
                }
            }
        }

        if ($carrierId <= 0) {
            return '';
        }

        $service = SamedayService::findByCarrierId($carrierId);
        if (!$service) {
            return '';
        }

        return $this->displayCarrierExtraContent(
            $params,
            $service,
            '1.7'
        );
    }

    /**
     * @param $params
     * @param $service
     * @param $fileVersion
     *
     * @return false|string
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function displayCarrierExtraContent(
        $params,
        $service,
        $fileVersion
    )
    {
        $html = "";
        $cart = new CartCore($params['cart']->id);
        if ($this->isServiceEligibleToLocker((string) $service['code'])) {
            $address = new AddressCore($cart->id_address_delivery);
            $stateName = StateCore::getNameById($address->id_state);

            $sameday_user = Configuration::get('SAMEDAY_ACCOUNT_USER');
            $countryCode = strtolower(CountryCore::getIsoById($address->id_country));
            $useLockerMap = (bool) Configuration::get('SAMEDAY_LOCKERS_MAP');

            $lockers = null;
            if (!$useLockerMap) {
                $lockersList = SamedayLocker::getLockers();
                if (empty($lockersList)) {
                    try {
                        $this->importLockers();
                        $lockersList = SamedayLocker::getLockers();
                    } catch (Exception $e) {
                        $this->log($e->getMessage(), SamedayConstants::ERROR);
                        $lockersList = [];
                    }
                }

                if (!empty($lockersList)) {
                    foreach ($lockersList as $locker) {
                        $lockers[$locker['city']][] = [
                            'id' => $locker['id_locker'],
                            'name' => $locker['name'],
                            'address' => $locker['address'],
                            'label' => sprintf('%s - %s', $locker['name'], $locker['address']),
                        ];
                    }

                    ksort($lockers);
                }
            }

            if (null !== $lockers) {
                $this->smarty->assign('lockers', $lockers);
            }

            $this->smarty->assign('lockerId', $params['cookie']->samedaycourier_locker_id);
            $this->smarty->assign('lockerName', $params['cookie']->samedaycourier_locker_name);
            $this->smarty->assign('lockerAddress', $params['cookie']->samedaycourier_locker_address);
            $this->smarty->assign('lockerOohType', $params['cookie']->samedaycourier_locker_ooh_type);
            $this->smarty->assign('idCart', $params['cart']->id);
            $this->smarty->assign('city', $address->city);
            $this->smarty->assign('county', $stateName);
            $this->smarty->assign('countryCode', $countryCode);
            $this->smarty->assign('samedayUser', $sameday_user);
            $storeLockerToken = SamedayTools::isPrestaShop9()
                ? SamedayTools::getModuleAjaxToken()
                : Tools::getAdminToken('Samedaycourier');
            $storeLockerRoute = SamedayTools::getEndpointUrl('ajax', 'ajax.php', [
                'token' => $storeLockerToken,
            ]);
            $this->smarty->assign('storeLockerRoute', $storeLockerRoute);

            if ($useLockerMap) {
                $html = $this->display(__FILE__, self::TEMPLATE_VERSION[$fileVersion]['locker_options_map']);
            } else {
                $html = $this->display(__FILE__, self::TEMPLATE_VERSION[$fileVersion]['locker_options_selector']);
            }
        }

        if (
            (int) Configuration::get('SAMEDAY_OPEN_PACKAGE')
            && $this->checkForOpenPackageTax($service['service_optional_taxes'])
        ) {
            $this->smarty->assign('carrier_id', $params['cart']->id_carrier);
            $this->smarty->assign('label', Configuration::get('SAMEDAY_OPEN_PACKAGE_LABEL'));

            $html = $this->display(__FILE__, self::TEMPLATE_VERSION[$fileVersion]['open_package_option']);
        }

        return $html;
    }

    /**
     * @param $serviceOptionalTaxes
     *
     * @return bool
     */
    private function checkForOpenPackageTax($serviceOptionalTaxes): bool
    {
        $taxOpenPackage = 0;
        $optionalServices = unserialize($serviceOptionalTaxes, ['']);

        if (!empty($optionalServices)) {
            foreach ($optionalServices as $optionalService) {
                if ($optionalService['code'] === SamedayConstants::OPENPACKAGECODE
                    && $optionalService['type'] === \Sameday\Objects\Types\PackageType::PARCEL
                ) {
                    $taxOpenPackage = $optionalService['id'];

                    break;
                }
            }
        }

        return $taxOpenPackage > 0;
    }

    public function hookActionCarrierProcess()
    {
        //
    }

    /**
     * @param $params
     * @throws PrestaShopException
     */
    public function hookActionValidateOrder($params)
    {
        if (false === $service = SamedayService::findByCarrierId($params['cart']->id_carrier)) {
            return;
        }

        if (false === $serviceCode = $service['code'] ?? false) {
            return;
        }

        if ($this->isServiceEligibleToLocker((string) $serviceCode)) {
            $samedayCart = new SamedayCart($params['cart']->id);
            if (null !== $locker = $samedayCart->sameday_locker) {
                $locker = json_decode($locker, false);
                $orderLocker = new SamedayOrderLocker();

                $orderLocker->id_order = $params['order']->id;
                $orderLocker->id_locker = $locker->locker_id;
                $orderLocker->address_locker = $locker->locker_address;
                $orderLocker->name_locker = $locker->locker_name;
                if ('' === $locker->ooh_type || null === $locker->ooh_type) {
                    $locker->ooh_type = 0; // Default as LockerNextDay
                }
                $orderLocker->service_code = SamedayConstants::OOH_SERVICES[$locker->ooh_type];

                $orderLocker->save();
            }
        }

        $openPackage = (int) isset($_COOKIE['samedaycourier_open_package']) ? $_COOKIE['samedaycourier_open_package'] : 0;
        if ($openPackage > 0  && $this->checkForOpenPackageTax($service['service_optional_taxes'])) {
            $SamedayOpenPackage = new SamedayOpenPackage();

            $SamedayOpenPackage->id_order = $params['order']->id;
            $SamedayOpenPackage->is_open_package = 1;
            $SamedayOpenPackage->save();
        }
    }

    /**
     * @param $params
     */
    public function hookActionValidateStepComplete($params)
    {
        $service = SamedayService::findByCarrierId($params['cart']->id_carrier);
        $lockerId = $_COOKIE['samedaycourier_locker_id'] ?? null;

        if (
            null === $lockerId
            && $this->isServiceEligibleToLocker((string) $service['code'])
        ) {
            $this->context->controller->errors[] = $this->l('Please select your easyBox from lockers map !');
            $params['completed']  = false;
        }
    }

    /**
     * @return void
     *
     * @throws Exception
     */
    public function addNewPickupPoint()
    {
        $form = Tools::getAllValues();

        try {
            $samedayClient = new \Sameday\Sameday($this->samedayApiHelper->getSamedayClient());
        } catch (Exception $exception) {
            $this->addMessage('danger', $this->l($exception->getMessage()));
            return;
        }

        $pickupPointRequest = new \Sameday\Requests\SamedayPostPickupPointRequest(
            $form['country'],
            $form['county'],
            $form['city'],
            $form['address'],
            $form['postalCode'],
            $form['alias'],
            [
                new \Sameday\Objects\PickupPoint\PickupPointContactPersonObject(
                    $form['contactPerson'],
                    $form['contactPersonPhone'],
                    true
                )
            ],
            filter_var($form['isDefault'], FILTER_VALIDATE_BOOLEAN)
        );

        try {
            $newPickupPoint = $samedayClient->postPickupPoint($pickupPointRequest);
        } catch (\Sameday\Exceptions\SamedayBadRequestException $exception) {
            $this->addMessage('danger', implode(',', $exception->getErrors()));
            return;
        } catch (Exception $exception) {
            $this->addMessage('danger', $this->l($exception->getMessage()));
            return;
        }

        $this->addMessage(
            'success',
            $this->l("Pickup point {$newPickupPoint->getPickupPointId()} created successfully!")
        );
    }

    public function deletePickupPoint()
    {
        if (0 === $pickupPointId = (int) Tools::getValue('id_pickup_point')) {
            $this->addMessage('danger', $this->l('Pickup point not found!'));
            return;
        }

        try {
            $samedayClient = new Sameday\Sameday($this->samedayApiHelper->getSamedayClient());
        } catch (Exception $exception) {
            $this->addMessage('danger', $this->l($exception->getMessage()));
            return;
        }

        try {
            $samedayClient->deletePickupPoint(new \Sameday\Requests\SamedayDeletePickupPointRequest($pickupPointId));
        } catch (\Sameday\Exceptions\SamedayBadRequestException $exception) {
            $this->addMessage('danger', implode(',', $exception->getErrors()));
            return;
        } catch (Exception $exception) {
            $this->addMessage('danger', $this->l($exception->getMessage()));
            return;
        }

        try {
            $samedayPickupPoint = new SamedayPickupPoint($pickupPointId);
            $samedayPickupPoint->delete();
        } catch (Exception $exception) {
            $this->addMessage('danger', $this->l($exception->getMessage()));
            return;
        }

        $this->addMessage('success', $this->l('Pickup point deleted!'));
    }

    /**
     * @param $order
     *
     * @return SamedayAwb|null
     */
    private function addAwb($order)
    {
        $insuredValue = Tools::getValue('sameday_insured_value');
        $packagesWeight = Tools::getValue('sameday_package_weight');
        $packagesHeight = Tools::getValue('sameday_package_height');
        $packagesLength = Tools::getValue('sameday_package_length');
        $packagesWidth = Tools::getValue('sameday_package_width');
        $parcelDimensions = array();
        foreach ($packagesWeight as $key => $weight) {
            $height = !empty($packagesHeight[$key]) ? $packagesHeight[$key] : 0;
            $width = !empty($packagesWidth[$key]) ? $packagesWidth[$key] : 0;
            $length = !empty($packagesLength[$key]) ? $packagesLength[$key] : 0;
            $parcelDimensions[] = new \Sameday\Objects\ParcelDimensionsObject($weight, $width, $length, $height);
        }

        $service = SamedayService::findByIdService(Tools::getValue('sameday_service'));

        $customer = new CustomerCore($order->id_customer);
        $address = new AddressCore($order->id_address_delivery);
        $stateName = StateCore::getNameById($address->id_state);

        $company = null;
        if (!empty($address->company)) {
            $company = new \Sameday\Objects\PostAwb\Request\CompanyEntityObject(
                $address->company,
                $address->vat_number,
                $address->dni,
                '',
                ''
            );
        }

        if ('' === $phone = !empty($address->phone_mobile) ? $address->phone_mobile : $address->phone) {
            $this->addMessage('danger', [$this->l('Must complete phone number!')]);
        }

        if ('' === $email = $customer->email ?? '') {
            $this->addMessage('danger', [$this->l('Must complete email!')]);
        }

        if (!empty($this->messages)) {
            return null;
        }

        $recipient = new \Sameday\Objects\PostAwb\Request\AwbRecipientEntityObject(
            $address->city,
            $stateName,
            trim($address->address1 . ' ' . $address->address2),
            $address->firstname . ' ' . $address->lastname,
            $phone,
            $email,
            $company,
            (!empty($address->postcode)) ? $address->postcode : null
        ); 

        $lockerLastMileId = null;
        $oohLastMileId = null;
        $lockerName = null;
        $lockerAddress = null;
        if (
            $this->isServiceEligibleToLocker($service['code'])
            && ('' !== Tools::getValue('locker_id'))
            && ('' !== Tools::getValue('locker_name'))
            && ('' !== Tools::getValue('locker_address'))
        ) {
            $lockerLastMileId = (int) Tools::getValue('locker_id');
            if ($service['code'] === SamedayConstants::PUDO_CODE) {
                $oohLastMileId = (int) Tools::getValue('locker_id');
            }

            $lockerName = Tools::getValue('locker_name');
            $lockerAddress = Tools::getValue('locker_address');
        }

        $serviceTaxIds = array();
        if (!empty(Tools::getValue('sameday_open_package'))) {
            $optionalTaxIds = unserialize($service['service_optional_taxes'], ['']);
            if (false !== $optionalTaxIds) {
                foreach ($optionalTaxIds as $optionalService) {
                    if (
                        $optionalService['code'] === SamedayConstants::OPENPACKAGECODE
                        && $optionalService['type'] === (int) Tools::getValue('sameday_package_type')
                    ) {
                        $serviceTaxIds[] = $optionalService['id'];

                        break;
                    }
                }
            }
        }

        if (!empty(Tools::getValue('sameday_locker_first_mile'))) {
            $optionalTaxIds = unserialize($service['service_optional_taxes'], ['']);
            if (false !== $optionalTaxIds) {
                foreach ($optionalTaxIds as $optionalService) {
                    if ($optionalService['code'] === SamedayConstants::PERSONAL_DELIVERY_OPTION_CODE) {
                        $serviceTaxIds[] = SamedayConstants::PERSONAL_DELIVERY_OPTION_CODE;

                        break;
                    }
                }
            }
        }

        $request = new \Sameday\Requests\SamedayPostAwbRequest(
            Tools::getValue('sameday_pickup_point'),
            null,
            new \Sameday\Objects\Types\PackageType(Tools::getValue('sameday_package_type')),
            $parcelDimensions,
            $service['id_service'],
            new \Sameday\Objects\Types\AwbPaymentType(Tools::getValue('sameday_awb_payment')),
            $recipient,
            $insuredValue,
            Tools::getValue('sameday_repayment'),
            new \Sameday\Objects\Types\CodCollectorType(\Sameday\Objects\Types\CodCollectorType::CLIENT),
            null,
            $serviceTaxIds,
            null,
            $this->buildAwbClientReference((int) $order->id),
            Tools::getValue('sameday_observation'),
            '',
            '',
            null,
            $lockerLastMileId,
            null,
            $oohLastMileId,
            $this->getDestCurrencyByDestCountryCode(strtolower(CountryCore::getIsoById($address->id_country)))
        );

        if (Configuration::get('SAMEDAY_DEBUG_MODE', 0)) {
            $this->log('Generate awb', SamedayConstants::DEBUG);
            $this->log($request, SamedayConstants::DEBUG);
        }

        try {
            $sameday = new \Sameday\Sameday($this->samedayApiHelper->getSamedayClient());
            $response = $sameday->postAwb($request);
            $samedayAwb = new SamedayAwb();
            $samedayAwb->id_order = $order->id;
            $samedayAwb->awb_cost = $response->getCost();
            $samedayAwb->awb_number = $response->getAwbNumber();
            $samedayAwb->created = date('Y-m-d H:i:s');
            if ($samedayAwb->save()) {
                foreach ($response->getParcels() as $parcel) {
                    $samedayAwbParcel = new SamedayAwbParcel();
                    $samedayAwbParcel->id_awb = $samedayAwb->id;
                    $samedayAwbParcel->awb_number = $parcel->getAwbNumber();
                    $samedayAwbParcel->position = $parcel->getPosition();
                    $samedayAwbParcel->save();
                }
            }

            $orderCarrier = new OrderCarrier((int)$order->getIdOrderCarrier());
            $orderCarrier->tracking_number = $response->getAwbNumber();
            $orderCarrier->update();

            $order->id_carrier = $service['id_carrier'];
            $order->shipping_number = $samedayAwb->awb_number;
            $order->update();

            if (
                null !== $lockerLastMileId
                && $service['code'] === SamedayConstants::LOCKER_NEXT_DAY_CODE
            ) {
                $samedayOrderLockerId = Tools::getValue('samedayOrderLockerId');
                if ('' === $samedayOrderLockerId) {
                    $orderLocker = new SamedayOrderLocker();
                    $orderLocker->id_order = $order->id;
                } else {
                    $orderLocker = new SamedayOrderLocker($samedayOrderLockerId);
                }

                $orderLocker->id_locker = $lockerLastMileId;
                $orderLocker->name_locker = $lockerName;
                $orderLocker->address_locker = $lockerAddress;
                $orderLocker->service_code = $service['code'];

                $orderLocker->save();
            }

            $this->addMessage('success', $this->l('AWB was generated.'));

            return $samedayAwb;
        } catch (\Sameday\Exceptions\SamedayBadRequestException $e) {
            $this->addMessage('danger', [$this->formatSamedayBadRequestMessage($e)]);
        } catch (Exception $e) {
            $this->log($e->getMessage() . $e->getTraceAsString(), SamedayConstants::ERROR);
            $this->addMessage('danger', [sprintf('Error Nr. %s: %s', $e->getCode(), $this->l($e->getMessage()))]);
        }

        return null;
    }

    /**
     * @param $order
     * @throws PrestaShopException
     * @throws Sameday\Exceptions\SamedaySDKException
     * @throws \Sameday\Exceptions\SamedayAuthenticationException
     * @throws \Sameday\Exceptions\SamedayAuthorizationException
     * @throws \Sameday\Exceptions\SamedayBadRequestException
     * @throws \Sameday\Exceptions\SamedayNotFoundException
     * @throws \Sameday\Exceptions\SamedayServerException
     */
    private function addParcel($order)
    {
        $awb = SamedayAwb::getOrderAwb($order->id);
        $position = SamedayAwbParcel::getLastPosition($awb['id']) + 1;
        $weight = Tools::getValue('sameday_package_weight');
        $height = Tools::getValue('sameday_package_height');
        $length = Tools::getValue('sameday_package_length');
        $width = Tools::getValue('sameday_package_width');
        $observation = Tools::getValue('sameday_observation');

        $sameday = new \Sameday\Sameday($this->samedayApiHelper->getSamedayClient());

        $request = new \Sameday\Requests\SamedayPostParcelRequest(
            $awb['awb_number'],
            new \Sameday\Objects\ParcelDimensionsObject($weight, $width, $length, $height),
            $position,
            $observation
        );

        if (Configuration::get('SAMEDAY_DEBUG_MODE', 0)) {
            $this->log('Add parcel to awb', SamedayConstants::DEBUG);
            $this->log($request, SamedayConstants::DEBUG);
        }

        try {
            $response = $sameday->postParcel($request);

            $parcel = new SamedayAwbParcel();
            $parcel->id_awb = $awb['id'];
            $parcel->awb_number = $response->getParcelAwbNumber();
            $parcel->position = $position;
            $parcel->save();
            $this->addMessage('success', $this->l('Parcel added to AWB'));
        } catch (\Sameday\Exceptions\SamedayOtherException $e) {
            $response = json_decode($e->getRawResponse()->getBody());
            $this->addMessage('danger', $response->error->message);
            $this->log($e->getRawResponse()->getBody(), SamedayConstants::ERROR);
        }
    }

    /**
     * @param $order
     */
    private function cancelAwb($order)
    {
        try {
            $awb = SamedayAwb::getOrderAwb($order);
            $sameday = new Sameday\Sameday($this->samedayApiHelper->getSamedayClient());

            if (SamedayAwb::cancelAwbByOrderId($order)) {
                SamedayAwbParcel::deleteAwbParcels($awb['id']);
                $request = new Sameday\Requests\SamedayDeleteAwbRequest($awb['awb_number']);
                if (Configuration::get('SAMEDAY_DEBUG_MODE', 0)) {
                    $this->log('Cancel awb', SamedayConstants::DEBUG);
                    $this->log($request, SamedayConstants::DEBUG);
                }
                $sameday->deleteAwb($request);
                $orderEntity = new Order((int) $order);
                $orderCarrier = new OrderCarrier((int)$orderEntity->getIdOrderCarrier());
                $orderCarrier->tracking_number = null;
                $orderCarrier->update();

                $this->addMessage('success', $this->l('AWB was canceled'));
            }
        } catch (Sameday\Exceptions\SamedayOtherException $e) {
            $response = json_decode($e->getRawResponse()->getBody(), true);
            $this->addMessage('danger', $response->error->message);
            $this->log($e->getRawResponse()->getBody(), SamedayConstants::ERROR);
        } catch (Exception $e) {
            $this->log($e->getMessage(), SamedayConstants::ERROR);
            $this->addMessage('danger', $this->l('An error occurred while trying to cancel AWB'));
        }
    }

    /**
     * @param $order
     * @throws Sameday\Exceptions\SamedaySDKException
     * @throws Sameday\Exceptions\SamedayAuthenticationException
     * @throws Sameday\Exceptions\SamedayAuthorizationException
     * @throws Sameday\Exceptions\SamedayBadRequestException
     * @throws Sameday\Exceptions\SamedayNotFoundException
     * @throws Sameday\Exceptions\SamedayServerException
     */
    private function downloadAwb($order)
    {
        $awb = SamedayAwb::getOrderAwb($order);
        $sameday = new Sameday\Sameday($this->samedayApiHelper->getSamedayClient());
        $request = new Sameday\Requests\SamedayGetAwbPdfRequest(
            $awb['awb_number'],
            new Sameday\Objects\Types\AwbPdfType(Configuration::get('SAMEDAY_AWB_PDF_FORMAT'))
        );
        if (Configuration::get('SAMEDAY_DEBUG_MODE', 0)) {
            $this->log('Download awb', SamedayConstants::DEBUG);
            $this->log($request, SamedayConstants::DEBUG);
        }
        $pdf = $sameday->getAwbPdf($request);
        while(ob_get_level()>1) {
            ob_end_clean();
        }
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . $awb['awb_number'] . '.pdf');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        ob_end_clean();
        flush();

        echo $pdf->getPdf();
        die;
    }

    /**
     * @throws Sameday\Exceptions\SamedayNotFoundException
     * @throws Sameday\Exceptions\SamedayServerException
     */
    public function downloadAwbPdfForOrder(int $orderId)
    {
        $awb = SamedayAwb::getOrderAwb($orderId);
        if (empty($awb['awb_number'])) {
            header('Content-Type: application/json');
            http_response_code(404);
            die(json_encode([
                'success' => false,
                'error' => $this->l('AWB not found for this order.'),
            ]));
        }

        $this->downloadAwb($orderId);
    }

    public function getBulkAwbAjaxUrl(): string
    {
        return SamedayTools::getEndpointUrl('ajax', 'ajax.php');
    }

    /**
     * Token embedded in BO orders list for bulk AJAX.
     * PS9: module AJAX token (FO controllers cannot rely on employee isLoggedBack / AdminOrders CSRF).
     * Older PS: AdminOrders token (unchanged).
     */
    public function getBulkAwbAdminToken(): string
    {
        if (SamedayTools::isPrestaShop9()) {
            return SamedayTools::getModuleAjaxToken();
        }

        return Tools::getAdminTokenLite('AdminOrders');
    }

    /**
     * Cron status-sync URL shown / used for scheduled jobs.
     */
    public function getStatusSyncCronUrl(): string
    {
        return SamedayTools::getEndpointUrl('sync', 'sync.php', [
            'token' => SamedayTools::getCronUrlToken(),
        ]);
    }

    public function getBulkAwbGridFeedback(int $orderId): string
    {
        $bulkRows = SamedayOrderBulkAwb::getByOrderIds([$orderId]);
        $awbRows = SamedayAwb::getByOrderIds([$orderId]);

        return SamedayOrderBulkAwb::formatForGrid(
            $bulkRows[$orderId] ?? null,
            $awbRows[$orderId] ?? null,
            $this,
            $orderId
        );
    }

    /**
     * @return array{summary: array, histories: array}
     */
    public function getAwbHistoryData(int $awbId): array
    {
        $awbId = (int) $awbId;
        $summaries = [];
        $histories = [];

        $sameday = new \Sameday\Sameday($this->samedayApiHelper->getSamedayClient());
        $parcels = SamedayAwbParcel::findParcelsByAwbId($awbId);

        foreach ($parcels as $parcel) {
            $request = new \Sameday\Requests\SamedayGetParcelStatusHistoryRequest($parcel['awb_number']);
            $response = $sameday->getParcelStatusHistory($request);
            $existingHistory = SamedayAwbParcelHistory::findByAwbNumber($parcel['awb_number']);
            if (is_array($existingHistory) && !empty($existingHistory['id'])) {
                $history = new SamedayAwbParcelHistory((int) $existingHistory['id']);
            } else {
                $history = new SamedayAwbParcelHistory();
            }
            $history->awb_number = $parcel['awb_number'];
            $history->summary = serialize($response->getSummary());
            $history->history = serialize($response->getHistory());
            $history->expedition = serialize($response->getExpeditionStatus());
            $history->save();

            $summaries[$parcel['awb_number']] = [
                'weight' => $response->getSummary()->getParcelWeight(),
                'delivered' => $response->getSummary()->isDelivered() ? 'Da' : 'Nu',
                'deliveredAttempts' => $response->getSummary()->getDeliveryAttempts(),
                'isPickedUp' => $response->getSummary()->isPickedUp() ? 'Da' : 'Nu',
                'isPickedUpAt' => $response->getSummary()->getPickedUpAt() ?: '',
            ];

            foreach ($response->getHistory() as $historyObject) {
                $histories[$parcel['awb_number']][] = [
                    'name' => $historyObject->getName(),
                    'label' => $historyObject->getLabel(),
                    'state' => $historyObject->getState(),
                    'date' => $historyObject->getDate(),
                    'county' => $historyObject->getCounty(),
                    'transit' => $historyObject->getTransitLocation(),
                    'reason' => $historyObject->getReason(),
                ];
            }
        }

        return [
            'summary' => $summaries,
            'histories' => $histories,
        ];
    }

    /**
     * @param $type
     * @param $content
     */
    private function addMessage($type, $content)
    {
        $this->messages[] = array(
            'type'    => $type,
            'content' => $content,
        );
    }

    /**
     * @param string $username
     * @param string $password
     * @param int $testing_mode
     * @param string $country
     * @param string $url
     *
     * @return bool
     *
     * @throws Sameday\Exceptions\SamedaySDKException
     */
    private function loginClient(
        string $username,
        string $password,
        int $testing_mode,
        string $country,
        string $url
    ): bool
    {
        $client = $this->samedayApiHelper->getSamedayClient(
            $username,
            $password,
            $url,
            $testing_mode
        );

        try{
            if ($client->login()) {
                Configuration::updateValue('SAMEDAY_LIVE_MODE', $testing_mode);
                Configuration::updateValue('SAMEDAY_HOST_COUNTRY', $country);

                return true;
            }
        } catch (Exception $exception) {
            $this->addMessage('danger', $this->l($exception->getMessage()));
        }

        return false;
    }

    /**
     * @param array $form_values
     *
     * @return bool
     * @throws Sameday\Exceptions\SamedaySDKException
     */
    private function connectionLogin(array $form_values): bool
    {
        $isLogged = false;
        $envModes = SamedayConstants::SAMEDAY_ENVS;

        foreach ($envModes as $hostCountry => $envModesByHosts) {
            if ($isLogged === true) {
                break;
            }

            foreach ($envModesByHosts as $envMode => $apiUrl) {
                if ($this->loginClient(
                    $form_values['SAMEDAY_ACCOUNT_USER'],
                    $form_values['SAMEDAY_ACCOUNT_PASSWORD'],
                    $envMode,
                    $hostCountry,
                    $apiUrl
                )) {
                    $isLogged = true;
                }
            }
        }

        return $isLogged;
    }

    /**
     * @param $code
     * @return string
     */
    private function getCarrierKey($code)
    {
        $mode = Configuration::get('SAMEDAY_LIVE_MODE', 0) ? 'PROD_' : 'TEST_';
        return "SAMEDAY_CARRIER_" . $mode . trim($code);
    }

    /**
     * @param $message
     * @param $level
     */
    private function log($message, $level)
    {
        $this->logger->log($message, $level);
    }

    /**
     * @param \Sameday\Exceptions\SamedayBadRequestException $exception
     *
     * @return string
     */
    private function formatSamedayBadRequestMessage(\Sameday\Exceptions\SamedayBadRequestException $exception): string
    {
        $rawBody = '';
        if (method_exists($exception, 'getRawResponse') && $exception->getRawResponse()) {
            $rawBody = (string) $exception->getRawResponse()->getBody();
            $this->log($rawBody, SamedayConstants::ERROR);
        }

        $this->log($exception->getErrors(), SamedayConstants::ERROR);

        $parts = [$this->l('Error while generating AWB.')];

        foreach ($exception->getErrors() as $error) {
            $field = '';
            if (!empty($error['key']) && is_array($error['key'])) {
                $field = implode(', ', $error['key']);
            }

            $messages = $error['errors'] ?? '';
            if (is_array($messages)) {
                $message = implode(', ', array_map('strval', $messages));
            } else {
                $message = (string) $messages;
            }

            if ($field !== '' && $message !== '') {
                $parts[] = $field . ' - ' . $message;
            } elseif ($message !== '') {
                $parts[] = $message;
            }
        }

        foreach ($this->extractSamedayValidationMessagesFromBody($rawBody) as $message) {
            if (!in_array($message, $parts, true)) {
                $parts[] = $message;
            }
        }

        $exceptionMessage = trim((string) $exception->getMessage());
        if (
            $exceptionMessage !== ''
            && !in_array($exceptionMessage, $parts, true)
            && stripos($exceptionMessage, 'validation failed') === false
        ) {
            $parts[] = $exceptionMessage;
        }

        return implode(' ', array_filter($parts));
    }

    /**
     * @param string $body
     *
     * @return string[]
     */
    private function extractSamedayValidationMessagesFromBody(string $body): array
    {
        if ($body === '') {
            return [];
        }

        $json = json_decode($body, true);
        if (!is_array($json)) {
            return [];
        }

        $messages = [];
        $collect = static function ($node, string $path) use (&$collect, &$messages) {
            if (!is_array($node)) {
                return;
            }

            if (!empty($node['errors']) && is_array($node['errors'])) {
                foreach ($node['errors'] as $error) {
                    if (!is_string($error) || $error === '') {
                        continue;
                    }

                    $message = $path !== '' ? $path . ' - ' . $error : $error;
                    if (!in_array($message, $messages, true)) {
                        $messages[] = $message;
                    }
                }
            }

            if (!empty($node['children']) && is_array($node['children'])) {
                foreach ($node['children'] as $childKey => $childNode) {
                    if (is_array($childNode)) {
                        $childPath = is_int($childKey)
                            ? $path
                            : ($path === '' ? (string) $childKey : $path . '.' . $childKey);
                        $collect($childNode, $childPath);
                    }
                }
            }
        };

        if (!empty($json['errors']) && is_array($json['errors'])) {
            $collect($json['errors'], '');
        }

        return $messages;
    }

    /**
     * @param int $orderId
     *
     * @return string
     */
    private function buildAwbClientReference(int $orderId): string
    {
        return $orderId . '-' . time();
    }

    /**
     * @param array $service
     * @param AddressCore $address
     * @param int $orderId
     *
     * @return string|null
     */
    private function validateBulkAwbServiceForAddress(array $service, AddressCore $address, int $orderId)
    {
        $destCountryIso = strtolower((string) CountryCore::getIsoById((int) $address->id_country));
        $hostCountry = strtolower($this->generalHelper->getHostCountry());
        $isCrossBorderShipment = $hostCountry !== $destCountryIso;
        $serviceCode = (string) ($service['code'] ?? '');

        if ($isCrossBorderShipment) {
            if (!in_array($serviceCode, SamedayConstants::ELIGIBLE_FOR_CROSSBORDER, true)) {
                return sprintf(
                    $this->l('Order #%d uses domestic service "%s" but delivery country is "%s". Use a cross-border Sameday service.'),
                    $orderId,
                    $serviceCode,
                    strtoupper($destCountryIso)
                );
            }

            return null;
        }

        if (
            in_array($serviceCode, SamedayConstants::ELIGIBLE_FOR_CROSSBORDER, true)
            && !in_array($serviceCode, SamedayConstants::ELIGIBLE_SERVICES, true)
        ) {
            return sprintf(
                $this->l('Order #%d uses cross-border service "%s" but delivery country is "%s". Use a domestic Sameday service.'),
                $orderId,
                $serviceCode,
                strtoupper($destCountryIso)
            );
        }

        return null;
    }

    /**
     * @param string $serviceCode
     *
     * @return bool
     */
    private function isServiceEligibleToLocker(string $serviceCode): bool
    {
        return (
            $serviceCode === SamedayConstants::LOCKER_NEXT_DAY_CODE
            || $serviceCode === SamedayConstants::LOCKER_NEXT_DAY_CROSSBORDER_CODE
            || $serviceCode === SamedayConstants::PUDO_CODE
        );
    }

    private function isServiceEligibleToPdo($serviceAdditionalTaxes): bool
    {
        if (('' !== $serviceAdditionalTaxes)
            && false !== $serviceAdditionalTaxes = unserialize($serviceAdditionalTaxes, [''])
        ) {
            foreach ($serviceAdditionalTaxes as $tax) {
                if ($tax['code'] === SamedayConstants::PERSONAL_DELIVERY_OPTION_CODE) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param bool $toShow
     *
     * @return string
     */
    private function toggleHtmlElement(bool $toShow): string
    {
        if ($toShow) {
            return SamedayConstants::TOGGLE_HTML_ELEMENT['show'];
        }

        return SamedayConstants::TOGGLE_HTML_ELEMENT['hide'];
    }

    private function isAdminOrdersListPage(): bool
    {
        if (!isset($this->context->controller)) {
            return false;
        }

        $controllerName = $this->context->controller->controller_name
            ?? $this->context->controller->php_self
            ?? '';

        if ($controllerName !== 'AdminOrders') {
            return false;
        }

        if ($this->isAdminOrderDetailPage()) {
            return false;
        }

        if (class_exists('\Symfony\Component\HttpFoundation\Request')) {
            $request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
            $route = (string) $request->attributes->get('_route', '');
            if ($route !== '' && $route !== 'admin_orders_index') {
                return false;
            }
        }

        return true;
    }

    /**
     * Register legacy list hooks when upgrade was skipped (e.g. files copied without BO upgrade).
     * Called from actionAdminControllerSetMedia, which runs before processFilter/renderList.
     *
     * @return void
     */
    private function ensureBulkAwbLegacyOrdersListHooksRegistered()
    {
        if (!$this->isBulkAwbLegacyFeedbackColumnSupported()) {
            return;
        }

        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        $hooks = [
            'actionAdminOrdersListingFieldsModifier',
            'actionAdminOrdersListingResultsModifier',
        ];

        $registeredAny = false;
        foreach ($hooks as $hook) {
            if (!$this->isRegisteredInHook($hook)) {
                if ($this->registerHook($hook)) {
                    $registeredAny = true;
                }
            }
        }

        if (!$registeredAny) {
            return;
        }

        if (class_exists('Cache')) {
            $context = $this->context;
            $cacheId = 'hook_module_exec_list_'
                . (isset($context->shop->id) ? '_' . $context->shop->id : '')
                . (isset($context->customer) ? '_' . $context->customer->id : '');
            Cache::clean($cacheId);
        }
    }

    /**
     * Symfony orders grid (PS 1.7.7+): toolbar in displayAdminAfterHeader; JS enables
     * Generate/Remove only after order row checkboxes are selected.
     *
     * @return bool
     */
    private function shouldRenderBulkAwbOnAdminAfterHeader()
    {
        return $this->isBulkAwbGridSupported()
            && $this->isAdminOrdersListPage();
    }

    /**
     * Legacy orders list (PS 1.6): displayAdminAfterHeader is not executed.
     *
     * @return bool
     */
    private function shouldRenderBulkAwbOnBackOfficeTop()
    {
        return $this->isBulkAwbSupported()
            && !$this->isBulkAwbGridSupported()
            && $this->isAdminOrdersListPage();
    }

    /**
     * @return string
     */
    private function renderBulkAwbOrdersListMarkup()
    {
        $this->context->smarty->assign([
            'sameday_bulk_awb_enabled' => true,
        ]);

        return $this->display(__FILE__, 'views/templates/admin/bulk_awb.tpl');
    }

    private function isAdminOrderDetailPage(): bool
    {
        if (Tools::getIsset('id_order') || Tools::getIsset('vieworder')) {
            return true;
        }

        if ((int) Tools::getValue('orderId') > 0) {
            return true;
        }

        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        if (preg_match('#/orders/\d+/view(?:[/?]|$)#', $requestUri)) {
            return true;
        }

        if (class_exists('\Symfony\Component\HttpFoundation\Request')) {
            $request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
            if ($request->attributes->get('_route') === 'admin_orders_view') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{success: bool, order_id: int, awb_number?: string, error?: string, skipped?: bool, message?: string}
     */
    public function addAwbBulk(int $orderId): array
    {
        $orderId = (int) $orderId;
        $result = [
            'success' => false,
            'order_id' => $orderId,
        ];

        $existingAwb = SamedayAwb::getOrderAwb($orderId);
        if (!empty($existingAwb['awb_number'])) {
            return array_merge($result, [
                'skipped' => true,
                'awb_number' => $existingAwb['awb_number'],
                'message' => $this->l('AWB is already generated.'),
            ]);
        }

        $order = new Order($orderId);
        if (!Validate::isLoadedObject($order)) {
            return array_merge($result, [
                'error' => $this->l('Order not found.'),
            ]);
        }

        $service = SamedayService::findByCarrierId($order->id_carrier);
        if (false === $service || empty($service['id_service'])) {
            return array_merge($result, [
                'error' => $this->l('Order carrier is not a Sameday service.'),
            ]);
        }

        $pickupPoint = SamedayPickupPoint::resolveForAwb();
        if (empty($pickupPoint['id_pickup_point'])) {
            return array_merge($result, [
                'error' => $this->l('Default pickup point not found.'),
            ]);
        }

        $customer = new CustomerCore($order->id_customer);
        $address = new AddressCore($order->id_address_delivery);
        $stateName = StateCore::getNameById($address->id_state);

        $serviceValidationError = $this->validateBulkAwbServiceForAddress($service, $address, $orderId);
        if (null !== $serviceValidationError) {
            return array_merge($result, [
                'error' => $serviceValidationError,
            ]);
        }

        $company = null;
        if (!empty($address->company)) {
            $company = new \Sameday\Objects\PostAwb\Request\CompanyEntityObject(
                $address->company,
                $address->vat_number,
                $address->dni,
                '',
                ''
            );
        }

        $phone = !empty($address->phone_mobile) ? $address->phone_mobile : $address->phone;
        if ('' === $phone) {
            return array_merge($result, [
                'error' => $this->l('Must complete phone number!'),
            ]);
        }

        $email = $customer->email ?? '';
        if ('' === $email) {
            return array_merge($result, [
                'error' => $this->l('Must complete email!'),
            ]);
        }

        $packageType = \Sameday\Objects\Types\PackageType::PARCEL;
        $weight = $order->getTotalWeight();
        if ($weight < 0.1) {
            $weight = 1;
        }
        $parcelDimensions = [new \Sameday\Objects\ParcelDimensionsObject($weight)];

        $recipient = new \Sameday\Objects\PostAwb\Request\AwbRecipientEntityObject(
            $address->city,
            $stateName,
            trim($address->address1 . ' ' . $address->address2),
            $address->firstname . ' ' . $address->lastname,
            $phone,
            $email,
            $company,
            (!empty($address->postcode)) ? $address->postcode : null
        );

        $lockerLastMileId = null;
        $oohLastMileId = null;
        $lockerName = null;
        $lockerAddress = null;
        $samedayOrderLockerId = null;

        if ($this->isServiceEligibleToLocker((string) $service['code'])) {
            $locker = SamedayOrderLocker::getLockerForOrder($order->id);
            if (null === $locker || empty($locker['id_locker'])) {
                return array_merge($result, [
                    'error' => $this->l('Locker details are required for this service.'),
                ]);
            }

            $lockerLastMileId = (int) $locker['id_locker'];
            if ($service['code'] === SamedayConstants::PUDO_CODE) {
                $oohLastMileId = (int) $locker['id_locker'];
            }

            $lockerName = $locker['name_locker'] ?? '';
            $lockerAddress = $locker['address_locker'] ?? '';
            $samedayOrderLockerId = $locker['id'] ?? null;
        }

        $serviceTaxIds = [];
        if ((int) SamedayOpenPackage::checkOrderIfIsOpenPackage($order->id) > 0) {
            $optionalTaxIds = unserialize($service['service_optional_taxes'], ['']);
            if (false !== $optionalTaxIds) {
                foreach ($optionalTaxIds as $optionalService) {
                    if (
                        $optionalService['code'] === SamedayConstants::OPENPACKAGECODE
                        && (int) $optionalService['type'] === (int) $packageType
                    ) {
                        $serviceTaxIds[] = $optionalService['id'];
                        break;
                    }
                }
            }
        }

        $repayment = 0.0;
        if ($this->checkForCashPayment($order->payment)) {
            $repayment = number_format($order->total_paid, 2, '.', '');
        }

        $request = new \Sameday\Requests\SamedayPostAwbRequest(
            (int) $pickupPoint['id_pickup_point'],
            null,
            new \Sameday\Objects\Types\PackageType($packageType),
            $parcelDimensions,
            (int) $service['id_service'],
            new \Sameday\Objects\Types\AwbPaymentType(\Sameday\Objects\Types\AwbPaymentType::CLIENT),
            $recipient,
            0,
            $repayment,
            new \Sameday\Objects\Types\CodCollectorType(\Sameday\Objects\Types\CodCollectorType::CLIENT),
            null,
            $serviceTaxIds,
            null,
            $this->buildAwbClientReference((int) $order->id),
            '',
            '',
            '',
            null,
            $lockerLastMileId,
            null,
            $oohLastMileId,
            $this->getDestCurrencyByDestCountryCode(strtolower(CountryCore::getIsoById($address->id_country)))
        );

        if (Configuration::get('SAMEDAY_DEBUG_MODE', 0)) {
            $this->log('Bulk generate awb', SamedayConstants::DEBUG);
            $this->log($request, SamedayConstants::DEBUG);
        }

        try {
            $sameday = new \Sameday\Sameday($this->samedayApiHelper->getSamedayClient());
            $response = $sameday->postAwb($request);
            $samedayAwb = new SamedayAwb();
            $samedayAwb->id_order = $order->id;
            $samedayAwb->awb_cost = $response->getCost();
            $samedayAwb->awb_number = $response->getAwbNumber();
            $samedayAwb->created = date('Y-m-d H:i:s');
            if ($samedayAwb->save()) {
                foreach ($response->getParcels() as $parcel) {
                    $samedayAwbParcel = new SamedayAwbParcel();
                    $samedayAwbParcel->id_awb = $samedayAwb->id;
                    $samedayAwbParcel->awb_number = $parcel->getAwbNumber();
                    $samedayAwbParcel->position = $parcel->getPosition();
                    $samedayAwbParcel->save();
                }
            }

            $orderCarrier = new OrderCarrier((int) $order->getIdOrderCarrier());
            $orderCarrier->tracking_number = $response->getAwbNumber();
            $orderCarrier->update();

            $order->id_carrier = $service['id_carrier'];
            $order->shipping_number = $samedayAwb->awb_number;
            $order->update();

            if (
                null !== $lockerLastMileId
                && $service['code'] === SamedayConstants::LOCKER_NEXT_DAY_CODE
            ) {
                if (empty($samedayOrderLockerId)) {
                    $orderLocker = new SamedayOrderLocker();
                    $orderLocker->id_order = $order->id;
                } else {
                    $orderLocker = new SamedayOrderLocker((int) $samedayOrderLockerId);
                }

                $orderLocker->id_locker = $lockerLastMileId;
                $orderLocker->name_locker = $lockerName;
                $orderLocker->address_locker = $lockerAddress;
                $orderLocker->service_code = $service['code'];
                $orderLocker->save();
            }

            return [
                'success' => true,
                'order_id' => $orderId,
                'awb_number' => $samedayAwb->awb_number,
                'message' => $this->l('AWB was generated.'),
            ];
        } catch (\Sameday\Exceptions\SamedayBadRequestException $e) {
            $errorMessage = $this->formatSamedayBadRequestMessage($e);
            if (stripos($errorMessage, 'pickup') !== false) {
                $errorMessage .= ' ' . sprintf(
                    $this->l('(pickup point ID: %s, alias: %s)'),
                    (int) $pickupPoint['id_pickup_point'],
                    $pickupPoint['sameday_alias'] ?? ''
                );
            }

            return array_merge($result, [
                'error' => $errorMessage,
            ]);
        } catch (Exception $e) {
            $this->log($e->getMessage() . $e->getTraceAsString(), SamedayConstants::ERROR);

            return array_merge($result, [
                'error' => sprintf('Error Nr. %s: %s', $e->getCode(), $this->l($e->getMessage())),
            ]);
        }
    }

    /**
     * @return array{success: bool, order_id: int, awb_number?: string, error?: string, message?: string}
     */
    public function cancelAwbBulk(int $orderId): array
    {
        $orderId = (int) $orderId;
        $result = [
            'success' => false,
            'order_id' => $orderId,
        ];

        $awb = SamedayAwb::getOrderAwb($orderId);
        if (empty($awb['awb_number'])) {
            return array_merge($result, [
                'error' => $this->l('AWB not found for this order.'),
            ]);
        }

        try {
            $sameday = new Sameday\Sameday($this->samedayApiHelper->getSamedayClient());

            if (SamedayAwb::cancelAwbByOrderId($orderId)) {
                SamedayAwbParcel::deleteAwbParcels($awb['id']);
                $request = new Sameday\Requests\SamedayDeleteAwbRequest($awb['awb_number']);
                if (Configuration::get('SAMEDAY_DEBUG_MODE', 0)) {
                    $this->log('Bulk cancel awb', SamedayConstants::DEBUG);
                    $this->log($request, SamedayConstants::DEBUG);
                }
                $sameday->deleteAwb($request);
                $orderEntity = new Order($orderId);
                $orderCarrier = new OrderCarrier((int) $orderEntity->getIdOrderCarrier());
                $orderCarrier->tracking_number = null;
                $orderCarrier->update();

                SamedayOrderBulkAwb::deleteByOrderId($orderId);

                return [
                    'success' => true,
                    'order_id' => $orderId,
                    'awb_number' => $awb['awb_number'],
                    'message' => $this->l('AWB was canceled'),
                ];
            }
        } catch (Sameday\Exceptions\SamedayOtherException $e) {
            $response = json_decode($e->getRawResponse()->getBody(), true);
            $this->log($e->getRawResponse()->getBody(), SamedayConstants::ERROR);

            return array_merge($result, [
                'error' => $response['error']['message'] ?? $this->l('An error occurred while trying to cancel AWB'),
            ]);
        } catch (Exception $e) {
            $this->log($e->getMessage(), SamedayConstants::ERROR);

            return array_merge($result, [
                'error' => $this->l('An error occurred while trying to cancel AWB'),
            ]);
        }

        return array_merge($result, [
            'error' => $this->l('An error occurred while trying to cancel AWB'),
        ]);
    }

    public function hookDisplayBackOfficeTop($params)
    {
        if (!$this->shouldRenderBulkAwbOnBackOfficeTop()) {
            return '';
        }

        return $this->renderBulkAwbOrdersListMarkup();
    }

    public function hookActionAdminControllerSetMedia($params)
    {
        if (!$this->isBulkAwbSupported() || !$this->isAdminOrdersListPage()) {
            return;
        }

        $this->ensureBulkAwbLegacyOrdersListHooksRegistered();

        $this->context->controller->addCSS($this->_path . 'views/css/bulkAwb.css');
        $this->context->controller->addJS($this->_path . 'views/js/bulkAwb.js');

        Media::addJsDef([
            'samedayBulkAwb' => [
                'ajaxUrl' => $this->getBulkAwbAjaxUrl(),
                'token' => $this->getBulkAwbAdminToken(),
                'labels' => [
                    'csvOrderId' => $this->l('Order ID'),
                    'csvStatus' => $this->l('Status'),
                    'csvMessage' => $this->l('Message'),
                    'csvAwb' => $this->l('AWB Number'),
                    'statusSuccess' => $this->l('Success'),
                    'statusFailed' => $this->l('Failed'),
                    'statusSkipped' => $this->l('Skipped'),
                    'removeConfirm' => $this->l('Remove AWB for order #%d?'),
                    'removeFailed' => $this->l('Could not remove AWB.'),
                    'historyFailed' => $this->l('Error occurred while retrieving AWB history.'),
                    'noRecords' => $this->l('No records'),
                ],
            ],
        ]);
    }

    /**
     * HtmlColumn exists from PS 8; PS 1.7 uses SamedayGridHtmlColumn + module twig override.
     *
     * @return \PrestaShop\PrestaShop\Core\Grid\Column\ColumnInterface
     */
    private function createSamedayFeedbackGridColumn()
    {
        $options = [
            'field' => 'sameday_feedback',
            'clickable' => false,
        ];
        $name = $this->l('Sameday feedback');

        if (class_exists('PrestaShop\\PrestaShop\\Core\\Grid\\Column\\Type\\Common\\HtmlColumn')) {
            return (new PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\HtmlColumn('sameday_feedback'))
                ->setName($name)
                ->setOptions($options);
        }

        return (new SamedayGridHtmlColumn('sameday_feedback'))
            ->setName($name)
            ->setOptions($options);
    }

    public function hookActionAdminOrdersListingFieldsModifier(array $params)
    {
        if (!$this->isBulkAwbLegacyFeedbackColumnSupported()) {
            return;
        }

        $column = [
            'title' => $this->l('Sameday feedback'),
            'align' => 'text-left',
            'class' => 'column-sameday_feedback',
            'callback' => 'renderLegacySamedayFeedback',
            'callback_object' => $this,
            'orderby' => false,
            'search' => false,
            'remove_onclick' => true,
        ];

        if (isset($params['fields']['id_pdf'])) {
            $params['fields'] = $this->insertArrayKeyAfter(
                $params['fields'],
                'id_pdf',
                'sameday_feedback',
                $column
            );

            return;
        }

        $params['fields']['sameday_feedback'] = $column;
    }

    public function hookActionAdminOrdersListingResultsModifier(array $params)
    {
        if (!$this->isBulkAwbLegacyFeedbackColumnSupported()) {
            return;
        }

        $list = &$params['list'];
        if ($list === []) {
            return;
        }

        $orderIds = array_map('intval', array_column($list, 'id_order'));
        $bulkRows = SamedayOrderBulkAwb::getByOrderIds($orderIds);
        $awbRows = SamedayAwb::getByOrderIds($orderIds);

        foreach ($list as &$row) {
            $orderId = (int) $row['id_order'];
            $row['sameday_feedback'] = SamedayOrderBulkAwb::formatForGrid(
                $bulkRows[$orderId] ?? null,
                $awbRows[$orderId] ?? null,
                $this,
                $orderId
            );
        }
        unset($row);
    }

    /**
     * Legacy list callback: output pre-rendered HTML without Smarty escaping.
     *
     * @param mixed $html
     * @param array $tr
     *
     * @return string
     */
    public function renderLegacySamedayFeedback($html, array $tr)
    {
        return is_string($html) ? $html : '';
    }

    /**
     * @param array $array
     * @param string $afterKey
     * @param string $newKey
     * @param mixed $newValue
     *
     * @return array
     */
    private function insertArrayKeyAfter(array $array, $afterKey, $newKey, $newValue)
    {
        $result = [];
        foreach ($array as $key => $value) {
            $result[$key] = $value;
            if ($key === $afterKey) {
                $result[$newKey] = $newValue;
            }
        }

        return $result;
    }

    public function hookActionOrderGridDefinitionModifier(array $params)
    {
        if (!$this->isBulkAwbGridSupported() || !$this->isBulkAwbFeedbackColumnSupported()) {
            return;
        }

        /** @var \PrestaShop\PrestaShop\Core\Grid\Definition\GridDefinitionInterface $definition */
        $definition = $params['definition'];

        if ($definition->getId() !== 'order') {
            return;
        }

        $definition->getColumns()->addBefore(
            'actions',
            $this->createSamedayFeedbackGridColumn()
        );
    }

    public function hookActionOrderGridDataModifier(array $params)
    {
        if (!$this->isBulkAwbGridSupported() || !$this->isBulkAwbFeedbackColumnSupported()) {
            return;
        }

        /** @var \PrestaShop\PrestaShop\Core\Grid\Data\GridData $data */
        $data = $params['data'];
        $records = $data->getRecords()->all();

        if ($records === []) {
            return;
        }

        $orderIds = array_map('intval', array_column($records, 'id_order'));
        $bulkRows = SamedayOrderBulkAwb::getByOrderIds($orderIds);
        $awbRows = SamedayAwb::getByOrderIds($orderIds);

        foreach ($records as &$record) {
            $orderId = (int) $record['id_order'];
            $record['sameday_feedback'] = SamedayOrderBulkAwb::formatForGrid(
                $bulkRows[$orderId] ?? null,
                $awbRows[$orderId] ?? null,
                $this,
                $orderId
            );
        }
        unset($record);

        $params['data'] = new PrestaShop\PrestaShop\Core\Grid\Data\GridData(
            new PrestaShop\PrestaShop\Core\Grid\Record\RecordCollection($records),
            $data->getRecordsTotal(),
            $data->getQuery()
        );
    }
}
