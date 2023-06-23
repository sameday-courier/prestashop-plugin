<?php

class SamedayCart extends Cart
{
    /** @var string */
    public $sameday_locker;

    public function __construct($id = null, $idLang = null)
    {
        parent::__construct($id, $idLang);

        self::$definition['fields']['sameday_locker'] = [
            'type' => self::TYPE_STRING,
            'required' => false,
            'validate' => 'isCleanHtml'
        ];
    }

    public function save($null_values = false, $auto_date = true)
    {
        $tableName = _DB_PREFIX_ . self::$definition['table'];
        $idCart = (int) $this->id;

        $sql = sprintf("UPDATE %s SET `sameday_locker` = '%s' WHERE `id_cart` = '%s'",
            $tableName,
            $this->sameday_locker,
            $idCart
        );

        Db::getInstance()->execute($sql);
    }
}
