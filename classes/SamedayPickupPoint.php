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

class SamedayPickupPoint extends ObjectModel
{
    const TABLE_NAME = "sameday_pickup_points";

    /** @var integer */
    public $id;

    /** @var integer */
    public $id_pickup_point;

    /** @var string */
    public $sameday_alias;

    /** @var string */
    public $county;

    /** @var string */
    public $city;

    /** @var string */
    public $address;

    /** @var int */
    public $is_default;

    public $live_mode = 0;

    /** @var array */
    public static $definition = array(
        'table'          => self::TABLE_NAME,
        'primary'        => 'id',
        'multilang'      => false,
        'multilang_shop' => false,
        'fields'         => array(
            'id_pickup_point' => array('type' => self::TYPE_INT, 'required' => true, 'validate' => 'isUnsignedInt'),
            'sameday_alias'   => array('type' => self::TYPE_STRING, 'required' => true, 'validate' => 'isCleanHtml'),
            'county'          => array('type' => self::TYPE_STRING, 'required' => true, 'validate' => 'isCleanHtml'),
            'city'            => array('type' => self::TYPE_STRING, 'required' => true, 'validate' => 'isCleanHtml'),
            'address'         => array('type' => self::TYPE_STRING, 'required' => true, 'validate' => 'isCleanHtml'),
            'is_default'      => array('type' => self::TYPE_BOOL, 'required' => false, 'validate' => 'isBool'),
            'live_mode'       => array('type' => self::TYPE_BOOL, 'required' => false, 'validate' => 'isBool'),
        ),
    );

    public static function getPickupPoints()
    {
        $liveMode = (int)Configuration::get('SAMEDAY_LIVE_MODE', 0);
        return Db::getInstance()->executeS(
            "SELECT * FROM " . _DB_PREFIX_ . self::TABLE_NAME . " WHERE live_mode = '{$liveMode}'"
        );
    }

    public static function findBySamedayId($id)
    {
        $liveMode = (int)Configuration::get('SAMEDAY_LIVE_MODE', 0);
        return Db::getInstance()->getRow(
            "SELECT s.* FROM " . _DB_PREFIX_ . self::TABLE_NAME .
            " s WHERE s.live_mode = '{$liveMode}' AND s.id_pickup_point = " . (int)$id
        );
    }
}
