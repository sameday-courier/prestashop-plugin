{**
 * 2007-2020 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2020 PrestaShop SA
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 *}
<script>
    {literal}
        document.addEventListener("DOMContentLoaded", function () {
            let name = 'samedaycourier_open_package';

            const setCookie = (openPackage) => {
                document.cookie = name + '=' + openPackage;
            }

            const expireCookie = () => {
                document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:01 GMT;';
            }

            let openPackageIdSelector = document.getElementById('samedaycourier_open_package');

            const getOpenPackage = () => {
                let cookies = document.cookie.split(';');
                let samedaycourier_open_package = '';

                cookies.forEach(function (value) {
                    if (value.indexOf('open_package') > 0) {
                        samedaycourier_open_package = value.split('=')[1];
                    }
                });

                return samedaycourier_open_package;
            }

            openPackageIdSelector.checked = '' !== getOpenPackage();

            openPackageIdSelector.addEventListener('click', function () {
                if (openPackageIdSelector.checked === true) {
                    setCookie(openPackageIdSelector.value);
                } else {
                    expireCookie();
                }
            }, false);
        });
    {/literal}
</script>