<?php

use \DbCore as Db;

class SamedayCarrierCore extends CarrierCore
{
    public static function getSamedayCarrier(int $deleted = 0): array
    {
        $query = sprintf(
            "SELECT * FROM %s WHERE `deleted` = $deleted AND `external_module_name`= '%s'",
            _DB_PREFIX_ . self::$definition['table'],
            SamedayConstants::MODULE_NAME
        );

        try {
            return Db::getInstance()->executeS($query);
        } catch (Exception $exception) { return []; }
    }

    /**
     * @param int $carrierId
     *
     * @return false|CarrierCore
     */
    public static function findByCarrierId(int $carrierId)
    {
        $query = sprintf(
            "SELECT * FROM %s WHERE `id_carrier`='%s'",
            _DB_PREFIX_ . self::$definition['table'],
            $carrierId
        );

        try {
            $instance = Db::getInstance()->executeS($query)[0] ?? null;
        } catch (Exception $exception) { return false; }

        if (null !== $instance) {
            return new CarrierCore((int) $instance['id_carrier']);
        }

        return false;
    }

    /**
     * @param array $unusedCarriers
     *
     * @return void
     */
    public static function removeCarriers(array $unusedCarriers)
    {
        foreach ($unusedCarriers as $carrier) {
            /** @var false|CarrierCore $unusedCarrier */
            $unusedCarrier = self::findByCarrierId($carrier['id_carrier']);
            if (false !== $unusedCarrier) {
                $unusedCarrier->delete();
            }
        }
    }
}
