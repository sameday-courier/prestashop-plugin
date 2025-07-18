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
        $cities = [];
        foreach (SamedayConstants::DEFAULTS_COUNTRIES as $countryCode => $country) {
            $countryId = Db::getInstance()->getRow(
                sprintf(
                    "SELECT id_country FROM %s WHERE iso_code = '%s'",
                    _DB_PREFIX_ . "country",
                    strtoupper($countryCode)
                )
            )['id_country'] ?? null;

            if (null === $countryId) {
                continue;
            }

            $states = Db::getInstance()->executeS(
                sprintf("SELECT * FROM %s WHERE `id_country` = '%s'",
                    _DB_PREFIX_ . 'state',
                    $countryId
                )
            );

            if (empty($states)) {
                continue;
            }

            foreach ($states as $state) {
                $cities[$countryId][$state['id_state']] =  Db::getInstance()->executeS(
                    sprintf("SELECT city_name AS name FROM %s WHERE `country_code` = '%s' AND `county_code` = '%s'",
                        _DB_PREFIX_ . self::TABLE_NAME,
                        $countryCode,
                        $state['iso_code']
                    )
                );
            }
        }

        return $cities;
    }
}
