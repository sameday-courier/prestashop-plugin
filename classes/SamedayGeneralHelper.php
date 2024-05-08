<?php

class SamedayGeneralHelper
{
    public function getHostCountry()
    {
        return Configuration::get('SAMEDAY_HOST_COUNTRY') ?? SamedayConstants::DEFAULT_HOST_COUNTRY;
    }

    public function isNotInUseService(string $samedayServiceCode): bool
    {
        return in_array($samedayServiceCode, SamedayConstants::NOT_IN_USE_SERVICES, true);
    }

    public function isOohDeliveryOption(string $samedayServiceCode): bool
    {
        return in_array($samedayServiceCode, SamedayConstants::OOH_SERVICES, true);
    }
}
