<?php

class SamedayCart extends Cart
{
    const TABLE_NAME = 'cart';

    /** @var string */
    public $sameday_locker;

    public static $definition;

    public function __construct($id = null, $idLang = null)
    {
        parent::__construct($id, $idLang);

        $parentDefinition = parent::$definition;

        $parentDefinition['fields']['sameday_locker'] = [
            'type' => self::TYPE_STRING,
            'required' => false,
            'validate' => 'isCleanHtml'
        ];

        self::$definition = $parentDefinition;
    }
}
