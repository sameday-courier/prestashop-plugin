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

class SamedayAwbParcel extends ObjectModel
{
    const TABLE_NAME = "sameday_awb_parcel";

    /** @var integer */
    public $id;

    public $id_awb;

    /** @var string */
    public $awb_number;

    /** @var int */
    public $position;

    /** @var array */
    public static $definition = array(
        'table'          => self::TABLE_NAME,
        'primary'        => 'id',
        'multilang'      => false,
        'multilang_shop' => false,
        'fields'         => array(
            'id_awb'   => array('type' => self::TYPE_INT, 'required' => true, 'validate' => 'isUnsignedInt'),
            'awb_number' => array('type' => self::TYPE_STRING, 'required' => true, 'validate' => 'isCleanHtml'),
            'position'   => array('type' => self::TYPE_INT, 'required' => true, 'validate' => 'isUnsignedInt'),
        ),
    );

    public static function findParcelsByAwbId($awbId)
    {
        return Db::getInstance()->executeS(
            "SELECT p.* FROM " . _DB_PREFIX_ . self::TABLE_NAME . " p WHERE p.id_awb = " . (int)$awbId
        );
    }

    public static function findByAwbNumber($awbNumber)
    {
        $awbNumber = pSQL($awbNumber);
        return Db::getInstance()->getRow(
            "SELECT p.* FROM " . _DB_PREFIX_ . self::TABLE_NAME . " p WHERE p.awb_number = '{$awbNumber}'"
        );
    }

    public static function getLastPosition($awb)
    {
        return Db::getInstance()->getValue(
            "SELECT a.position FROM " . _DB_PREFIX_ . self::TABLE_NAME .
            " a WHERE a.id_awb = " . (int)$awb . " ORDER BY a.position DESC"
        );
    }

    public static function deleteAwbParcels($awbId)
    {
        return Db::getInstance()->delete(self::TABLE_NAME, 'id_awb = ' . (int)$awbId);
    }

    public static function updateStatusSync($id, $status)
    {
        return Db::getInstance()->update(
            SamedayAwbParcel::TABLE_NAME,
            array('status_sync' => serialize($status)),
            'id = '. (int) $id
        );
    }
}
