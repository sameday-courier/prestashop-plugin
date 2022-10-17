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
{include file='./_partials/checkout_lockers.tpl'}
{if isset($lockers)}
    <table class="resume table table-bordered">
        <tbody>
            <tr>
                <td>{l s='Select locker' mod='samedaycourier'}</td>
                <td>
                    <select name="samedaycourier_locker_id" id="lockerIdSelector">
                        <option value=""> {l s='Select locker' mod='samedaycourier'} </option>
                        {foreach from=$lockers key=city item = cityLockers}
                            <optgroup label="{$city|escape:'htmlall':'UTF-8'}">
                            {foreach from=$cityLockers item=locker}
                                <option value="{$locker.id|escape:'htmlall':'UTF-8'}"
                                        {if $locker.id==$lockerId}selected="selected"{/if}
                                        data-name="{$locker.name|escape:'htmlall':'UTF-8'}"
                                        data-address="{$locker.address|escape:'htmlall':'UTF-8'}"
                                >
                            {/foreach}
                        {/foreach}
                    </select>
                </td>
            </tr>
        </tbody>
    </table>
{/if}
