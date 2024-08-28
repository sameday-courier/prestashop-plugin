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
    const MODULE_NAME = 'samedaycourier';

    const LIVE_MODE = 1;
    const DEMO_MODE = 0;

    const API_HOST_LOCALE_RO = 'ro';
    const API_HOST_LOCALE_HU = 'hu';
    const API_HOST_LOCALE_BG = 'bg';

    const OPENPACKAGECODE = 'OPCG';
    const PERSONAL_DELIVERY_OPTION_CODE = 'PDO';

    const SAMEDAY_6H_CODE = '6H';
    const STANDARD_24H_CODE = '24';
    const LOCKER_NEXT_DAY_CODE = 'LN';
    const PUDO_CODE = 'PP';
    const OOH_SERVICE = 'OOH';
    const STANDARD_CROSSBORDER_CODE = 'XB';
    const LOCKER_NEXT_DAY_CROSSBORDER_CODE = 'XL';

    const ELIGIBLE_SERVICES = [
        self::SAMEDAY_6H_CODE,
        self::STANDARD_24H_CODE,
        self::LOCKER_NEXT_DAY_CODE
    ];

    const ELIGIBLE_FOR_CROSSBORDER = [
        self::STANDARD_CROSSBORDER_CODE,
        self::LOCKER_NEXT_DAY_CROSSBORDER_CODE,
    ];

    const ELIGIBLE_TO_6H_SERVICE = [
        'Bucuresti',
    ];

    const OOH_SERVICES = [
        self::LOCKER_NEXT_DAY_CODE,
        self::PUDO_CODE,
    ];

    const IN_USE_SERVICES = [
        self::SAMEDAY_6H_CODE,
        self::STANDARD_24H_CODE,
        self::LOCKER_NEXT_DAY_CODE,
        self::OOH_SERVICE,
        self::STANDARD_CROSSBORDER_CODE,
        self::LOCKER_NEXT_DAY_CROSSBORDER_CODE,
    ];

    const OOH_SERVICES_LABELS = [
        self::API_HOST_LOCALE_RO => 'Ridicare Sameday Point/Easybox',
        self::API_HOST_LOCALE_BG => 'вземете от Sameday Point/Easybox',
        self::API_HOST_LOCALE_HU => 'felvenni től Sameday Point/Easybox',
    ];

    const OOH_POPUP_TITLE = [
        self::API_HOST_LOCALE_RO => 'Optiunea Ridicare Personala include ambele servicii LockerNextDay, respectiv Pudo!',
        self::API_HOST_LOCALE_BG => 'Тази опция включва LockerNextDay и PUDO!',
        self::API_HOST_LOCALE_HU => 'Ez az opció magában foglalja a LockerNextDay és a PUDO szolgáltatást is!',
    ];

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

    const DEFAULT_HOST_COUNTRY = 'ro';

    const TOGGLE_HTML_ELEMENT = [
        'show' => 'block',
        'hide' => 'none',
    ];

    const DEBUG = 0;
    const WARNING = 2;
    const ERROR = 3;
    const AJAX = 'samedaycourier/ajax';
}
