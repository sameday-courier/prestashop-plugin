<?php

use DbCore as Db;

class SamedayGeneralQueryHelper
{
    /**
     * @param string $tableName
     * @param string $newColumn
     *
     * @return void
     */
    public function addNewColumn(string $tableName, string $newColumn)
    {
        if (false === $this->isColumnExists($tableName, $newColumn)) {
            Db::getInstance()->execute(sprintf("ALTER TABLE %s ADD %s TEXT", $tableName, $newColumn));
        }
    }

    /**
     * @param string $tableName
     * @param string $columnName
     *
     * @return void
     */
    public function dropColumn(string $tableName, string $columnName)
    {
        if ($this->isColumnExists($tableName, $columnName)) {
            Db::getInstance()->execute(sprintf("ALTER TABLE %s DROP COLUMN %s", $tableName, $columnName));
        }
    }

    /**
     * @param string $tableName
     *
     * @return void
     */
    public function dropTable(string $tableName)
    {
        if ($this->isTableExists($tableName)) {
            Db::getInstance()->execute(sprintf("DROP TABLE %s", $tableName));
        }
    }

    /**
     * @param string $tableName
     *
     * @return bool
     */
    public function isTableExists(string $tableName): bool
    {
        return Db::getInstance()->execute(sprintf("SHOW TABLES LIKE '%s'", $tableName));
    }

    /**
     * @throws PrestaShopDatabaseExceptionCore
     */
    public function isColumnExists(string $tableName, string $columnName): bool
    {
        $columns = Db::getInstance()->executeS(sprintf("SHOW COLUMNS FROM %s", $tableName));

        $searchedColumn = array_filter(
            $columns,
            static function ($column) use ($columnName) {
                return $column['Field'] === $columnName;
            }
        );

        if (!empty($searchedColumn)) {
            return true;
        }

        return false;
    }
}