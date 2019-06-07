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

class SamedayLocker extends ObjectModel
{
    const TABLE_NAME = 'sameday_lockers';

    /** @var integer */
    public $id;

    /** @var integer */
    public $id_locker;

    /** @var string */
    public $name;

    /** @var string */
    public $county;

    /** @var string */
    public $city;

    /** @var string */
    public $address;

    /** @var string */
    public $postal_code;

    /** @var string */
    public $lat;

    /** @var string */
    public $long;

    public $live_mode = 0;

    /** @var array */
    public static $definition = array(
        'table' => self::TABLE_NAME,
        'primary' => 'id',
        'multilang' => false,
        'multilang_shop' => false,
        'fields' => array(
            'id_locker' => array('type' => self::TYPE_INT, 'required' => true, 'validate' => 'isUnsignedInt'),
            'name' => array('type' => self::TYPE_STRING, 'required' => true, 'validate' => 'isCleanHtml'),
            'county' => array('type' => self::TYPE_STRING, 'required' => true, 'validate' => 'isCleanHtml'),
            'city' => array('type' => self::TYPE_STRING, 'required' => true, 'validate' => 'isCleanHtml'),
            'address' => array('type' => self::TYPE_STRING, 'required' => true, 'validate' => 'isCleanHtml'),
            'postal_code' => array('type' => self::TYPE_STRING, 'required' => false, 'validate' => 'isCleanHtml'),
            'lat' => array('type' => self::TYPE_STRING, 'required' => false, 'validate' => 'isCleanHtml'),
            'long' => array('type' => self::TYPE_STRING, 'required' => false, 'validate' => 'isCleanHtml'),
            'live_mode' => array('type' => self::TYPE_BOOL, 'required' => false, 'validate' => 'isBool'),
        ),
    );

    public static function getLockers($skipImport = false)
    {
        $liveMode = (int)Configuration::get('SAMEDAY_LIVE_MODE', 0);

        if (!$skipImport && time() > ((int) Configuration::get('SAMEDAY_LAST_LOCKERS')) + 86400) {
            $module = Module::getInstanceByName('samedaycourier');
            $module->importLockers();
            Configuration::updateValue('SAMEDAY_LAST_LOCKERS', time());
        }

        return Db::getInstance()->executeS(
            "SELECT * FROM " . _DB_PREFIX_ . self::TABLE_NAME . " WHERE live_mode = '{$liveMode}'"
        );
    }

    public static function findBySamedayId($id)
    {
        $liveMode = (int)Configuration::get('SAMEDAY_LIVE_MODE', 0);
        return Db::getInstance()->getRow(
            "SELECT s.* FROM " . _DB_PREFIX_ . self::TABLE_NAME .
            " s WHERE s.live_mode = '{$liveMode}' AND s.id_locker = " . (int)$id
        );
    }
}
