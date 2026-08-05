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
        if (!$this->isTableExists($tableName)) {
            return;
        }

        Db::getInstance()->execute(sprintf('DROP TABLE IF EXISTS `%s`', pSQL($tableName)));
    }

    /**
     * @param string $tableName
     *
     * @return bool
     */
    public function isTableExists(string $tableName): bool
    {
        $result = Db::getInstance()->executeS(
            'SHOW TABLES LIKE \'' . pSQL($tableName) . '\''
        );

        return is_array($result) && count($result) > 0;
    }

    /**
     * @throws PrestaShopDatabaseExceptionCore
     */
    public function isColumnExists(string $tableName, string $columnName): bool
    {
        if (!$this->isTableExists($tableName)) {
            return false;
        }

        $columns = Db::getInstance()->executeS(sprintf('SHOW COLUMNS FROM `%s`', pSQL($tableName)));

        if (!is_array($columns)) {
            return false;
        }

        foreach ($columns as $column) {
            if (isset($column['Field']) && $column['Field'] === $columnName) {
                return true;
            }
        }

        return false;
    }
}