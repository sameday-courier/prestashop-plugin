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

class SamedayState extends StateCore
{
    const TABLE_NAME = 'state';

    public static function findOneByName(string $stateName)
    {
        $stateName = pSQL($stateName);

        $tableName = _DB_PREFIX_ . self::TABLE_NAME;
        $sql = sprintf(
        "SELECT * FROM %s AS t WHERE t.name = '%s'",
            $tableName,
            $stateName
        );

        return Db::getInstance()->getRow($sql);
    }
}