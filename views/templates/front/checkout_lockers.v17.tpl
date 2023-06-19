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
<div class="col-sm-2">
    {l s='Select locker' mod='samedaycourier'}
</div>
<div class="col-sm-8">
    <button type="button" style="display:inline-block"name="samedaycourier_locker_id" id="showLockerMap" data-username='{$samedayUser}' data-country='{$hostCountry}' class="button-exclusive btn btn-default">
        {l s='Show locker map' mod='samedaycourier'}
    </button>

    <div style="display:inline-block;    vertical-align: middle;">
        <input type="hidden" id="locker_name" name="locker_name" value="" data-locker_carrier_id="{$carrier_id}">
        <input type="text" id="locker_address" name="locker_address" style="width:0px;height:0px;opacity:0;" oninvalid="this.setCustomValidity('Please select locker')" value="" >
        <span style="padding-bottom: 10px;font-size: 13px; font-weight: bold; line-height: 22px;width:100%;display:block" id="showLockerDetails"></span>
    </div>
</div>
<script>
setTimeout(function() {
$(document).ready(function(){
  $(".js-delivery-option").click(function(){
    let contentCarrier = $(this).next('.js-carrier-extra-content').html();
    if(contentCarrier.indexOf('samedaycourier_locker_id') != -1){
        $("#locker_address").prop('required',true);
    }else{
        $("#locker_address").prop('required',false);
    }
    
  });
});
}, 1000);
</script>