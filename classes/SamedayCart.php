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
        $sql = sprintf("UPDATE %s SET `sameday_locker` = '%s' WHERE `id_cart` = '%s'",
            _DB_PREFIX_ . self::$definition['table'],
            $this->sameday_locker,
            (int) $this->id
        );

        Db::getInstance()->execute($sql);
    }
}
