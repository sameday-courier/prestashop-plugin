{**
 * Bulk AWB toolbar and modals for orders list
 *}
{if $sameday_bulk_awb_enabled}
<div id="sameday-bulk-awb-toolbar" class="sameday-bulk-toolbar sameday-bulk-toolbar-row">
    <button type="button" class="btn btn-primary" id="samedayBulkGenerateBtn" disabled>
        {l s='Generate AWB' mod='samedaycourier'}
    </button>
    <button type="button" class="btn btn-danger" id="samedayBulkRemoveBtn" disabled>
        {l s='Remove AWB' mod='samedaycourier'}
    </button>
    <button type="button" class="btn btn-warning" id="samedayBulkClearErrorsBtn">
        {l s='Clear Errors' mod='samedaycourier'}
    </button>
</div>

<div class="modal fade sameday-bulk-awb-modal" id="samedayBulkGenerateModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header sameday-bulk-awb-header">
                <h4 class="modal-title">{l s='AWB Bulk Generation' mod='samedaycourier'}</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div id="samedayBulkGenerateConfirm">
                    <p>{l s='The following orders will be processed:' mod='samedaycourier'}</p>
                    <ul id="samedayBulkGenerateOrderList" class="sameday-bulk-confirm-list"></ul>
                    <div class="sameday-bulk-confirm-agree">
                        <label>
                            <input type="checkbox" id="samedayBulkGenerateAgree">
                            <small>{l s='I agree that the price estimations are subject to change due to bulk generation inaccuracy potential.' mod='samedaycourier'}</small>
                        </label>
                    </div>
                </div>
                <div id="samedayBulkGenerateProgress" style="display:none;">
                    <div class="sameday-bulk-progress-label">
                        <span>{l s='Generating AWBs' mod='samedaycourier'}</span>
                        <span id="samedayBulkGeneratePercent">0%</span>
                    </div>
                    <div class="sameday-bulk-progress-track">
                        <div id="samedayBulkGenerateBar" class="sameday-bulk-progress-bar"></div>
                    </div>
                    <div id="samedayBulkGenerateResults" class="sameday-bulk-results" style="display:none;">
                        <div class="sameday-bulk-results-cards">
                            <div class="sameday-bulk-result-card">
                                <span class="sameday-bulk-result-value" id="samedayBulkGenerateCountProcessed">0</span>
                                <span class="sameday-bulk-result-label">{l s='Processed' mod='samedaycourier'}</span>
                            </div>
                            <div class="sameday-bulk-result-card is-success">
                                <span class="sameday-bulk-result-value" id="samedayBulkGenerateCountSuccess">0</span>
                                <span class="sameday-bulk-result-label">{l s='Successful' mod='samedaycourier'}</span>
                            </div>
                            <div class="sameday-bulk-result-card is-failed">
                                <span class="sameday-bulk-result-value" id="samedayBulkGenerateCountFailed">0</span>
                                <span class="sameday-bulk-result-label">{l s='Failed' mod='samedaycourier'}</span>
                            </div>
                        </div>
                    </div>
                    <div id="samedayBulkGenerateLog" class="sameday-bulk-log"></div>
                </div>
            </div>
            <div class="modal-footer">
                <div id="samedayBulkGenerateFooterConfirm">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">{l s='Cancel' mod='samedaycourier'}</button>
                    <button type="button" class="btn btn-primary" id="samedayBulkGenerateProcess" disabled>{l s='Process AWB' mod='samedaycourier'}</button>
                </div>
                <div id="samedayBulkGenerateFooterDone" style="display:none;">
                    <button type="button" class="btn btn-outline-secondary" id="samedayBulkGenerateDownloadCsv">
                        {l s='Download CSV' mod='samedaycourier'}
                    </button>
                    <button type="button" class="btn btn-primary" data-dismiss="modal">{l s='Done' mod='samedaycourier'}</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade sameday-bulk-awb-modal" id="samedayBulkRemoveModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header sameday-bulk-awb-header">
                <h4 class="modal-title">{l s='Bulk AWB removal' mod='samedaycourier'}</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div id="samedayBulkRemoveConfirm">
                    <p>{l s='The following orders will be processed:' mod='samedaycourier'}</p>
                    <ul id="samedayBulkRemoveOrderList" class="sameday-bulk-confirm-list"></ul>
                    <div class="sameday-bulk-confirm-agree">
                        <label>
                            <input type="checkbox" id="samedayBulkRemoveAgree">
                            <small>{l s='I understand that AWB removal cannot be undone.' mod='samedaycourier'}</small>
                        </label>
                    </div>
                </div>
                <div id="samedayBulkRemoveProgress" style="display:none;">
                    <div class="sameday-bulk-progress-label">
                        <span>{l s='Removing AWBs' mod='samedaycourier'}</span>
                        <span id="samedayBulkRemovePercent">0%</span>
                    </div>
                    <div class="sameday-bulk-progress-track">
                        <div id="samedayBulkRemoveBar" class="sameday-bulk-progress-bar is-remove"></div>
                    </div>
                    <div id="samedayBulkRemoveResults" class="sameday-bulk-results" style="display:none;">
                        <div class="sameday-bulk-results-cards">
                            <div class="sameday-bulk-result-card">
                                <span class="sameday-bulk-result-value" id="samedayBulkRemoveCountProcessed">0</span>
                                <span class="sameday-bulk-result-label">{l s='Processed' mod='samedaycourier'}</span>
                            </div>
                            <div class="sameday-bulk-result-card is-success">
                                <span class="sameday-bulk-result-value" id="samedayBulkRemoveCountSuccess">0</span>
                                <span class="sameday-bulk-result-label">{l s='Successful' mod='samedaycourier'}</span>
                            </div>
                            <div class="sameday-bulk-result-card is-failed">
                                <span class="sameday-bulk-result-value" id="samedayBulkRemoveCountFailed">0</span>
                                <span class="sameday-bulk-result-label">{l s='Failed' mod='samedaycourier'}</span>
                            </div>
                        </div>
                    </div>
                    <div id="samedayBulkRemoveLog" class="sameday-bulk-log"></div>
                </div>
            </div>
            <div class="modal-footer">
                <div id="samedayBulkRemoveFooterConfirm">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">{l s='Cancel' mod='samedaycourier'}</button>
                    <button type="button" class="btn btn-danger" id="samedayBulkRemoveProcess" disabled>{l s='Process AWB' mod='samedaycourier'}</button>
                </div>
                <div id="samedayBulkRemoveFooterDone" style="display:none;">
                    <button type="button" class="btn btn-outline-secondary" id="samedayBulkRemoveDownloadCsv">
                        {l s='Download CSV' mod='samedaycourier'}
                    </button>
                    <button type="button" class="btn btn-primary" data-dismiss="modal">{l s='Done' mod='samedaycourier'}</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade sameday-bulk-awb-modal" id="samedayBulkAwbHistoryModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header sameday-bulk-awb-header">
                <h4 class="modal-title">{l s='AWB History' mod='samedaycourier'}</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <table class="table table-bordered">
                    <thead>
                        <tr><td>{l s='Summary' mod='samedaycourier'}</td></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>{l s='Parcel number' mod='samedaycourier'}</th>
                                            <th>{l s='Parcel weight' mod='samedaycourier'}</th>
                                            <th>{l s='Delivered' mod='samedaycourier'}</th>
                                            <th>{l s='Delivery attempts' mod='samedaycourier'}</th>
                                            <th>{l s='Is picked up' mod='samedaycourier'}</th>
                                            <th>{l s='Picked up at' mod='samedaycourier'}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="samedayBulkAwbSummary"></tbody>
                                </table>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <table class="table table-bordered">
                    <thead>
                        <tr><td>{l s='History' mod='samedaycourier'}</td></tr>
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
                                    <tbody id="samedayBulkAwbHistories"></tbody>
                                </table>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-dismiss="modal">{l s='Close' mod='samedaycourier'}</button>
            </div>
        </div>
    </div>
</div>
{/if}
