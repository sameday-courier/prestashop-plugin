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

class SamedayOrderLocker extends ObjectModel
{
    const TABLE_NAME = 'sameday_order_locker';

    /**
     * @var integer $id_order
     */
    public $id_order;

    /**
     * @var integer $id_locker
     */
    public $id_locker;

    /**
     * @var string $name_locker
     */
    public $name_locker;

    /**
     * @var string $address_locker
     */
    public $address_locker;

    /**
     * @var string $service_code
     */
    public $service_code;

    /** @var array */
    public static $definition = array(
        'table' => self::TABLE_NAME,
        'primary' => 'id',
        'multilang' => false,
        'multilang_shop' => false,
        'fields' => array(
            'id_order' => array('type' => self::TYPE_INT, 'required' => true, 'validate' => 'isUnsignedInt'),
            'id_locker' => array('type' => self::TYPE_INT, 'required' => true, 'validate' => 'isUnsignedInt'),
            'name_locker' => array('type' => self::TYPE_STRING, 'required' => true, 'validate' => 'isCleanHtml'),
            'address_locker' => array('type' => self::TYPE_STRING, 'required' => true, 'validate' => 'isCleanHtml'),
            'service_code' => array('type' => self::TYPE_STRING, 'required' => true, 'validate' => 'isCleanHtml'),
        ),
    );

    public static function getLockerForOrder($orderId)
    {
        $sql = new DbQuery();
        $sql->from(self::TABLE_NAME);
        $sql->select('*');
        $sql->where('id_order=' . $orderId);

        $locker = Db::getInstance()->getRow($sql);

        if (!empty($locker)){
            return $locker;
        }

        return null;
    }
}
