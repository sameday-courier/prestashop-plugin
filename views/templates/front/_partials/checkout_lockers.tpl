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
        function docReady(fn) {
            // see if DOM is already available
            if (document.readyState === "complete" || document.readyState === "interactive") {
                // call on next available tick
                setTimeout(fn, 1);
            } else {
                document.addEventListener('DOMContentLoaded', fn);
            }
        }

        docReady(function () {
            let name = 'samedaycourier_locker_id';

            const setCookie = (lockerId) => {
                document.cookie = name + '=' + lockerId;
            }

            const getLockerId = () => {
                let cookies = document.cookie.split(';');
                let locker_id = '';
                cookies.forEach(function (value) {
                    if (value.indexOf('locker_id') > 0) {
                        locker_id = value.split('=')[1];
                    }
                });

                return locker_id;
            }

            let lockerIdSelector = document.getElementById('lockerIdSelector');
            lockerIdSelector.value = getLockerId();

            lockerIdSelector.addEventListener('change', function () {
                if ('' !== lockerIdSelector.value) {
                    setCookie(lockerIdSelector.value);
                }
            }, false);
        });
    {/literal}
</script>