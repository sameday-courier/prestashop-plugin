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
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<style>
    .select2{
        width: 100% !important;
    }
</style>
<script src="https://cdn.sameday.ro/locker-plugin/lockerpluginsdk.js"></script>
{if $messages|count}
    {foreach from=$messages item=message}
        <div class="alert alert-{$message.type|escape:'html':'UTF-8'}">
            <ul>
                {if $message.content|count > 0}
                    {foreach from=$message.content item=error}
                        <li style="font-weight: bold; font-size: 12px; color: #643036">{$error|escape:'html':'UTF-8'}</li>
                    {/foreach}
                {/if}
            </ul>
        </div>
    {/foreach}
{/if}

<div class="well">
    <div class="row">
        {if $awb}
            {if $allowParcel}
                <div class="col-md-3">
                    <button class="btn btn-success" data-toggle="modal" data-target="#addParcel"><i class="icon-plus"></i>
                        {l s='Add Parcel' mod='samedaycourier'}
                    </button>
                </div>
                <div class="col-md-3">
                    <form action="" method="post" id="form-cancel-awb" class="form-horizontal">
                        <button type="submit" name="cancelAwb" class="btn btn-danger"><i
                                    class="icon-remove"></i> {l s='Cancel AWB' mod='samedaycourier'}</button>
                    </form>
                </div>
            {/if}
            <div class="col-md-3">
                <button name="history_awb" id="btn-history" class="btn btn-warning" data-awb="{$awb.id|escape:'html':'UTF-8'}">
                    <i class="icon-time"></i> {l s='AWB History' mod='samedaycourier'}</button>
            </div>

            <div class="col-md-3">
                <form action="" method="post" id="form-download-awb" class="form-horizontal">
                    <button type="submit" name="downloadAwb" class="btn btn-primary" id="downloadAwb"><i
                                class="icon-file"></i> {l s='Download AWB' mod='samedaycourier'}</button>
                </form>
            </div>
        {else}
            <div class="col-md-3">
                <button class="btn btn-success" data-toggle="modal" data-target="#addAwb"><i
                            class="icon-plus"></i> {l s='Add AWB' mod='samedaycourier'}</button>
            </div>
        {/if}
    </div>
</div>
{if $awb}
    <div id="addParcel" class="modal fade" role="dialog">
        <div class="modal-dialog">
            <!-- Modal content-->
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">X</button>
                </div>
                <form action="" method="post" id="form-add-parcels" class="form-horizontal">
                    <div class="modal-body">
                        <!-- Package Number //-->
                        <div class="form-group package_dimension_field">
                            <div class="parcel row">
                                <label class="col-sm-3 control-label" for="input-weight">
                                    {l s='Package dimension' mod='samedaycourier'}
                                </label>
                                <div class="col-sm-9">
                                    <div class="form-group">
                                        <table>
                                            <tr>
                                                <td>
                                                    <input type="number" name="sameday_package_weight[]"
                                                           value="{$packageWeight|escape:'html':'UTF-8'}"
                                                           min="0.1"
                                                           placeholder="Weight"
                                                           id="input-weight"
                                                           class="form-control input-number weight"
                                                           step="any"
                                                           required
                                                    >
                                                </td>
                                                <td>
                                                    <input type="number" name="sameday_package_width[]"
                                                           value=""
                                                           min="0"
                                                           placeholder="Width"
                                                           id="input-width"
                                                           class="form-control input-number"
                                                    >
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <input type="number" name="sameday_package_length[]"
                                                           value=""
                                                           min="0"
                                                           placeholder="Length"
                                                           id="input-length"
                                                           class="form-control input-number">
                                                </td>
                                                <td>
                                                    <input type="number" name="sameday_package_height[]"
                                                           value=""
                                                           min="0"
                                                           placeholder="Height"
                                                           id="input-height"
                                                           class="form-control input-number">
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Observation //-->
                        <div class="form-group">
                            <label class="col-sm-3 control-label" for="sameday_observation">
                                {l s='Observation' mod='samedaycourier'}
                            </label>
                            <div class="col-sm-9">
                                <input type="text" name="sameday_observation" id="sameday_observation" value="" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="addParcel" class="btn btn-success"><i
                                    class="icon-plus"></i> {l s='Add Parcel' mod='samedaycourier'}</button>
                        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    </div>
                </form>
            </div>

        </div>
    </div>

    <div class="modal fade" id="historyAwb" role="dialog">
        <div class="modal-dialog modal-lg">
            <!-- Modal content-->
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">{l s='AWB History' mod='samedaycourier'}</h4>
                </div>
                <div class="modal-body" id="awb-history">
                    <table class="table table-bordered">
                        <thead>
                        <tr>
                            <td> {l s='Summary' mod='samedaycourier'} </td>
                        </tr>
                        </thead>
                        <tbody>
                        <tr>
                            <td>
                                <table class="table table-bordered">
                                    <thead>
                                    <tr>
                                        <th> {l s='Parcel number' mod='samedaycourier'} </th>
                                        <th> {l s='Parcel weight' mod='samedaycourier'} </th>
                                        <th> {l s='Delivered' mod='samedaycourier'} </th>
                                        <th> {l s='Delivery attempts' mod='samedaycourier'} </th>
                                        <th> {l s='Is picked up' mod='samedaycourier'} </th>
                                        <th> {l s='Picked up at' mod='samedaycourier'} </th>
                                    </tr>
                                    </thead>
                                    <tbody id="awb-summary">
                                        <tr><td colspan="6">{l s='No records' mod='samedaycourier'}</td></tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <td> {l s='History' mod='samedaycourier'} </td>
                            </tr>
                        </thead>
                        <tbody>
                        <tr>
                            <td>
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>{l s='Parcel number' mod='samedaycourier'}</th>
                                            <th>{l s='Status' mod='samedaycourier'}</th>
                                            <th>{l s='Label' mod='samedaycourier'}</th>
                                            <th>{l s='State' mod='samedaycourier'}</th>
                                            <th>{l s='Date' mod='samedaycourier'}</th>
                                            <th>{l s='County' mod='samedaycourier'}</th>
                                            <th>{l s='Transit location' mod='samedaycourier'}</th>
                                            <th>{l s='Reason' mod='samedaycourier'}</th>
                                        </tr>
                                    </thead>
                                        <tbody id="awb-histories">
                                            <tr><td colspan="8">{l s='No records' mod='samedaycourier'}</td></tr>
                                        </tbody>
                                </table>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">{l s='Close' mod='samedaycourier'}</button>
                </div>
            </div>
        </div>
    </div>
{else}
    <div id="addAwb" class="modal fade" role="dialog">
        <div class="modal-dialog modal-lg">
            <!-- Modal content-->
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">×</button>
                </div>
                <form action="" method="post" id="form-shipping" class="form-horizontal">
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="col-sm-3 control-label" for="input-key">{l s='Packages' mod='samedaycourier'}</label>
                            <div class="col-sm-4">
                                <div class="input-group">
                                    <input type="text" name="sameday_package_number" class="form-control input-number"
                                           id="sameday_package_qty" value="1" readonly="">
                                    <span class="input-group-btn">
                                          <button type="button" class="btn btn-info btn-number" data-type="plus"
                                                  data-field="sameday_package_qty" id="plus-btn">
                                              <span> + </span>
                                          </button>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Package Number //-->
                        <div class="form-group package_dimension_field">
                            <div class="parcel">
                                <label class="col-sm-3 control-label" for="input-weight">
                                    {l s='Package dimension' mod='samedaycourier'}
                                </label>
                                <div class="col-sm-8">
                                    <div class="form-group">
                                        <table>
                                            <tr>
                                                <td>
                                                    <input type="number" name="sameday_package_weight[]"
                                                           value="{$packageWeight|escape:'html':'UTF-8'}"
                                                           min="0.1"
                                                           placeholder="Weight"
                                                           id="input-weight"
                                                           class="form-control input-number weight"
                                                           step="any"
                                                           required
                                                    >
                                                </td>
                                                <td>
                                                    <input type="number" name="sameday_package_width[]"
                                                           value=""
                                                           min="0"
                                                           placeholder="Width"
                                                           id="input-width"
                                                           class="form-control input-number"
                                                    >
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <input type="number" name="sameday_package_length[]"
                                                           value=""
                                                           min="0"
                                                           placeholder="Length"
                                                           id="input-length"
                                                           class="form-control input-number">
                                                </td>
                                                <td>
                                                    <input type="number" name="sameday_package_height[]"
                                                           value=""
                                                           min="0"
                                                           placeholder="Height"
                                                           id="input-height"
                                                           class="form-control input-number">
                                                </td>
                                                <td>
                                                    <span id="removePackageDimensionField">
                                                        <i class="btn btn-danger pull-left" style="vertical-align: bottom; cursor: pointer; padding-top: 9px;"> X </i>
                                                    </span>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Repayment //-->
                        <div class="form-group">
                            <label class="col-sm-3 control-label" for="sameday_repayment">
                                {l s='Repayment' mod='samedaycourier'}
                                <span style="font-weight: bold">({$orderCurrency|escape:'html':'UTF-8'})</span>
                            </label>
                            <div class="col-sm-9">
                                <input type="text" name="sameday_repayment" id="sameday_repayment" value="{$repayment|escape:'html':'UTF-8'}" class="form-control">
                            </div>
                        </div>

                        <!-- Currency Warning if not math values -->
                        {if !empty($xBorderWarning)}
                            <div class="form-group">
                                <div class="col-sm-12">
                                    <span style="color: #b22222; font-weight: bolder" class="form-group">{$xBorderWarning|escape:'html':'UTF-8'}</span>
                                </div>
                            </div>
                        {/if}

                        <!-- Insured Value //-->
                        <div class="form-group">
                            <label class="col-sm-3 control-label" for="sameday_insured_value">
                                {l s='Insured value' mod='samedaycourier'}
                            </label>
                            <div class="col-sm-9">
                                <input type="number" name="sameday_insured_value" id="sameday_insured_value" value="0" min="0" class="form-control">
                            </div>
                        </div>

                        <!-- Observation //-->
                        <div class="form-group">
                            <label class="col-sm-3 control-label" for="sameday_observation">
                                {l s='Observation' mod='samedaycourier'}
                            </label>
                            <div class="col-sm-9">
                                <input type="text" name="sameday_observation" id="sameday_observation" class="form-control">
                            </div>
                        </div>

                        <!-- Client Reference //-->
                        <div class="form-group">
                            <label class="col-sm-3 control-label" for="input-key-clientReference">
                                {l s='Client Reference' mod='samedaycourier'}
                            </label>
                            <div class="col-sm-9">
                                <input type="text" name="sameday_client_reference" value="{$orderId|escape:'html':'UTF-8'}" class="form-control" id="input-key-clientReference">
                                <span> {l s='Default value for this field is Order ID' mod='samedaycourier'} </span>
                            </div>
                        </div>

                        <!-- Package Type //-->
                        <div class="form-group">
                            <label class="col-sm-3 control-label" for="input-status-sameday_package_type">
                                {l s='Package type' mod='samedaycourier'}
                            </label>
                            <div class="col-sm-9">
                                <select name="sameday_package_type" id="input-status-sameday-package-type"
                                        class="form-control">
                                    {foreach from=$package_types key=value item=label}
                                        <option value="{$value|escape:'html':'UTF-8'}">{$label|escape:'html':'UTF-8'}</option>
                                    {/foreach}
                                </select>
                            </div>
                        </div>

                        <!-- Pickup Point //-->
                        <div class="form-group">
                            <label class="col-sm-3 control-label" for="input-status-sameday-pickup_point">
                                {l s='Pickup point' mod='samedaycourier'}
                            </label>
                            <div class="col-sm-9">
                                <select name="sameday_pickup_point" id="input-status-sameday-pickup_point"
                                        class="form-control">
                                    {foreach from=$pickup_points item=pickup_point}
                                        <option value="{$pickup_point.id_pickup_point|escape:'html':'UTF-8'}"
                                                {if $pickup_point.is_default}selected="selected"{/if}>{$pickup_point.sameday_alias|escape:'html':'UTF-8'}</option>
                                    {/foreach}
                                </select>
                            </div>
                        </div>

                        <!-- Services //-->
                        <div class="form-group">
                            <label class="col-sm-3 control-label"
                                   for="input-status-sameday-service">{l s='Service' mod='samedaycourier'}</label>
                            <div class="col-sm-9">
                                <select name="sameday_service" id="input-status-sameday-service"
                                        class="form-control">
                                    {foreach from=$services item=service}
                                            {if $service.status > 0}
                                                <option
                                                    value="{$service.id_service|escape:'html':'UTF-8'}"
                                                    data-locker_fist_mile="{$service.isPDOtoShow|escape:'html':'UTF-8'}"
                                                    data-locker_last_mile_code="{$service.isLastMileToShow|escape:'html':'UTF-8'}"
                                                    {if $service.id_service == $current_service} selected="selected" {/if}
                                                >
                                                    {$service.name|escape:'html':'UTF-8'}
                                                </option>
                                            {/if}
                                    {/foreach}
                                </select>
                            </div>
                        </div>

                        <div class="form-group" style="display: {$isLastMileToShow}" id="showLockerDetails">
                            <label class="col-sm-3 control-label" for="input-status-sameday-locker-details">
                                {l s='Locker Details' mod='samedaycourier'}
                            </label>
                            <div class="col-sm-9">
                                <input type="text" name="locker-details" id="sameday_locker_name" value="{$lockerDetails|escape:'html':'UTF-8'}" class="form-control" readonly>
                            </div>

                            <input type="hidden" id="locker_id" name="locker_id" value="{$idLocker|escape:'html':'UTF-8'}">
                            <input type="hidden" id="locker_name" name="locker_name" value="{$lockerName|escape:'html':'UTF-8'}">
                            <input type="hidden" id="locker_address" name="locker_address" value="{$lockerAddress|escape:'html':'UTF-8'}">
                            <input type="hidden" id="samedayOrderLockerId" name="samedayOrderLockerId" value="{$samedayOrderLockerId|escape:'html':'UTF-8'}">

                            <label class="col-sm-3 control-label"
                                   for="input-status-select_locker">
                            </label>

                            <div class="col-sm-9">
                                <button data-username="{$samedayUser}" data-country="{$countryCode}"
                                        class="btn btn-warning update-status ml-3 sameday_select_locker"
                                        type="button"
                                        id="select_locker"
                                        style="margin-left: 0px !important; margin-top: 10px;"
                                >
                                    {l s='Change location' mod='samedaycourier'}
                                </button>
                            </div>
                        </div>

                        <!-- Personal delivery at locker //-->
                        <div class="form-group" id="showLockerFirstMile" style="display: {$isPDOtoShow};">
                            <label class="col-sm-3 control-label"
                                   for="input-status-sameday-locker_first_mile">{l s='Personal delivery at locker' mod='samedaycourier'}
                            </label>
                            <div class="col-sm-1">
                                <input type="checkbox" name="sameday_locker_first_mile"  id="input-status-sameday-locker_first_mile" class="form-control">
                            </div>
                            <label class="col-sm-3 control-label"
                                   for="input-status-sameday-locker_first_mile_info_details">
                            </label>
                            <div class="col-sm-9" id="input-status-sameday-locker_first_mile_info_details">
                                <span style="display:block;width:100%"> {l s='Check this field if you want to apply for Personal delivery of the package at an easyBox terminal' mod='samedaycourier'}</span>
                                <span style="display:block;width:100%"><a href="https://sameday.ro/easybox#lockers-intro" target="_blank">{l s='Show map' mod='samedaycourier'}</a></span>
                                <div class="custom_tooltip"> {l s='Show locker dimensions' mod='samedaycourier'}
                                    <div class="tooltiptext">
                                        <table class="table"> <tbody style="color: #ffffff"> <tr> <th></th> <th style="text-align: center;">L</th> <th style="text-align: center;">l</th> <th style="text-align: center;">h</th> </tr><tr> <td>Small (cm)</td><td> 47</td><td> 44.5</td><td> 10</td></tr><tr> <td>Medium (cm)</td><td> 47</td><td> 44.5</td><td> 19</td></tr><tr> <td>Large (cm)</td><td> 47</td><td> 44.5</td><td> 39</td></tr> </tbody></table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Open Package -->
                        <div class="form-group">
                            <label class="col-sm-3 control-label"
                                   for="input-status-sameday-open-package">{l s='Open Package' mod='samedaycourier'}
                            </label>
                            <div class="col-sm-1">
                                <input type="checkbox" name="sameday_open_package" {if $isOpenPackage} checked="checked" {/if} id="input-status-sameday-open-package" class="form-control">
                            </div>
                        </div>

                        <!-- Awb Payment //-->
                        <div class="form-group hidden">
                            <label class="col-sm-3 control-label" for="input-status-sameday_awb_payment">
                                {l s='Awb Payment' mod='samedaycourier'}
                            </label>
                            <div class="col-sm-9">
                                <select name="sameday_awb_payment" id="input-status-sameday_awb_payment"
                                        class="form-control">
                                    <option value="1"> {l s='Client' mod='samedaycourier'}</option>
                                </select>
                            </div>
                        </div>
                        <input type="hidden" name="sameday_third_party_pickup" value="0"/>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="addAwb" class="btn btn-success"><i
                                    class="icon-plus"></i> {l s='Add AWB' mod='samedaycourier'}
                        </button>
                        <button type="button" class="btn btn-default" data-dismiss="modal">{l s='Close' mod='samedaycourier'}</button>
                    </div>
                </form>
            </div>

        </div>
    </div>
<script>
    $(document).ready(function() {
        $('#input-status-sameday-pickup_point').select2();
    });
</script>
<script type="text/javascript">
    $(document).ready(function () {
        $('#plus-btn').click(function (e) {
            e.preventDefault();
            $('#sameday_package_qty').val(parseInt($('#sameday_package_qty').val()) + 1);

            $('div.parcel').first().clone().appendTo('.package_dimension_field');
        });

        $('body').on('click', '#removePackageDimensionField', function () {
            if (parseInt($('#sameday_package_qty').val()) > 1) {
                $(this).parents('.parcel').remove();
                $('#sameday_package_qty').val(parseInt($('#sameday_package_qty').val()) - 1);
            }
        });

        $('form#form-shipping').submit(function () {
            if ($(this).attr('submitted')) {
                return false;
            }

            $(this).attr('submitted', true);
        });
    });
    </script>
{/if}
<script type="text/javascript">
    $('body').on('click', '#btn-history', function(e){
        e.preventDefault();
        console.log($(e).data('target'));
        if ($(e).data('target') !== undefined) {
            console.log('da');
        }
        var awbId = $(this).data('awb');
        $.ajax({
            url: '{$ajaxRoute|escape:'html':'UTF-8'}' + '&awb_id=' + awbId,
            type: 'GET',

            success: function(response){
                data = JSON.parse(response);
                if (data.summary !== undefined) {
                    var summaryHtml = '';
                    $.each(data.summary, function(awb, summary){
                        summaryHtml += '<tr><td>' + awb + '</td><td>' + summary.weight + '</td><td>' + summary.delivered + '</td>' +
                            '<td>' + summary.deliveredAttempts + '</td><td>' + summary.isPickedUp + '</td><td>' + summary.isPickedUpAt + '</td></tr>';
                    });

                    $('#awb-summary').html(summaryHtml);
                }

                if (data.histories !== undefined) {
                    var historiesHtml = '';
                    $.each(data.histories, function(awb, histories){
                        $.each(histories, function(i, history){
                            historiesHtml += '<tr><td>'+awb+'</td><td>'+history.name+'</td><td>'+history.label+'</td>' +
                                '<td>'+history.state+'</td><td>'+history.date.date.slice(0, 19)+'</td><td>'+history.county+'</td>' +
                                '<td>'+history.transit+'</td><td>'+history.reason+'</td></tr>';
                        });
                    });

                    $('#awb-histories').html(historiesHtml);
                }
                // Display Modal
                if (response.length) {
                    $('#historyAwb').modal('show');
                } else {
                    alert('Error occured while retrieving awb history');
                }
            },
            error: function (error) {
                alert(error);
            }
        });
    });

    document.getElementById('select_locker').addEventListener('click', () => {
        _openLockers();
    });

    document.getElementById('input-status-sameday-service').addEventListener('change', (e) => {
        const _target = e.target;
        const currentService = _target.options[_target.selectedIndex];

        const showLockerDetails = document.getElementById('showLockerDetails');
        const showLockerFirstMile = document.getElementById('showLockerFirstMile');

        // Unchecked element
        document.getElementById('input-status-sameday-locker_first_mile').checked = false;

        // Toggle Html Element
        showLockerDetails.style.display = currentService.getAttribute('data-locker_last_mile_code');
        showLockerFirstMile.style.display = currentService.getAttribute('data-locker_fist_mile');
    });

    const _openLockers = () => {
        /* DOM node selectors. */
        const clientId="b8cb2ee3-41b9-4c3d-aafe-1527b453d65e";//each integrator will have unique clientId
        const countryCode= document.querySelector('#select_locker').getAttribute('data-country').toUpperCase(); //country for which the plugin is used
        const langCode= document.querySelector('#select_locker').getAttribute('data-country').toLowerCase(); //language of the plugin
        const samedayUser = document.querySelector('#select_locker').getAttribute('data-username').toLowerCase(); //sameday username

        window['LockerPlugin'].init({ clientId: clientId, countryCode: countryCode, langCode: langCode, apiUsername: samedayUser });

        let pluginInstance = window['LockerPlugin'].getInstance();

        pluginInstance.open();

        pluginInstance.subscribe((message) => {
            pluginInstance.close();

            document.querySelector('#locker_id').value = message.lockerId;
            document.querySelector('#locker_name').value = message.name;
            document.querySelector('#locker_address').value = message.address;

            document.querySelector('#sameday_locker_name').value = message.name + " - " +message.address;
        });
    }

</script>

<style>
/* Locker Dimensions Tooltip */
.custom_tooltip {    
    position: relative;    
    display: inline-block;    
    cursor: help;}
.custom_tooltip .tooltiptext {    
    visibility: hidden;    
    width: auto;    
    background-color: #363A41;
    color: #ffffff;    
    text-align: center;    
    border-radius: 5px;    padding: 10px;
    /* Position the tooltip */    
    position: absolute;    
    z-index: 10000;
}
.custom_tooltip:hover .tooltiptext {    
    color: #ffffff;    
    visibility: visible;
}
</style>