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
        $idAddressDelivery = $this->storeNewAddressForLocker(
            json_decode($this->sameday_locker, false),
            new Address($this->id_address_delivery)
        );

        $deliveryOption = json_encode([$idAddressDelivery => sprintf('%s,', $this->id_carrier)]);

        $sql = sprintf("UPDATE %s SET `sameday_locker` = '%s', `id_address_delivery` = '%s', `delivery_option` = '%s' WHERE `id_cart` = '%s'",
            _DB_PREFIX_ . self::$definition['table'],
            $this->parseAndFilterLocker($this->sameday_locker),
            $idAddressDelivery,
            $deliveryOption,
            (int) $this->id
        );

        Db::getInstance()->execute($sql);
    }

    public function storeNewAddressForLocker($locker, $address)
    {
        if (
            false === $samedayAddress = SamedayAddress::findOneByCustomerAndAlias($address->id_customer)
        ) {
            /** @var Address $newAddress */
            $newAddress = $address->duplicateObject();
        } else {
            $newAddress = new Address($samedayAddress['id_address']);
        }

        /** @var SamedayState $state */
        $state = SamedayState::findOneByName($locker->county);

        $lockerName = (array) explode(' ', $locker->name);
        $alias = sprintf('easybox %s %s', $lockerName[1] ?? '', $lockerName[2] ?? '');

        $newAddress->alias = $alias;
        $newAddress->city = $locker->city;
        $newAddress->address1 = substr($locker->address, 0, 32);
        $newAddress->address2 = '';
        $newAddress->id_state = $state['id_state'];
        $newAddress->postcode = '';
        $newAddress->id_country = $state['id_country'];

        $newAddress->save();

        return $newAddress->id;
    }

    /**
     * @param string $locker
     *
     * @return string|null
     */
    private function parseAndFilterLocker(string $locker)
    {
        if ('' === $locker) {
            return '';
        }

        $locker = json_decode($locker, false);

        return json_encode([
            'lockerId' => (int) $locker->lockerId,
            'name' => strip_tags(stripslashes($locker->name)),
            'address' => strip_tags(stripslashes($locker->address)),
            'city' => strip_tags(stripslashes($locker->city)),
            'county' => strip_tags(stripslashes($locker->county)),
            'zip' => 0,
        ]);
    }
}
