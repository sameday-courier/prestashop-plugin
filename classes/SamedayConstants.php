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

final class SamedayConstants
{
    const LIVE_MODE = 1;
    const DEMO_MODE = 0;

    const API_HOST_LOCALE_RO = 'ro';
    const API_HOST_LOCALE_HU = 'hu';
    const API_HOST_LOCALE_BG = 'bg';

    const SAMEDAY_ENVS = [
        self::API_HOST_LOCALE_RO => [
            self::LIVE_MODE => 'https://api.sameday.ro',
            self::DEMO_MODE => 'https://sameday-api.demo.zitec.com',
        ],
        self::API_HOST_LOCALE_HU => [
            self::LIVE_MODE => 'https://api.sameday.hu',
            self::DEMO_MODE => 'https://sameday-api-hu.demo.zitec.com',
        ],
        self::API_HOST_LOCALE_BG => [
            self::LIVE_MODE => 'https://api.sameday.bg',
            self::DEMO_MODE => 'https://sameday-api-bg.demo.zitec.com',
        ],
    ];

    const DEBUG = 0;
    const WARNING = 2;
    const ERROR = 3;
    const AJAX = 'samedaycourier/ajax';
}
