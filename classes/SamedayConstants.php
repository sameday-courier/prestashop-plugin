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
    const SAMEDAY_ENVS = [
        'ro' => [
            'API_URL_PROD' => 'https://api.sameday.ro',
            'API_URL_DEMO' => 'https://sameday-api.demo.zitec.com',
        ],
    ];

    const API_URL_PROD = 'https://api.sameday.ro';
    const API_URL_DEMO = 'https://sameday-api.demo.zitec.com';
    const DEBUG = 0;
    const INFO = 1;
    const WARNING = 2;
    const ERROR = 3;
    const SYNC = 'samedaycourier/sync';
    const AJAX = 'samedaycourier/ajax';
}
