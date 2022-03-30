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
            
            const clientId="b8cb2ee3-41b9-4c3d-aafe-1527b453d65e";//each integrator will have an unique clientId
            const countryCode= document.getElementById('showLockerMap').getAttribute('data-country') //country for which the plugin is used
            const langCode= document.getElementById('showLockerMap').getAttribute('data-country').toLowerCase();  //language of the plugin
            window.LockerPlugin.init({ clientId: clientId, countryCode: countryCode, langCode: langCode });
            
            lockerPLugin = window.LockerPlugin.getInstance();

            let name = 'samedaycourier_locker_id';
            let showLockerMap = document.getElementById('showLockerMap');
            let showLockerSelector = document.getElementById('lockerIdSelector');

            const setCookie = (lockerId) => {
                document.cookie = name + '=' + lockerId;
            }

            if (typeof(showLockerMap) != 'undefined' && showLockerMap != null){
                showLockerMap.addEventListener('click', function () {
                    lockerPLugin.open();
                }, false);
            }else{
                showLockerSelector.onchange = (event) => {
                    let lockerId = event.target.value;
                    setCookie(lockerId);
                }
            }

            lockerPLugin.subscribe((locker) => {
                setCookie(locker.lockerId);
                lockerPLugin.close();
            });
        });
    {/literal}
</script>