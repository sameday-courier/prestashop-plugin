{**
 * 2007-2019 PrestaShop
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
 * @copyright 2007-2019 PrestaShop SA
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 *}

<div class="well">
    <div class="row">
        {if $awb}
            {if $allowParcel}
                <div class="col-md-3">
                    <button class="btn btn-success" data-toggle="modal" data-target="#addParcel"><i
                                class="icon-plus"></i> {l s='Add Parcel' mod='sameday'}</button>
                </div>
                <div class="col-md-3">
                    <form action="" method="post" id="form-cancel-awb" class="form-horizontal">
                        <button type="submit" name="cancelAwb" class="btn btn-danger"><i
                                    class="icon-remove"></i> {l s='Cancel AWB' mod='sameday'}</button>
                    </form>
                </div>
            {/if}
            <div class="col-md-3">
                <button name="history_awb" id="btn-history" class="btn btn-warning" data-awb="{$awb.id|escape:'html':'UTF-8'}">
                    <i class="icon-time"></i> {l s='AWB History' mod='sameday'}</button>
            </div>

            <div class="col-md-3">
                <form action="" method="post" id="form-download-awb" class="form-horizontal">
                    <button type="submit" name="downloadAwb" class="btn btn-primary" id="downloadAwb"><i
                                class="icon-file"></i> {l s='Download AWB' mod='sameday'}</button>
                </form>
            </div>
        {else}
            <div class="col-md-3">
                <button class="btn btn-success" data-toggle="modal" data-target="#addAwb"><i
                            class="icon-plus"></i> {l s='Add AWB' mod='sameday'}</button>
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
                    <button type="button" class="close" data-dismiss="modal">×</button>
                    <h4 class="modal-title">{l s='Add Parcel' mod='sameday'}</h4>
                </div>
                <form action="" method="post" id="form-add-parcels" class="form-horizontal">
                    <div class="modal-body">
                        <!-- Package Number //-->
                        <div class="form-group package_dimension_field">
                            <div class="parcel row">
                                <label class="col-sm-3 control-label"
                                       for="input-length">{l s='Package dimension' mod='sameday'}</label>
                                <div class="col-sm-8" style="padding-bottom: 5px;">
                                    <div class="row">
                                        <div class="col-sm-3">
                                            <input type="number" name="sameday_package_weight" value="" min="1" step="0.1"
                                                   placeholder="Weight" id="input-weight"
                                                   class="form-control input-number" required>
                                        </div>
                                        <div class="col-sm-3">
                                            <input type="number" name="sameday_package_width" value="" min="0"
                                                   placeholder="Width" id="input-width"
                                                   class="form-control input-number">
                                        </div>
                                        <div class="col-sm-3">
                                            <input type="number" name="sameday_package_length" value="" min="0"
                                                   placeholder="Length" id="input-length"
                                                   class="form-control input-number">
                                        </div>
                                        <div class="col-sm-3">
                                            <input type="number" name="sameday_package_height" value="" min="0"
                                                   placeholder="Height" id="input-height"
                                                   class="form-control input-number">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Observation //-->
                        <div class="form-group">
                            <label class="col-sm-3 control-label"
                                   for="input-key">{l s='Observation' mod='sameday'}</label>
                            <div class="col-sm-8">
                                <input type="text" name="sameday_observation" value="" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="addParcel" class="btn btn-success"><i
                                    class="icon-plus"></i> {l s='Add Parcel' mod='sameday'}</button>
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
                    <h4 class="modal-title">{l s='AWB History' mod='sameday'}</h4>
                </div>
                <div class="modal-body" id="awb-history">
                    <table class="table table-bordered">
                        <thead>
                        <tr>
                            <td> {l s='Summary' mod='sameday'} </td>
                        </tr>
                        </thead>
                        <tbody>
                        <tr>
                            <td>
                                <table class="table table-bordered">
                                    <thead>
                                    <tr>
                                        <th> {l s='Parcel number' mod='sameday'} </th>
                                        <th> {l s='Parcel weight' mod='sameday'} </th>
                                        <th> {l s='Delivered' mod='sameday'} </th>
                                        <th> {l s='Delivery attempts' mod='sameday'} </th>
                                        <th> {l s='Is picked up' mod='sameday'} </th>
                                        <th> {l s='Picked up at' mod='sameday'} </th>
                                    </tr>
                                    </thead>
                                    <tbody id="awb-summary">
                                        <tr><td colspan="6">{l s='No records' mod='sameday'}</td></tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <td> {l s='History' mod='sameday'} </td>
                            </tr>
                        </thead>
                        <tbody>
                        <tr>
                            <td>
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>{l s='Parcel number' mod='sameday'}</th>
                                            <th>{l s='Status' mod='sameday'}</th>
                                            <th>{l s='Label' mod='sameday'}</th>
                                            <th>{l s='State' mod='sameday'}</th>
                                            <th>{l s='Date' mod='sameday'}</th>
                                            <th>{l s='County' mod='sameday'}</th>
                                            <th>{l s='Transit location' mod='sameday'}</th>
                                            <th>{l s='Reason' mod='sameday'}</th>
                                        </tr>
                                    </thead>
                                        <tbody id="awb-histories">
                                            <tr><td colspan="8">{l s='No records' mod='sameday'}</td></tr>
                                        </tbody>
                                </table>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">{l s='Close' mod='sameday'}</button>
                </div>
            </div>
        </div>
    </div>
{else}
    <div id="addAwb" class="modal fade" role="dialog">
        <div class="modal-dialog">
            <!-- Modal content-->
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">×</button>
                    <h4 class="modal-title">{l s='Add AWB' mod='sameday'}</h4>
                </div>
                <form action="" method="post" id="form-shipping" class="form-horizontal">
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="col-sm-3 control-label" for="input-key">{l s='Packages' mod='sameday'}</label>
                            <div class="col-sm-4">
                                <div class="input-group">
                                    <input type="text" name="sameday_package_number" class="form-control input-number"
                                           id="sameday_package_qty" value="1" readonly="">
                                    <span class="input-group-btn">
                                  <button type="button" class="btn btn-info btn-number" data-type="plus"
                                          data-field="sameday_package_qty" id="plus-btn">
                                      <span class="icon-plus"></span>
                                  </button>
                                </span>
                                </div>
                            </div>
                            <label class="col-sm-3 control-label"
                                   for="input-key">{l s='Calculated weight' mod='sameday'}</span></label>
                            <div class="col-sm-2">
                                <div class="input-group">
                                    <input type="number" value="0" readonly="readonly" id="calculated-weight"
                                           class="form-control input-number">
                                </div>
                            </div>
                        </div>

                        <!-- Package Number //-->
                        <div class="form-group package_dimension_field">
                            <div class="parcel row">
                                <label class="col-sm-3 control-label"
                                       for="input-length">{l s='Package dimension' mod='sameday'}</label>
                                <div class="col-sm-8" style="padding-bottom: 5px;">
                                    <div class="row">
                                        <div class="col-sm-3">
                                            <input type="number" name="sameday_package_weight[]" value="" min="1"
                                                   placeholder="Weight" id="input-weight"
                                                   class="form-control input-number weight" step="0.1" required>
                                        </div>
                                        <div class="col-sm-3">
                                            <input type="number" name="sameday_package_width[]" value="" min="0"
                                                   placeholder="Width" id="input-width"
                                                   class="form-control input-number">
                                        </div>
                                        <div class="col-sm-3">
                                            <input type="number" name="sameday_package_length[]" value="" min="0"
                                                   placeholder="Length" id="input-length"
                                                   class="form-control input-number">
                                        </div>
                                        <div class="col-sm-3">
                                            <input type="number" name="sameday_package_height[]" value="" min="0"
                                                   placeholder="Height" id="input-height"
                                                   class="form-control input-number">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-1">
                                        <span id="removePackageDimensionField"><i class="icon-remove pull-left"
                                                                                  style="vertical-align: bottom; cursor: pointer; padding-top: 9px;"></i></span>
                                </div>
                            </div>
                        </div>

                        <!-- Insured Value //-->
                        <div class="form-group">
                            <label class="col-sm-3 control-label"
                                   for="input-key">{l s='Insured value' mod='sameday'}</label>
                            <div class="col-sm-4">
                                <input type="number" name="sameday_insured_value" value="0" min="0"
                                       class="form-control">
                            </div>
                        </div>

                        <!-- Observation //-->
                        <div class="form-group">
                            <label class="col-sm-3 control-label"
                                   for="input-key">{l s='Observation' mod='sameday'}</label>
                            <div class="col-sm-9">
                                <input type="text" name="sameday_observation" class="form-control">
                            </div>
                        </div>

                        <!-- Ramburs //-->
                        <div class="form-group">
                            <label class="col-sm-3 control-label"
                                   for="input-key">{l s='Ramburs' mod='sameday'}</label>
                            <div class="col-sm-9">
                                <input type="text" name="sameday_ramburs" value="{$ramburs|escape:'html':'UTF-8'}" class="form-control">
                            </div>
                        </div>

                        <!-- Package Type //-->
                        <div class="form-group">
                            <label class="col-sm-3 control-label"
                                   for="input-status-sameday_package_type">{l s='Package type' mod='sameday'}</label>
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
                            <label class="col-sm-3 control-label"
                                   for="input-status-sameday-pickup_point">{l s='Pickup point' mod='sameday'}</label>
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

                        <!-- Awb Payment //-->
                        <div class="form-group hidden">
                            <label class="col-sm-3 control-label" for="input-status-sameday_awb_payment">Awb
                                payment</label>
                            <div class="col-sm-9">
                                <select name="sameday_awb_payment" id="input-status-sameday_awb_payment"
                                        class="form-control">
                                    <option value="1"> {l s='Client' mod='sameday'}</option>
                                </select>
                            </div>
                        </div>

                        <input type="hidden" name="sameday_third_party_pickup" value="0"/>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="addAwb" class="btn btn-success"><i
                                    class="icon-plus"></i> {l s='Add AWB' mod='sameday'}</button>
                        <button type="button" class="btn btn-default" data-dismiss="modal">{l s='Close' mod='sameday'}</button>
                    </div>
                </form>
            </div>

        </div>
    </div>
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

            $('body').on('change', '.weight', function(){
                $('#calculated-weight').val(0);
                $.each($('input.weight'), function (i, el){
                    var weight = parseFloat($(el).val()) || 0;
                    $('#calculated-weight').val((parseFloat($('#calculated-weight').val()) || 0) + weight);
                });
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
                if (data.summary != undefined) {
                    var summaryHtml = '';
                    $.each(data.summary, function(awb, summary){
                        summaryHtml += '<tr><td>' + awb + '</td><td>' + summary.weight + '</td><td>' + summary.delivered + '</td>' +
                            '<td>' + summary.deliveredAttempts + '</td><td>' + summary.isPickedUp + '</td><td>' + summary.isPickedUpAt + '</td></tr>';
                    });

                    $('#awb-summary').html(summaryHtml);
                }

                if (data.histories != undefined) {
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
</script>