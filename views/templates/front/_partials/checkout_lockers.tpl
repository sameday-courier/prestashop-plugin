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
            if (_isSet(() => document.getElementById("locker_name"))) {
                if (_getCookie("samedaycourier_locker_name").length > 1) {
                    let lockerIdCookie = _getCookie("samedaycourier_locker_id");
                    let lockerNameCookie = _getCookie("samedaycourier_locker_name");
                    let lockerAddressCookie = _getCookie("samedaycourier_locker_address");
                    let lockerOohType = _getCookie("samedaycourier_locker_ooh_type");
                    document.getElementById("locker_name").value = lockerNameCookie;
                    document.getElementById("locker_address").value = lockerAddressCookie;
                    document.getElementById("locker_ooh_type").value = lockerOohType;

                    document.getElementById("showLockerDetails").style.display = "block";
                    document.getElementById("showLockerDetails").innerHTML = lockerNameCookie + '<br/>' + lockerAddressCookie;

                    _storeLocker(JSON.stringify({
                        'locker_id' : lockerIdCookie,
                        'locker_name': lockerNameCookie,
                        'locker_address': lockerAddressCookie,
                        'ooh_type': lockerOohType,
                    }));
                } else {
                    document.getElementById("showLockerDetails").style.display = "none";
                }
            }

            const cookie_locker_id = 'samedaycourier_locker_id';
            const cookie_locker_name = 'samedaycourier_locker_name';
            const cookie_locker_address = 'samedaycourier_locker_address';
            const cookie_locker_ooh_type = 'samedaycourier_locker_ooh_type';

            let showLockerMap = document.getElementById('showLockerMap');
            let showLockerSelector = document.getElementById('lockerIdSelector');

            if (_isSet(() => showLockerMap)) {
                const clientId="b8cb2ee3-41b9-4c3d-aafe-1527b453d65e";//each integrator will have a unique clientId
                const city = document.getElementById('showLockerMap').getAttribute('data-city');
                const county = document.getElementById('showLockerMap').getAttribute('data-county');
                const countryCode= document.getElementById('showLockerMap').getAttribute('data-country').toUpperCase(); //country for which the plugin is used
                const langCode= document.getElementById('showLockerMap').getAttribute('data-country');  //language of the plugin
                const samedayUser= document.getElementById('showLockerMap').getAttribute('data-username'); //sameday username

                window['LockerPlugin'].init(
                    {
                        clientId: clientId,
                        city: city,
                        county: county,
                        countryCode: countryCode,
                        langCode: langCode,
                        apiUsername: samedayUser
                    }
                );

                let lockerPlugin = window['LockerPlugin'].getInstance();

                document.addEventListener('click', (e) => {
                    if (e.target?.id === showLockerMap.id) {
                        lockerPlugin.open();
                    }
                });

                lockerPlugin.subscribe((locker) => {
                    let lockerId = locker.lockerId;
                    let lockerName = locker.name;
                    let lockerAddress = locker.address;
                    let oohType = locker.oohType;

                    _setCookie(cookie_locker_id, lockerId, 30);

                    document.getElementById("locker_name").value = lockerName;
                    _setCookie(cookie_locker_name, lockerName, 30);

                    document.getElementById("locker_address").value = lockerAddress;
                    _setCookie(cookie_locker_address, lockerAddress, 30);

                    document.getElementById("locker_ooh_type").value = oohType;
                    _setCookie(cookie_locker_ooh_type, oohType, 30);

                    document.getElementById("showLockerDetails").style.display = "block";
                    document.getElementById("showLockerDetails").innerHTML = lockerName + '<br/>' + lockerAddress;

                    _storeLocker(JSON.stringify({
                        'locker_id' : lockerId,
                        'locker_name': lockerName,
                        'locker_address': lockerAddress,
                        'ooh_type': oohType,
                    }));

                    lockerPlugin.close();
                });
            } else {
                showLockerSelector.onchange = (event) => {
                    let _target = event.target;
                    let option = _target.options[_target.selectedIndex];

                    _setCookie(cookie_locker_id, _target.value, 30);
                    _setCookie(cookie_locker_name, option.getAttribute('data-name'), 30);
                    _setCookie(cookie_locker_address, option.getAttribute('data-address'), 30);
                }
            }
        });

        const _storeLocker = (locker) => {
            let storeLockerRoute = document.getElementById('showLockerMap').getAttribute('data-store_locker_route');
            let idCart = document.getElementById('showLockerMap').getAttribute('data-id_cart');

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