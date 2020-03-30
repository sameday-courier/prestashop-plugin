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

use Sameday\Objects\Service\ServiceObject;

class SamedayService extends ObjectModel
{
    const TABLE_NAME = "sameday_services";
    const STATUS_DISABLED = 0;
    const STATUS_ALWAYS_ACTIVE = 1;
    const STATUS_INTERVAL_ACTIVE = 2;

    /** @var integer */
    public $id;

    /** @var integer */
    public $id_service;

    /** @var string */
    public $name;

    /** @var string */
    public $code;

    /** @var float */
    public $price = 0;

    /** @var bool */
    public $free_delivery = false;

    /** @var float */
    public $free_shipping_threshold = 0;

    /** @var integer */
    public $delivery_type;

    /** @var string */
    public $delivery_type_name;

    /** @var string */
    public $working_days;

    /** @var int */
    public $id_carrier;

    /** @var int */
    public $status = 0;

    /** @var int */
    public $disabled = 0;

    /** @var bool */
    public $live_mode = false;

    /** @var string */
    public $service_optional_taxes;

    /** @var array */
    public static $definition = array(
        'table'          => self::TABLE_NAME,
        'primary'        => 'id',
        'multilang'      => false,
        'multilang_shop' => false,
        'fields'         => array(
            'id_service'              => array(
                'type' => self::TYPE_INT, 'required' => true, 'validate' => 'isUnsignedInt'
            ),
            'name'                    => array(
                'type' => self::TYPE_STRING, 'required' => true, 'validate' => 'isCleanHtml'
            ),
            'code'                    => array(
                'type' => self::TYPE_STRING, 'required' => true, 'validate' => 'isCleanHtml'
            ),
            'price'                   => array(
                'type' => self::TYPE_FLOAT, 'required' => true, 'validate' => 'isUnsignedFloat'
            ),
            'free_delivery'           => array(
                'type' => self::TYPE_INT, 'required' => false
            ),
            'free_shipping_threshold' => array(
                'type' => self::TYPE_FLOAT, 'validate' => 'isUnsignedFloat'
            ),
            'delivery_type'           => array(
                'type' => self::TYPE_INT, 'required' => true, 'validate' => 'isUnsignedInt'
            ),
            'delivery_type_name'      => array(
                'type' => self::TYPE_STRING, 'required' => true, 'validate' => 'isCleanHtml'
            ),
            'working_days'            => array(
                'type' => self::TYPE_STRING, 'required' => false, 'validate' => 'isCleanHtml'
            ),
            'id_carrier'              => array(
                'type' => self::TYPE_INT, 'required' => false, 'validate' => 'isUnsignedInt'
            ),
            'status'                  => array(
                'type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true
            ),
            'disabled'                => array(
                'type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => false
            ),
            'live_mode'               => array(
                'type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true
            ),
            'service_optional_taxes'  => array(
                'type' => self::TYPE_STRING, 'validate' => 'isCleanHtml', 'required' => false
            ),
        ),
    );

    public static function getServices($activeOnly = false, $limit = 0)
    {
        $liveMode = Configuration::get('SAMEDAY_LIVE_MODE', 0);
        $query = 'SELECT * FROM ' . _DB_PREFIX_ . self::TABLE_NAME . ' WHERE live_mode = ' .(int) $liveMode;
        if ($activeOnly) {
            $query .= ' AND `disabled` = 0';
        }

        if ($limit) {
            $query .= ' LIMIT ' . (int)$limit;
        }

        return Db::getInstance()->executeS($query);
    }

    public static function getAllServices()
    {
        return Db::getInstance()->executeS('SELECT * FROM ' . _DB_PREFIX_ . self::TABLE_NAME);
    }

    /**
     * @param ServiceObject $service
     * @param int $id
     *
     * @return bool
     */
    public static function updateService(ServiceObject $service, $id)
    {
        return Db::getInstance()->update(
            self::TABLE_NAME,
            array(
                'disabled' => 0,
                'code' => $service->getCode(),
                'service_optional_taxes' => !empty($service->getOptionalTaxes()) ? serialize($service->getOptionalTaxes()) : null
            ),
            'id =' . (int) $id
        );
    }

    /**
     * @param $id
     *
     * @return bool
     */
    public static function deleteService($id)
    {
        return Db::getInstance()->delete(
            self::TABLE_NAME,
            'id = ' . $id
        );
    }

    public static function deactivateAllServices($liveMode = false)
    {
        $liveMode = (int) Configuration::get('SAMEDAY_LIVE_MODE', 0);
        return Db::getInstance()->update(self::TABLE_NAME, array('disabled' => 1), 'live_mode = '. $liveMode);
    }

    public static function findByCode($code)
    {
        $code = pSQL($code);
        $liveMode = Configuration::get('SAMEDAY_LIVE_MODE', 0);

        return Db::getInstance()->getRow(
            "SELECT s.* FROM " . _DB_PREFIX_ . self::TABLE_NAME .
            " s WHERE s.code = '{$code}' AND s.live_mode = '{$liveMode}'"
        );
    }

    public static function updateCarrierId($id_service, $id_carrier)
    {
        return Db::getInstance()->update('sameday_services', array(
            'id_carrier' => (int)$id_carrier,
        ), 'id = ' . (int)$id_service);
    }

    public static function findByCarrierId($id_carrier)
    {
        return Db::getInstance()->getRow(
            "SELECT s.* FROM " . _DB_PREFIX_ . self::TABLE_NAME . " s WHERE s.id_carrier = " . (int)$id_carrier
        );
    }
}
