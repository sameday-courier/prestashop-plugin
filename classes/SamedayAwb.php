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

class SamedayAwb extends ObjectModel
{
    const TABLE_NAME = "sameday_awb";

    /** @var integer */
    public $id;

    public $id_order;

    /** @var string */
    public $awb_number;

    /** @var float */
    public $awb_cost;

    /** @var DateTime */
    public $created;

    /** @var array */
    public static $definition = array(
        'table'          => self::TABLE_NAME,
        'primary'        => 'id',
        'multilang'      => false,
        'multilang_shop' => false,
        'fields'         => array(
            'id_order'   => array('type' => self::TYPE_INT, 'required' => true, 'validate' => 'isUnsignedInt'),
            'awb_number' => array('type' => self::TYPE_STRING, 'required' => true, 'validate' => 'isCleanHtml'),
            'awb_cost'   => array('type' => self::TYPE_FLOAT, 'required' => true, 'validate' => 'isFloat'),
            'created'    => array('type' => self::TYPE_DATE, 'required' => false, 'validate' => 'isDate'),
        ),
    );

    public static function cancelAwbByOrderId($order)
    {
        return Db::getInstance()->delete(self::TABLE_NAME, 'id_order = ' . (int)$order, 1);
    }

    public static function getOrderAwb($order)
    {
        return Db::getInstance()->getRow(
            "SELECT a.* FROM " . _DB_PREFIX_ . self::TABLE_NAME ." a WHERE a.id_order = " . (int)$order
        );
    }

    /**
     * @param int[] $orderIds
     *
     * @return array<int, array>
     */
    public static function getByOrderIds(array $orderIds)
    {
        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
        if ($orderIds === []) {
            return [];
        }

        $rows = Db::getInstance()->executeS(
            'SELECT * FROM ' . _DB_PREFIX_ . self::TABLE_NAME .
            ' WHERE id_order IN (' . implode(',', $orderIds) . ')'
        );

        $result = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $result[(int) $row['id_order']] = $row;
            }
        }

        return $result;
    }
}
