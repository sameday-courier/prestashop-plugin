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
}
