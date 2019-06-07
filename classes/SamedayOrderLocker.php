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

class SamedayOrderLocker extends ObjectModel
{
    const TABLE_NAME = 'sameday_order_locker';

    /** @var integer */
    public $id_order;

    /** @var integer */
    public $id_locker;

    /** @var array */
    public static $definition = array(
        'table' => self::TABLE_NAME,
        'primary' => 'id',
        'multilang' => false,
        'multilang_shop' => false,
        'fields' => array(
            'id_order' => array('type' => self::TYPE_INT, 'required' => true, 'validate' => 'isUnsignedInt'),
            'id_locker' => array('type' => self::TYPE_INT, 'required' => true, 'validate' => 'isUnsignedInt'),
        ),
    );

    public static function getLockerForOrder($orderId)
    {
        return Db::getInstance()->getValue(
            'SELECT id_locker FROM ' . _DB_PREFIX_ . self::TABLE_NAME . ' WHERE id_order=' . (int) $orderId,
            false
        );
    }
}
