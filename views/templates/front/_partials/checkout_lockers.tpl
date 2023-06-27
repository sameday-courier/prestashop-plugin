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

        const _isSet = (accessor) => {
            try {
                return accessor() !== undefined && accessor() !== null
            } catch (e) {
                return false
            }
        }

        docReady(function () {
            if (_isSet( () => document.getElementById("locker_name"))) {
                if('' !== _getCookie("sameday_locker")) {
                    let locker = JSON.parse(_getCookie("sameday_locker"));

                    document.getElementById("locker_name").value = locker.name;
                    document.getElementById("locker_address").value = locker.address;

                    document.getElementById("showLockerDetails").style.display = "block";
                    document.getElementById("showLockerDetails").innerHTML = locker.name + '<br/>' + locker.address;
                } else {
                    document.getElementById("showLockerDetails").style.display = "none";
                }
            }

            const sameday_id_locker = 'sameday_id_locker';
            const sameday_locker = 'sameday_locker';

            let showLockerMap = document.getElementById('showLockerMap');
            let showLockerSelector = document.getElementById('lockerIdSelector');

            if (_isSet(() => showLockerMap)) {
                const clientId="b8cb2ee3-41b9-4c3d-aafe-1527b453d65e"; // each integrator will have a unique clientId
                const countryCode= document.getElementById('showLockerMap').getAttribute('data-country').toUpperCase(); //country for which the plugin is used
                const langCode= document.getElementById('showLockerMap').getAttribute('data-country');  //language of the plugin
                const samedayUser= document.getElementById('showLockerMap').getAttribute('data-username'); //sameday username
                const city = document.getElementById('locker_name').getAttribute('data-city');

                window['LockerPlugin'].init(
                    {
                        clientId: clientId,
                        countryCode: countryCode,
                        langCode: langCode,
                        apiUsername: samedayUser,
                        city: city,
                    }
                );

                let lockerPlugin = window['LockerPlugin'].getInstance();

                showLockerMap.addEventListener('click', () => {
                    lockerPlugin.open();
                }, false);

                lockerPlugin.subscribe((locker) => {
                    let lockerName = locker.name;
                    let lockerAddress = locker.address;

                    _setCookie(sameday_locker, JSON.stringify(locker), 30);

                    document.getElementById("locker_name").value = lockerName;
                    document.getElementById("locker_address").value = lockerAddress;

                    document.getElementById("showLockerDetails").style.display = "block";
                    document.getElementById("showLockerDetails").innerHTML = lockerName + '<br/>' + lockerAddress;

                    _storeLocker(JSON.stringify(locker));

                    lockerPlugin.close();
                });

            } else {
                // For Local data usage -- drop-down list
                showLockerSelector.onchange = (event) => {
                    let _target = event.target;
                    let option = _target.options[_target.selectedIndex];

                    _setCookie(sameday_id_locker, _target.value, 30);
                }
            }
        });

        const _storeLocker = (locker) => {
            let storeLockerRoute = document.getElementById('locker_name').getAttribute('data-store_locker_route');
            let idCart = document.getElementById('locker_name').getAttribute('data-id_cart');

            $.ajax({
                url: storeLockerRoute,
                method: 'POST',
                data: {
                    action: 'store_locker',
                    locker: locker,
                    idCart: idCart,
                },

            });
        }

        const _setCookie = (key, value, days) => {
            let d = new Date();
            d.setTime(d.getTime() + (days*24*60*60*1000));
            let expires = "expires=" + d.toUTCString();

            document.cookie = key + "=" + value + ";" + expires + ";path=/";
        }

        const _getCookie = (key) => {
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