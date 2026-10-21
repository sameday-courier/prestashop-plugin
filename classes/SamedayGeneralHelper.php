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

    /**
     * @param string $samedayServiceCode
     *
     * @return bool
     */
    public function isPudoServiceType(string $samedayServiceCode): bool
    {
        return in_array($samedayServiceCode, SamedayConstants::PUDO_TYPE_SERVICE, true);
    }

    /**
     * @param string $orderServiceCode
     * @param string|int|null $oohType
     *
     * @return string
     */
    public function resolveAwbServiceCode(string $orderServiceCode, $oohType): string
    {
        $serviceKeys = array_flip(SamedayConstants::EASYBOX_TYPE_SERVICE)
            + array_flip(SamedayConstants::PUDO_TYPE_SERVICE);
        if (!isset($serviceKeys[$orderServiceCode])) {
            return $orderServiceCode;
        }

        $oohTypeCode = SamedayConstants::OOH_SERVICES[$oohType] ?? $oohType;
        $targetServices = in_array($oohTypeCode, SamedayConstants::PUDO_TYPE_SERVICE, true)
            ? SamedayConstants::PUDO_TYPE_SERVICE
            : SamedayConstants::EASYBOX_TYPE_SERVICE;

        return $targetServices[$serviceKeys[$orderServiceCode]] ?? $orderServiceCode;
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
