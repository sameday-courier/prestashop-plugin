<?php

class SamedayGeneralHelper
{
    /**
     * @return false|string
     */
    public function getHostCountry()
    {
        return Configuration::get('SAMEDAY_HOST_COUNTRY') ?? SamedayConstants::DEFAULT_HOST_COUNTRY;
    }

    /**
     * @param string $samedayServiceCode
     *
     * @return bool
     */
    public function isNotInUseService(string $samedayServiceCode): bool
    {
        return !in_array($samedayServiceCode, SamedayConstants::IN_USE_SERVICES, true);
    }

    /**
     * @param string $samedayServiceCode
     *
     * @return bool
     */
    public function isOohDeliveryOption(string $samedayServiceCode): bool
    {
        return in_array($samedayServiceCode, SamedayConstants::OOH_SERVICES, true);
    }

    /**
     * @param array $errors
     *
     * @return string
     */
    public function buildErrorMessage(array $errors): string
    {
        $allErrors = array();
        foreach ($errors as $error) {
            if (isset($error['errors'])) {
                foreach ($error['errors'] as $message) {
                    $allErrors[] = implode('.', $error['key']) . ': ' . $message;
                }
            } else {
                $allErrors[] = sprintf('%s : %s',
                    $error['code'] ?? 'Generic Error',
                    $error['message'] ?? 'Something went wrong'
                );
            }
        }

        return implode(' ', $allErrors);
    }
}
