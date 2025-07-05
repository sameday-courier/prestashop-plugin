<?php

use Sameday\Exceptions\SamedayBadRequestException;
use Sameday\Objects\CountyObject;
use Sameday\Requests\SamedayGetCountiesRequest;

class SamedayApiHelper
{
    /**
     * @param $user
     * @param $password
     * @param $urlEnv
     * @param $testingMode
     *
     * @return Sameday\SamedayClient
     *
     * @throws Sameday\Exceptions\SamedaySDKException
     */
    public function getSamedayClient(
        $user = null,
        $password = null,
        $urlEnv = null,
        $testingMode = null
    ): Sameday\SamedayClient
    {
        if ($user === null) {
            $user = Configuration::get('SAMEDAY_ACCOUNT_USER');
        }

        if ($password === null) {
            $password = Configuration::get('SAMEDAY_ACCOUNT_PASSWORD');
        }

        if ($testingMode === null) {
            $testingMode = (Configuration::get('SAMEDAY_LIVE_MODE')) ?: SamedayConstants::DEMO_MODE;
        }

        $country = (Configuration::get('SAMEDAY_HOST_COUNTRY')) ?: SamedayConstants::API_HOST_LOCALE_RO;

        if ($urlEnv === null) {
            $urlEnv = SamedayConstants::SAMEDAY_ENVS[$country][$testingMode];
        }

        return new Sameday\SamedayClient(
            $user,
            $password,
            $urlEnv,
            'Prestashop',
            _PS_VERSION_,
            'curl',
            new SamedayPersistenceDataHandler()
        );
    }

    /**
     * @return array
     */
    public function getSamedayCounties(): array
    {
        $defaultCountyChoices = [
            'value' => "",
            'label' => "Select county",
        ];
        try {
            $samedayClient = new Sameday\Sameday($this->getSamedayClient());
        } catch (Exception $exception) {
            return [$defaultCountyChoices];
        }

        try {
            $counties = $samedayClient->getCounties(new SamedayGetCountiesRequest(null));
        } catch (SamedayBadRequestException $exception) {
            return [$defaultCountyChoices];
        } catch (Exception $exception) {
            return [$defaultCountyChoices];
        }

        return array_merge(
            [$defaultCountyChoices],
            array_map(
                static function (CountyObject $county) {
                    return [
                        'value' => $county->getId(),
                        'label' => $county->getName(),
                    ];
                },
                $counties->getCounties()
            )
        );
    }

    /**
     * @param $countyId
     *
     * @return array
     */
    public function getSamedayCities($countyId = null): array
    {
        $defaultChoice = [
            'value' => "",
            'label' => "Select City",
        ];

        try {
            $samedayClient = new Sameday\Sameday($this->getSamedayClient());
        } catch (Exception $exception) {
            return [$defaultChoice];
        }

        if (null === $countyId) {
            return [$defaultChoice];
        }

        $page = 1;
        $request = new \Sameday\Requests\SamedayGetCitiesRequest($countyId);
        do {
            $request->setPage($page++);
            $request->setCountPerPage(1000);
            try {
                $cities = $samedayClient->getCities($request);
            } catch (\Sameday\Exceptions\SamedayBadRequestException $exception) {
                return [];
            } catch (Exception $exception) {
                return [];
            }

            $cityChoices = array_map(
                static function (\Sameday\Objects\CityObject $city) {
                    return [
                        'value' => $city->getId(),
                        'label' => $city->getName(),
                    ];
                },
                $cities->getCities()
            );

        } while ($page <= $cities->getPages());

        return array_merge(
            [$defaultChoice],
            $cityChoices
        );
    }
}
