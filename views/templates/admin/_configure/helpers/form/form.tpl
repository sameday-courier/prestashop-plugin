{*
* @author    Sameday Courier <software@sameday.ro>
* @copyright 2019 Sameday Courier
*}
{extends file="helpers/form/form.tpl"}

{block name="input"}
    {if $input.type == 'calendar'}
        {assign var=days value=['0' => 'Sunday', '1' => 'Monday', '2' => 'Tuesday', '3' => 'Wednesday', '4' => 'Thursday', '5' => 'Friday', '6' => 'Saturday']}
        {foreach from=$days key=k item=v}
            <div class="row {$input.class|escape:'html':'UTF-8'}">
                <div class="col-lg-1">
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="{$input.name|escape:'html':'UTF-8'}[days][{$k|escape:'html':'UTF-8'}]"
                                   {if $fields_value[$input.name]['days'][$k]|default:0}checked{/if}> {l s=$v mod='sameday'}
                        </label>
                    </div>
                </div>
                <div class="col-lg-2">
                    <div class="input-group">
                        <div class="input-group-addon">{l s='From' mod='sameday'}</div>
                        <input
                                id="{if isset($input.id)}{$input.id|escape:'html':'UTF-8'}{else}{$input.name|escape:'html':'UTF-8'}{/if}_from_{$k}"
                                type="text"
                                data-hex="true"
                                {if isset($input.class)}class="{$input.class|escape:'html':'UTF-8'} timepicker"
                                {else}class="timepicker"{/if}
                                name="{$input.name|escape:'html':'UTF-8'}[hours][{$k|escape:'html':'UTF-8'}][from]"
                                value="{$fields_value[$input.name].hours[$k].from|escape:'html':'UTF-8'}"/>
                        <span class="input-group-addon">
					<i class="icon-time"></i>
				</span>
                    </div>
                </div>
                <div class="col-lg-2">
                    <div class="input-group">
                        <div class="input-group-addon">{l s='To' mod='sameday'}</div>
                        <input
                                id="{if isset($input.id)}{$input.id|escape:'html':'UTF-8'}{else}{$input.name|escape:'html':'UTF-8'}{/if}_to_{$k}"
                                type="text"
                                data-hex="true"
                                {if isset($input.class)}class="{$input.class|escape:'html':'UTF-8'} timepicker"
                                {else}class="timepicker"{/if}
                                name="{$input.name|escape:'html':'UTF-8'}[hours][{$k|escape:'html':'UTF-8'}][to]"
                                value="{$fields_value[$input.name].hours[$k].to|escape:'html':'UTF-8'}"/>
                        <span class="input-group-addon">
					<i class="icon-time"></i>
				</span>
                    </div>
                </div>
            </div>
        {/foreach}
    {else}
        {$smarty.block.parent}
    {/if}
{/block}
{block name="script"}
    if ($(".timepicker").length > 0)
    $(".timepicker").timepicker({
        pickDate: false
    });
{/block}