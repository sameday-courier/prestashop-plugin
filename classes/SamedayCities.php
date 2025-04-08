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

class SamedayCities extends ObjectModel
{
    const TABLE_NAME = "sameday_cities";

    /** @var integer */
    public $id;

    /** @var string */
    public $city_name;

    /** @var integer */
    public $county_id;

    /** @var integer */
    public $sdk_id;

    /** @var array */
    public static $definition = array(
        'table'          => self::TABLE_NAME,
        'primary'        => 'id',
        'multilang'      => false,
        'multilang_shop' => false,
        'fields'         => array(
            'city_name'   => array('type' => self::TYPE_STRING, 'required' => true, 'validate' => 'isCleanHtml'),
            'county_id'   => array('type' => self::TYPE_INT, 'required' => true, 'validate' => 'isUnsignedInt'),
            'sdk_id'      => array('type' => self::TYPE_INT, 'required' => true, 'validate' => 'isUnsignedInt'),
        ),
    );

    public static function getCounties(){
        return DB::getInstance()->executeS("SELECT * FROM ps_state WHERE id_country = 36");
    }

    public static function updateCity($data){
        // Check if a city with the same sdk_id already exists
        $exists = DB::getInstance()->getRow('SELECT id FROM ' . _DB_PREFIX_ . self::TABLE_NAME . ' WHERE sdk_id = ' . (int)$data['sdk_id']);

        // If the entry already exists, return false
        if ($exists > 0) {
            return false;
        }

        // Proceed with adding the entry if it doesn't exist
        return DB::getInstance()->insert(
            self::TABLE_NAME,
            array(
                'city_name' => $data['city_name'],
                'county_id' => (int)$data['county_id'],
                'sdk_id' => (int)$data['sdk_id'],
            )
        );
    }

    public static function dropCities(){
        return DB::getInstance()->execute('TRUNCATE TABLE ' . _DB_PREFIX_ . self::TABLE_NAME);
    }

    public static function getStateByIso($countyCode, $countryId){
        return DB::getInstance()->getRow("SELECT * FROM ps_state WHERE iso_code = '$countyCode' AND id_country = '$countryId'");
    }

    public static function getCitiesByCountyId($countyId){
        return DB::getInstance()->executeS("SELECT * FROM ps_sameday_cities WHERE county_id = '$countyId'");
    }

    public static function getCountryIdByIso($countryIsoCode){
        return DB::getInstance()->getRow("SELECT * FROM ps_country WHERE iso_code = '$countryIsoCode'");
    }


}
