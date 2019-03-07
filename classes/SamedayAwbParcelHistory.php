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

class SamedayAwbParcelHistory extends ObjectModel
{
    const TABLE_NAME = "sameday_awb_parcel_history";

    /** @var integer */
    public $id;

    /** @var string */
    public $awb_number;

    /** @var string */
    public $summary;

    /** @var string */
    public $history;

    /** @var string */
    public $expedition;

    /** @var array */
    public static $definition = array(
        'table'          => self::TABLE_NAME,
        'primary'        => 'id',
        'multilang'      => false,
        'multilang_shop' => false,
        'fields'         => array(
            'awb_number' => array('type' => self::TYPE_STRING, 'required' => true, 'validate' => 'isCleanHtml'),
            'summary'    => array('type' => self::TYPE_STRING, 'required' => false),
            'history'    => array('type' => self::TYPE_STRING, 'required' => false),
            'expedition' => array('type' => self::TYPE_STRING, 'required' => false),
        ),
    );

    public static function findByAwbNumber($awbNumber)
    {
        $awbNumber = pSQL($awbNumber);
        return Db::getInstance()->getRow(
            "SELECT p.* FROM " . _DB_PREFIX_ . self::TABLE_NAME . " p WHERE p.awb_number = '{$awbNumber}'"
        );
    }
}
