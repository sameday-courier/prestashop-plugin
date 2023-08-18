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

class SamedayPsOrder extends OrderCore
{
    public function save($null_values = false, $auto_date = true)
    {
        $sql = sprintf("UPDATE %s SET `id_address_delivery` = '%s' WHERE `id_order` = '%s'",
            _DB_PREFIX_ . self::$definition['table'],
            $this->id_address_delivery,
            $this->id
        );

        return Db::getInstance()->execute($sql);
    }
}
