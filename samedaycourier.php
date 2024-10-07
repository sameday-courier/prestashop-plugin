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
    private $generalHelper;

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
            'open_package_option' => 'checkout_open_package.v16.tpl'
        ],
        '1.7' => [
            'locker_options_map' => 'checkout_lockers.v17.tpl',
            'locker_options_selector' => 'checkout_lockers_selector.v17.tpl',
            'open_package_option' => 'checkout_open_package.v17.tpl'
        ]
    ];

    const DEFAULT_VALUE_LOCKER_MAX_ITEMS = 5;

    const DEFAULT_HOST_COUNTRY = 'ro';

    /**
     * Cash on delivery
     */
    const COD = ['Cod', 'Ramburs'];

    const CURRENCIES = [
        'RON' => SamedayConstants::API_HOST_LOCALE_RO,
        'HUF' => SamedayConstants::API_HOST_LOCALE_HU,
        'BGN' => SamedayConstants::API_HOST_LOCALE_BG,
    ];

    /**
     * SamedayCourier constructor.
     */
    public function __construct()
    {
        $this->name = 'samedaycourier';
        $this->tab = 'shipping_logistics';

        $this->version = '1.7.3';
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
        $this->ajaxRoute = $this->baseUrl()._MODULE_DIR_.'samedaycourier/ajax.php?token=' . Tools::substr(Tools::encrypt(Configuration::get('SAMEDAY_CRON_TOKEN')), 0, 10);
        $this->generalHelper = new SamedayGeneralHelper();
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
        if (($this->getMajorVersion() === 1) && ($this->getMinorVersion() === 6)) {
            $hookDisplayAdminOrder = 'displayAdminOrderContentShip';
        }

        return parent::install() &&
            $this->registerHook('actionCarrierUpdate') &&
            (version_compare(_PS_VERSION_, '1.7.0.0') < 0
                ? $this->registerHook('extraCarrier')
                : $this->registerHook('displayCarrierExtraContent')) &&
            $this->registerHook('displayAdminAfterHeader') &&
            $this->registerHook($hookDisplayAdminOrder) &&
            $this->registerHook('actionValidateOrder') &&
            $this->registerHook('actionCarrierProcess') &&
            $this->registerHook('actionValidateStepComplete')
        ;
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
        // Configuration::deleteByName('SAMEDAY_ORDER_STATUS_AWB');
        Configuration::deleteByName('SAMEDAY_DEBUG_MODE');
        Configuration::deleteByName('SAMEDAY_ESTIMATED_COST');
        Configuration::deleteByName('SAMEDAY_OPEN_PACKAGE');
        Configuration::deleteByName('SAMEDAY_LOCKERS_MAP');
        Configuration::deleteByName('SAMEDAY_OPEN_PACKAGE_LABEL');
        Configuration::deleteByName('SAMEDAY_LOCKER_MAX_ITEMS');
        Configuration::deleteByName('SAMEDAY_AWB_PDF_FORMAT');
        Configuration::deleteByName('SAMEDAY_LAST_SYNC');
        Configuration::deleteByName('SAMEDAY_STATUS_MODE');
        Configuration::deleteByName('SAMEDAY_LAST_LOCKERS');
        Configuration::deleteByName('SAMEDAY_TOKEN');
        Configuration::deleteByName('SAMEDAY_TOKEN_EXPIRES_AT');

        $services = SamedayService::getAllServices();
        foreach ($services as $service) {
            Configuration::deleteByName($this->getCarrierKey($service['code']));
            $carrier = new Carrier($service['id_carrier']);
            $carrier->delete();
        }

        // Uninstall SQL
        include(__DIR__ . '/sql/uninstall.php');

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

        $this->context->smarty->assign('module_dir', $this->_path);
        $this->context->smarty->assign('action_url', $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name);

        $this->renderForm();
        $this->renderServicesList();
        $this->renderPickupPointsList();
        $this->renderLockersList();

        if (Configuration::get('SAMEDAY_LIVE_MODE', 0) === 0) {
            $this->addMessage('warning', $this->l('Module Sameday Courier is working in testing mode'));
        }

        return $this->html;
    }

    /**
     * @param $user
     * @param $password
     * @param $urlEnv
     * @param $testingMode
     * @return Sameday\SamedayClient
     * @throws Sameday\Exceptions\SamedaySDKException
     */
    private function getSamedayClient($user = null, $password = null, $urlEnv = null, $testingMode = null): Sameday\SamedayClient
    {
        if ($user === null) {
            $user = Configuration::get('SAMEDAY_ACCOUNT_USER');
        }

        if ($password === null) {
            $password = Configuration::get('SAMEDAY_ACCOUNT_PASSWORD');
        }

        if ($testingMode === null) {
            $testingMode = (Configuration::get('SAMEDAY_LIVE_MODE')) ?: SamedayConstants::DEMO_MODE;
        }

        $country = (Configuration::get('SAMEDAY_HOST_COUNTRY')) ?: SamedayConstants::API_HOST_LOCALE_RO;

        if ($urlEnv === null) {
            $urlEnv = SamedayConstants::SAMEDAY_ENVS[$country][$testingMode];
        }

        return new Sameday\SamedayClient(
            $user,
            $password,
            $urlEnv,
            'Prestashop',
            _PS_VERSION_,
            'curl',
            new SamedayPersistenceDataHandler()
        );
    }

    /**
     * @throws Sameday\Exceptions\SamedaySDKException
     */
    private function importServices()
    {
        $client = $this->getSamedayClient();

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
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ),
                            array(
                                'id'    => 'active_off',
                                'value' => false,
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
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ),
                            array(
                                'id'    => 'active_off',
                                'value' => false,
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
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ),
                            array(
                                'id'    => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ),
                        ),
                    ),
                    array(
                        'type'    => 'select',
                        'label'   => $this->l('Show locker map method'),
                        'name'    => 'SAMEDAY_LOCKERS_MAP',
                        'is_bool' => true,
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
                        'desc'   => $this->l('Set the maximum amount of items to fit in locker! In order to work Locker NextDay service do not leave this field blank !!'),
                        'label'  => $this->l('Locker max. items'),
                    ),
                    array(
                        'type'    => 'switch',
                        'label'   => $this->l('Debug'),
                        'name'    => 'SAMEDAY_DEBUG_MODE',
                        'is_bool' => true,
                        'values'  => array(
                            array(
                                'id'    => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ),
                            array(
                                'id'    => 'active_off',
                                'value' => false,
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
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        $lockerMaxItems = Tools::getValue(
            'SAMEDAY_LOCKER_MAX_ITEMS',
            Configuration::get('SAMEDAY_LOCKER_MAX_ITEMS', null)
        );

        if (false === $lockerMaxItems) {
            $lockerMaxItems = self::DEFAULT_VALUE_LOCKER_MAX_ITEMS;
        }

        return array(
            'SAMEDAY_STATUS_MODE'      => Tools::getValue(
                'SAMEDAY_STATUS_MODE',
                Configuration::get('SAMEDAY_STATUS_MODE', false)
            ),
            'SAMEDAY_ACCOUNT_USER'     => Tools::getValue(
                'SAMEDAY_ACCOUNT_USER',
                Configuration::get('SAMEDAY_ACCOUNT_USER', null)
            ),
            'SAMEDAY_ACCOUNT_PASSWORD' => Tools::getValue(
                'SAMEDAY_ACCOUNT_PASSWORD',
                Configuration::get('SAMEDAY_ACCOUNT_PASSWORD', null)
            ),
            'SAMEDAY_ESTIMATED_COST' => Tools::getValue(
                'SAMEDAY_ESTIMATED_COST',
                Configuration::get('SAMEDAY_ESTIMATED_COST', null)
            ),
            'SAMEDAY_OPEN_PACKAGE' => Tools::getValue(
                'SAMEDAY_OPEN_PACKAGE',
                Configuration::get('SAMEDAY_OPEN_PACKAGE', null)
            ),
            'SAMEDAY_LOCKERS_MAP' => Tools::getValue(
                'SAMEDAY_LOCKERS_MAP',
                Configuration::get('SAMEDAY_LOCKERS_MAP', null)
            ),
            'SAMEDAY_OPEN_PACKAGE_LABEL' => Tools::getValue(
                'SAMEDAY_OPEN_PACKAGE_LABEL',
                Configuration::get('SAMEDAY_OPEN_PACKAGE_LABEL', null)
            ),
            'SAMEDAY_LOCKER_MAX_ITEMS' => $lockerMaxItems,
            'SAMEDAY_DEBUG_MODE'       => Tools::getValue(
                'SAMEDAY_DEBUG_MODE',
                Configuration::get('SAMEDAY_DEBUG_MODE', null)
            ),
            'SAMEDAY_AWB_PDF_FORMAT'   => Tools::getValue(
                'SAMEDAY_AWB_PDF_FORMAT',
                Configuration::get('SAMEDAY_AWB_PDF_FORMAT', null)
            ),
        );
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
        $helper->actions = array();
        $helper->show_toolbar = true;
        $helper->module = $this;
        $helper->title = $this->l('Pickup Points');
        $helper->shopLinkType = '';
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = $this->currentIndex;

        $this->html .= $helper->generateList($pickupPoints, $fields);
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

            if ($this->connectionLogin($form_values)) {
                //Reset old token
                $form_values[SamedayPersistenceDataHandler::KEYS[\Sameday\SamedayClient::KEY_TOKEN]] = '';
                $form_values[SamedayPersistenceDataHandler::KEYS[\Sameday\SamedayClient::KEY_TOKEN_EXPIRES]] = '';

                foreach (array_keys($form_values) as $key) {
                    Configuration::updateValue($key, Tools::getValue($key));
                }

                // Import local data Services and PickupPoints
                $this->importServices();
                $this->importPickupPoints();
            } else {
                $this->addMessage('danger', $this->l('Connection failed! Verify your credentials and try again later!'));
            }
        }

        if (Tools::isSubmit('import_services')) {
            $this->importServices();
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
        $client = $this->getSamedayClient();
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
                $pickupPoint->sameday_alias = $pickupPointObject->getAlias();
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
        $client = $this->getSamedayClient();
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

        $sameday = new \Sameday\Sameday($this->getSamedayClient());
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
            array()
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
        if (((int) Configuration::get('SAMEDAY_LIVE_MODE', 0)) === 0) {
            $name .= ' ' . $this->l('SamedayCourier');
        }

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

        return $this->display(__FILE__, 'displayAdminAfterHeader.tpl');
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
                $this->isServiceEligibleToLocker($service['code'])
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
        $oohType = null;
        $samedayOrderLockerId = null;
        if (false !== $service = SamedayService::findByCarrierId($order->id_carrier)) {
            $serviceId = $service['id_service'];
            if ($this->isServiceEligibleToLocker($service['code'])) {
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
        foreach (self::COD as $value) {
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
        $service = SamedayService::findByCarrierId($params['carrier']['id']);
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
        if ($this->isServiceEligibleToLocker($service['code'])) {
            $cart = new CartCore($params['cart']->id);
            $address = new AddressCore($cart->id_address_delivery);
            $stateName = StateCore::getNameById($address->id_state);

            $sameday_user = Configuration::get('SAMEDAY_ACCOUNT_USER');
            $countryCode = strtolower(CountryCore::getIsoById($address->id_country));
            $useLockerMap = (bool) Configuration::get('SAMEDAY_LOCKERS_MAP');

            $lockers = null;
            if (! $useLockerMap) {
                // Use locker list from Local Import
                $lockersList = SamedayLocker::getLockers();
                if (isset($lockersList) && !empty($lockersList)) {
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
            $storeLockerRoute = sprintf(
                '%s%ssamedaycourier/ajax.php?token=%s',
                $this->baseUrl(),
                _MODULE_DIR_,
                Tools::getAdminToken('Samedaycourier')
            );
            $this->smarty->assign('storeLockerRoute', $storeLockerRoute);

            if(Configuration::get('SAMEDAY_LOCKERS_MAP')) {
                return $this->display(__FILE__, self::TEMPLATE_VERSION[$fileVersion]['locker_options_map']);
            }

            return $this->display(__FILE__, self::TEMPLATE_VERSION[$fileVersion]['locker_options_selector']);
        }

        if (
            (int) Configuration::get('SAMEDAY_OPEN_PACKAGE')
            && $this->checkForOpenPackageTax($service['service_optional_taxes'])
        ) {
            $this->smarty->assign('carrier_id', $params['cart']->id_carrier);
            $this->smarty->assign('label', Configuration::get('SAMEDAY_OPEN_PACKAGE_LABEL'));

            return $this->display(__FILE__, self::TEMPLATE_VERSION[$fileVersion]['open_package_option']);
        }

        return '';
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
            && $this->isServiceEligibleToLocker($service['code'])
        ) {
            $this->context->controller->errors[] = $this->l('Please select your easyBox from lockers map !');
            $params['completed']  = false;
        }
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
            Tools::getValue('sameday_client_reference'),
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
            $sameday = new \Sameday\Sameday($this->getSamedayClient());
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
            $this->log($e->getErrors(), SamedayConstants::ERROR);
            $errors = [$this->l('Error while generating AWB.')];
            foreach ($e->getErrors() as $error) {
                $errors[] = implode(', ', $error['key']) . '- ' . implode(', ', $error['errors']);
            }
            $this->addMessage('danger', $errors);
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

        $sameday = new \Sameday\Sameday($this->getSamedayClient());

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
            $sameday = new Sameday\Sameday($this->getSamedayClient());

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
        $sameday = new Sameday\Sameday($this->getSamedayClient());
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
        $client = $this->getSamedayClient(
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
}
