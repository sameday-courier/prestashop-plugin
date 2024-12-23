<?php

class SamedayGeneralHelper
{
    /**
     * // return country code such as "ro, hu, bg"
     *
     * @return string
     */
    public function getHostCountry(): string
    {
        if (false === Configuration::get('SAMEDAY_HOST_COUNTRY')
            || null === Configuration::get('SAMEDAY_HOST_COUNTRY')
        ) {
            return SamedayConstants::DEFAULT_HOST_COUNTRY;
        }

        return  Configuration::get('SAMEDAY_HOST_COUNTRY');
    }

    public function isNotInUseService(string $samedayServiceCode): bool
    {
        return !in_array($samedayServiceCode, SamedayConstants::IN_USE_SERVICES, true);
    }

    public function isOohDeliveryOption(string $samedayServiceCode): bool
    {
        return in_array($samedayServiceCode, SamedayConstants::OOH_SERVICES, true);
    }

    public function sanitizeInput(string $input): string
    {
        return stripslashes(
            strip_tags(
                    str_replace("'", '&#39;', str_replace("javascript", '', $input)
                )
            )
        );
    }
}
