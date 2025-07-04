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

/**
 * Class Entity of SamedayCity
 */
class SamedayCity extends ObjectModel
{
    const TABLE_NAME = 'sameday_cities';

    /** @var integer */
    public $id;

    /** @var integer */
    public $city_id;

    /** @var string */
    public $city_name;

    /** @var string */
    public $county_code;

    /** @var string */
    public $country_code;

    /** @var string */
    public $postal_code;

    /** @var array */
    public static $definition = array(
        'table' => self::TABLE_NAME,
        'primary' => 'id',
        'multilang' => false,
        'multilang_shop' => false,
        'fields' => array(
            'city_id' => array('type' => self::TYPE_INT, 'required' => true, 'validate' => 'isUnsignedInt'),
            'city_name' => array('type' => self::TYPE_STRING, 'required' => true, 'validate' => 'isCleanHtml'),
            'county_code' => array('type' => self::TYPE_STRING, 'required' => true, 'validate' => 'isCleanHtml'),
            'country_code' => array('type' => self::TYPE_STRING, 'required' => false, 'validate' => 'isCleanHtml'),
            'postal_code' => array('type' => self::TYPE_STRING, 'required' => false, 'validate' => 'isCleanHtml'),
        ),
    );

    /**
     * @param int $cityId
     *
     * @return array|bool|object|null
     */
    public static function findByCityId(int $cityId)
    {
        return Db::getInstance()->getRow(
            sprintf(
                "SELECT * FROM %s WHERE `city_id` = %d",
                _DB_PREFIX_ . self::TABLE_NAME,
                $cityId
            )
        );
    }

    /**
     * @return array
     */
    public static function getCitiesCachedResult(): array
    {
        $cities = Cache::getInstance()->get('sameday_cities');

        if (false === $cities) {
            $cities = self::getCities();
            Cache::getInstance()->set('sameday_cities', $cities);
        }

        return $cities;
    }

    /**
     * @return array
     */
    public static function getCities(): array
    {
        $countries = [];
        foreach (SamedayConstants::DEFAULTS_COUNTRIES as $key => $country) {
            $countries[$key] = Db::getInstance()->getRow(
                sprintf(
                    "SELECT id_country FROM %s WHERE iso_code = '%s'",
                    _DB_PREFIX_ . "country",
                    strtoupper($key)
                )
            )['id_country'];
        }

        $cities = [];
        foreach ($countries as $countryCode => $countryId) {
            $queriedCities = Db::getInstance()->executeS(
                sprintf("SELECT * FROM %s WHERE `country_code` = '%s'",
                    _DB_PREFIX_ . self::TABLE_NAME,
                    $countryCode
                )
            );
            foreach ($queriedCities as $city) {
                $stateId = Db::getInstance()->getRow(
                    sprintf(
                        "SELECT id_state FROM %s WHERE id_country = '%s' AND iso_code = '%s'",
                        _DB_PREFIX_ . "state",
                        $countryId,
                        $city['county_code']
                    )
                )['id_state'] ?? null;
                if (null !== $stateId) {
                    $cities[$countryId][$stateId][] =  $city;
                }
            }
        }

        return $cities;
    }
}
