<div class="col-md-3">
    <button class="btn btn-success" data-toggle="modal" data-target="#addPickupPoint">
        <i class="icon-plus"></i> {l s='Add New Pickup Point' mod='samedaycourier'}
    </button>
</div>

<div id="addPickupPoint" class="modal fade" role="dialog">
    <div class="modal-dialog">
        <!-- Modal content-->
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">X</button>
            </div>
            <form action="" method="post" id="form-add-parcels" class="form-horizontal">
                <div class="modal-body">
                    <!-- Alias //-->
                    <div class="form-group">
                        <label class="col-sm-3 control-label" for="sameday_alias">
                            {l s='Sameday Alias' mod='samedaycourier'}
                        </label>
                        <div class="col-sm-9">
                            <input type="text" name="alias" id="sameday_alias" class="form-control">
                        </div>
                    </div>
                    <!-- Address //-->
                    <div class="form-group">
                        <label class="col-sm-3 control-label" for="sameday_address">
                            {l s='Address' mod='samedaycourier'}
                        </label>
                        <div class="col-sm-9">
                            <input type="text" name="address" id="sameday_address" class="form-control">
                        </div>
                    </div>
                    <!-- Postal code //-->
                    <div class="form-group">
                        <label class="col-sm-3 control-label" for="sameday_postal_code">
                            {l s='Postal code' mod='samedaycourier'}
                        </label>
                        <div class="col-sm-9">
                            <input type="text" name="postalCode" id="sameday_postal_code" class="form-control">
                        </div>
                    </div>
                    <!-- Country //-->
                    <div class="form-group">
                        <label class="col-sm-3 control-label" for="sameday_country">
                            {l s='Country' mod='samedaycourier'}
                        </label>
                        <div class="col-sm-9">
                            <select name="country" id="sameday_country" class="form-control">
                                {foreach $countries as $country}
                                    <option value="{$country.value|escape:'html':'UTF-8'}">
                                        {$country.label|escape:'html':'UTF-8'}
                                    </option>
                                {/foreach}
                            </select>
                        </div>
                    </div>
                    <!-- County //-->
                    <div class="form-group">
                        <label class="col-sm-3 control-label" for="sameday_county">
                            {l s='County' mod='samedaycourier'}
                        </label>
                        <div class="col-sm-9">
                            <select name="county" id="sameday_county" class="form-control">
                                {foreach $counties as $county}
                                    <option value="{$county.value|escape:'html':'UTF-8'}">
                                        {$county.label|escape:'html':'UTF-8'}
                                    </option>
                                {/foreach}
                            </select>
                        </div>
                    </div>
                    <!-- City //-->
                    <div class="form-group">
                        <label class="col-sm-3 control-label" for="sameday_city">
                            {l s='City' mod='samedaycourier'}
                        </label>
                        <div class="col-sm-9">
                            <select name="city" id="sameday_city" class="form-control">
                                {foreach $cities as $city}
                                    <option value="{$city.value|escape:'html':'UTF-8'}">
                                        {$city.label|escape:'html':'UTF-8'}
                                    </option>
                                {/foreach}
                            </select>
                        </div>
                    </div>
                    <!-- Contact person name //-->
                    <div class="form-group">
                        <label class="col-sm-3 control-label" for="sameday_contact_person">
                            {l s='Contact person' mod='samedaycourier'}
                        </label>
                        <div class="col-sm-9">
                            <input type="text" name="contactPerson" id="sameday_contact_person" class="form-control">
                        </div>
                    </div>
                    <!-- Contact person phone //-->
                    <div class="form-group">
                        <label class="col-sm-3 control-label" for="sameday_contact_phone">
                            {l s='Contact person phone' mod='samedaycourier'}
                        </label>
                        <div class="col-sm-9">
                            <input type="text" name="contactPersonPhone" id="sameday_contact_person" class="form-control">
                        </div>
                    </div>
                    <!-- Is default //-->
                    <div class="form-group">
                        <label class="col-sm-3 control-label" for="sameday_is_default">
                            {l s='Is Default' mod='samedaycourier'}
                        </label>
                        <div class="col-sm-1">
                            <input type="checkbox" name="isDefault" id="sameday_is_default" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="add_new_pickup_point" class="btn btn-success">
                        <i class="icon-plus"></i> {l s='Add new Pickup Point' mod='samedaycourier'}
                    </button>
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(() => {
        let citySelect = $('#sameday_city');
        $('#sameday_county').on('change', (event) => {
            $.ajax({
                url: '{$changeCountyAction}',
                type: 'POST',
                data: {
                    'action': 'change_county',
                    'token': '{$token}',
                    'county_id': event.target.value
                },
                dataType: 'json',
                success: (response) => {
                    citySelect.empty();
                    for (let city of response.cities) {
                        citySelect.append($('<option>', { value: city.value, text: city.label }));
                        citySelect.focus();
                    }
                },
                error: (error) => {
                    console.log(error);
                },
                beforeSend: () => {
                    citySelect.empty();
                    citySelect.append($('<option>', { value: "", text: "Waiting..." }));
                }
            });
        });
    });
</script>