<?php

use \DbCore as Db;

class SamedayGeneralQueryHelper
{
    public function alterColumn(string $tableName, string $newColumn): void
    {
        try {
            $columns = Db::getInstance()->executeS(sprintf("SHOW COLUMNS FROM %s", $tableName));
        } catch (Exception $exception) {return;}

        $searchedColumn = array_filter(
            $columns,
            static function ($column) use ($newColumn) {
                return $column['Field'] === $newColumn;
            }
        );

        if (empty($searchedColumn)) {
            Db::getInstance()->execute(sprintf("ALTER TABLE %s ADD %s TEXT", $tableName, $newColumn));
        }
    }
}