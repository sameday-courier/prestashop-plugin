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
                setTimeout(fn, 1000);
            } else {
                document.addEventListener('DOMContentLoaded', fn);
            }
        }

        const is_set = (accessor) => {
            try {
                return accessor() !== undefined && accessor() !== null
            } catch (e) {
                return false
            }
        }

        docReady(function () {
            if (is_set( () => document.getElementById("locker_name"))) {
                if(get_cookie("samedaycourier_locker_name").length > 1){
                    let lockerNamesCookie = get_cookie("samedaycourier_locker_name");
                    let lockerAddressCookie = get_cookie("samedaycourier_locker_address");
                    document.getElementById("locker_name").value = lockerNamesCookie;
                    document.getElementById("locker_address").value = lockerAddressCookie;

                    document.getElementById("showLockerDetails").style.display = "block";
                    document.getElementById("showLockerDetails").innerHTML = lockerNamesCookie + '<br/>' + lockerAddressCookie;

                }else{
                    document.getElementById("showLockerDetails").style.display = "none";
                }
            }

            if (is_set( () => document.getElementById("showLockerMap"))) {
                const clientId="b8cb2ee3-41b9-4c3d-aafe-1527b453d65e";//each integrator will have an unique clientId
                const countryCode= document.getElementById('showLockerMap').getAttribute('data-country').toUpperCase(); //country for which the plugin is used
                const langCode= document.getElementById('showLockerMap').getAttribute('data-country')  //language of the plugin
                window.LockerPlugin.init({ clientId: clientId, countryCode: countryCode, langCode: langCode });

                lockerPLugin = window.LockerPlugin.getInstance();
            }
            let name = 'samedaycourier_locker_id';
            let showLockerMap = document.getElementById('showLockerMap');
            let showLockerSelector = document.getElementById('lockerIdSelector');

            if (is_set( () => showLockerMap)) {
                showLockerMap.addEventListener('click', () => {
                    lockerPLugin.open();
                }, false);
            } else {
                showLockerSelector.onchange = (event) => {
                    let lockerId = event.target.value;
                    set_cookie(name, lockerId, 30);
                }
            }

            if (is_set( () => document.getElementById("showLockerMap"))) {
                lockerPLugin.subscribe((locker) => {
                    let lockerName = locker.name;
                    let lockerAddress = locker.address;

                    set_cookie("samedaycourier_locker_id", locker.lockerId, 30);

                    document.getElementById("locker_name").value = lockerName;
                    set_cookie("samedaycourier_locker_name", lockerName, 30);

                    document.getElementById("locker_address").value = lockerAddress;
                    set_cookie("samedaycourier_locker_address", lockerAddress, 30);

                    document.getElementById("showLockerDetails").style.display = "block";
                    document.getElementById("showLockerDetails").innerHTML = lockerName + '<br/>' + lockerAddress;

                    lockerPLugin.close();
                });
            }
        });

        const set_cookie = (key, value, days) => {
            let d = new Date();
            d.setTime(d.getTime() + (days*24*60*60*1000));
            let expires = "expires=" + d.toUTCString();

            document.cookie = key + "=" + value + ";" + expires + ";path=/";
        }

        const get_cookie = (key) => {
            let cookie = '';
            document.cookie.split(';').forEach(function (value) {
                if (value.split('=')[0].trim() === key) {
                    return cookie = value.split('=')[1];
                }
            });

            return cookie;
        }
    {/literal}
</script>