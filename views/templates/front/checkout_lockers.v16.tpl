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
<script src="https://cdn.sameday.ro/locker-plugin/lockerpluginsdk.js"></script>
{include file='./_partials/checkout_lockers.tpl'}
<table class="resume table table-bordered">
    <tbody>
        <tr>
            <td>{l s='Select locker' mod='samedaycourier'}</td>
            <td>
                <button type="button"
                    name="samedaycourier_locker_id"
                    id="showLockerMap"
                    data-username='{$samedayUser}'
                    data-country='{$countryCode}'
                    data-id_cart='{$idCart}'
                    data-city='{$city}'
                    data-county='{$county}'
                    data-store_locker_route='{$storeLockerRoute}'
                    class="button-exclusive btn btn-default"
                >
                    {l s='Show locker map' mod='samedaycourier'}
                </button>
                <input type="hidden" id="locker_name" name="locker_name">
                <input type="text" id="locker_address" name="locker_address" style="width:0px;height:0px;opacity:0;">
            </td>
            <td>
                <span style="padding-bottom: 10px;font-size: 13px; font-weight: bold; line-height: 22px;width:100%;display:block" id="showLockerDetails"></span>
            </td>
        </tr>
    </tbody>
</table>