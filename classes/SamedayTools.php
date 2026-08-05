<?php
/**
 * Shared helpers for PrestaShop 8.x / 9.x compatibility.
 */

class SamedayTools
{
    /**
     * @return bool
     */
    public static function isPrestaShop9()
    {
        return version_compare(_PS_VERSION_, '9.0.0', '>=');
    }

    /**
     * Token digest used in cron/ajax URLs.
     * Tools::encrypt was removed in PrestaShop 9; Tools::hash remains.
     *
     * @param string $value
     *
     * @return string
     */
    public static function tokenHash($value)
    {
        if (self::isPrestaShop9()) {
            return Tools::hash((string) $value);
        }

        return Tools::encrypt((string) $value);
    }

    /**
     * First 10 chars of the cron token digest (historical URL format).
     *
     * @return string
     */
    public static function getCronUrlToken()
    {
        return Tools::substr(self::tokenHash(Configuration::get('SAMEDAY_CRON_TOKEN')), 0, 10);
    }

    /**
     * @param string $token
     *
     * @return bool
     */
    public static function isValidCronUrlToken($token)
    {
        return self::getCronUrlToken() === (string) $token;
    }

    /**
     * Append query parameters using ? or & as needed.
     *
     * @param string $url
     * @param array $params
     *
     * @return string
     */
    public static function appendQueryParams($url, array $params)
    {
        $parts = [];
        foreach ($params as $key => $value) {
            if ($value === null) {
                continue;
            }
            $parts[] = rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
        }

        if ($parts === []) {
            return $url;
        }

        $separator = strpos($url, '?') === false ? '?' : '&';

        return $url . $separator . implode('&', $parts);
    }

    /**
     * Public module front-controller URL (works when modules/*.php is blocked on PS9).
     *
     * @param string $controller
     * @param array $params
     *
     * @return string
     */
    public static function getModuleControllerUrl($controller, array $params = [])
    {
        $link = Context::getContext()->link;
        if ($link) {
            return $link->getModuleLink(
                SamedayConstants::MODULE_NAME,
                $controller,
                array_filter(
                    $params,
                    static function ($value) {
                        return $value !== null && $value !== '';
                    }
                ),
                true
            );
        }

        $base = Tools::getShopDomainSsl(true) . __PS_BASE_URI__;

        return self::appendQueryParams(
            $base . 'index.php',
            array_merge(
                [
                    'fc' => 'module',
                    'module' => SamedayConstants::MODULE_NAME,
                    'controller' => $controller,
                ],
                $params
            )
        );
    }

    /**
     * Legacy direct PHP endpoint (PS &lt; 9 only; blocked by modules/.htaccess on PS9).
     *
     * @param string $script ajax.php|sync.php
     * @param array $params
     *
     * @return string
     */
    public static function getLegacyModuleScriptUrl($script, array $params = [])
    {
        $url = Tools::getShopDomainSsl(true) . _MODULE_DIR_ . SamedayConstants::MODULE_NAME . '/' . ltrim($script, '/');

        return self::appendQueryParams($url, $params);
    }

    /**
     * Front/back module AJAX token (must NOT use Tools::getAdminToken on PS9 —
     * that returns a Symfony employee CSRF token and breaks FO checkout).
     *
     * @return string
     */
    public static function getModuleAjaxToken()
    {
        return Tools::substr(
            self::tokenHash((string) Configuration::get('SAMEDAY_CRON_TOKEN') . '|sameday_module_ajax'),
            0,
            32
        );
    }

    /**
     * @param string $token
     *
     * @return bool
     */
    public static function isValidModuleAjaxToken($token)
    {
        $expected = self::getModuleAjaxToken();

        return $expected !== ''
            && is_string($token)
            && $token !== ''
            && hash_equals($expected, $token);
    }

    /**
     * Use front controllers only on PS9 where modules/*.php is blocked.
     * Keep legacy scripts on PS8 and older so existing BO/FO flows remain unchanged.
     *
     * @param string $controller
     * @param string $legacyScript
     * @param array $params
     *
     * @return string
     */
    public static function getEndpointUrl($controller, $legacyScript, array $params = [])
    {
        if (self::isPrestaShop9()) {
            return self::getModuleControllerUrl($controller, $params);
        }

        return self::getLegacyModuleScriptUrl($legacyScript, $params);
    }

    /**
     * Absolute admin img URL for HelperList icons on PS9.
     *
     * @return string
     */
    public static function getAdminImgBaseUrl()
    {
        return Tools::getShopDomainSsl(true) . __PS_BASE_URI__ . 'img/admin/';
    }
}
