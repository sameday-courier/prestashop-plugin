<?php
/**
 * 2007-2020 PrestaShop
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2020 PrestaShop SA
 * @license   http://addons.prestashop.com/en/content/12-terms-and-conditions-of-use
 * International Registered Trademark & Property of PrestaShop SA
 */

/**
 * class SamedayBgnCurrencyConvertor
 */
final class SamedayBgnCurrencyConvertor
{
    const EURO_CURRENCY = "EUR";
    const BGN_CURRENCY = "BGN";
    private $currency;
    private $amount;

    /**
     * @param string $currency
     * @param float $amount
     */
    public function __construct(string $currency, float $amount)
    {
        $this->currency = $currency;
        $this->amount = $amount;
    }

    /**
     * @return string
     *
     * @throws Exception
     */
    public function convert(): string
    {
        switch ($this->currency) {
            case self::EURO_CURRENCY:
                return $this->convertBGNtoEUR($this->amount);
            case self::BGN_CURRENCY:
                return $this->convertEURtoBGN($this->amount);
            default:
                throw new RuntimeException('Invalid currency');
        }
    }

    /**
     * @param string $estimatedPrice
     * @param string $estimatedCurrency
     *
     * @return string
     */
    public function buildCurrencyConversionLabel(
        string $price,
        string $storeCurrency,
        string $estimatedPrice,
        string $estimatedCurrency
    ): string
    {
        return sprintf(
            "Shipping cost: %s %s (≈ %s %s)",
            $price,
            $storeCurrency,
            $estimatedPrice,
            $estimatedCurrency
        );
    }

    /**
     * @param float $amount
     *
     * @return string
     */
    private function convertBGNtoEUR(float $amount): string
    {
        return number_format(($amount * 0.511292), 2, '.', '');
    }

    /**
     * @param float $amount
     *
     * @return string
     */
    private function convertEURtoBGN(float $amount): string
    {
        return number_format(($amount * 1.95583), 2, '.', '');
    }
}
