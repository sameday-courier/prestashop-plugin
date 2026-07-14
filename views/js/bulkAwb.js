(function () {
    'use strict';

    var config = typeof samedayBulkAwb !== 'undefined' ? samedayBulkAwb : null;
    var BULK_CHECKBOX_SELECTOR = [
        '#order_grid table.js-grid-table tbody input.js-bulk-action-checkbox:checked',
        'table.order tbody input[name="orderBox[]"]:checked',
    ].join(', ');
    var BULK_CHECKBOX_INPUT_SELECTOR = [
        '#order_grid table.js-grid-table tbody input.js-bulk-action-checkbox',
        'table.order tbody input[name="orderBox[]"]',
    ].join(', ');
    var pendingBulkOrderIds = [];
    var bulkRunResults = {
        generate: [],
        remove: [],
    };

    function getLabels() {
        return (config && config.labels) ? config.labels : {
            csvOrderId: 'Order ID',
            csvStatus: 'Status',
            csvMessage: 'Message',
            csvAwb: 'AWB Number',
            statusSuccess: 'Success',
            statusFailed: 'Failed',
            statusSkipped: 'Skipped',
            removeConfirm: 'Remove AWB for order #%d?',
            removeFailed: 'Could not remove AWB.',
            historyFailed: 'Error occurred while retrieving AWB history.',
            noRecords: 'No records',
        };
    }

    function getSelectedOrderIds() {
        var seen = {};
        var ids = [];

        document.querySelectorAll(BULK_CHECKBOX_SELECTOR).forEach(function (input) {
            if (input.classList.contains('js-bulk-action-select-all')) {
                return;
            }

            var value = parseInt(input.value, 10);
            if (value > 0 && !seen[value]) {
                seen[value] = true;
                ids.push(value);
            }
        });

        return ids;
    }

    function isBulkCheckboxChange(eventTarget) {
        if (!eventTarget || !eventTarget.matches) {
            return false;
        }

        if (eventTarget.matches('.js-bulk-action-select-all')) {
            return true;
        }

        return eventTarget.matches(BULK_CHECKBOX_INPUT_SELECTOR);
    }

    function updateToolbarState() {
        var selected = getSelectedOrderIds();
        var generateBtn = document.getElementById('samedayBulkGenerateBtn');
        var removeBtn = document.getElementById('samedayBulkRemoveBtn');

        if (generateBtn) {
            generateBtn.disabled = selected.length === 0;
        }
        if (removeBtn) {
            removeBtn.disabled = selected.length === 0;
        }
    }

    function isLegacyOrdersList() {
        return !!document.querySelector('table.order')
            || !!document.querySelector('input[name="orderBox[]"]');
    }

    function getLegacyOrdersPanel() {
        var table = document.querySelector('table.order');
        if (!table) {
            return null;
        }

        return table.closest('.panel');
    }

    function getPanelHeadingAction() {
        var panel = getLegacyOrdersPanel();
        if (!panel) {
            return null;
        }

        return panel.querySelector('.panel-heading-action');
    }

    function getOrderGridPanel() {
        return document.getElementById('order_grid_panel')
            || document.querySelector('#order_grid .js-grid-panel')
            || document.querySelector('.js-grid-panel[id="order_grid_panel"]');
    }

    function isToolbarMounted() {
        var toolbar = document.getElementById('sameday-bulk-awb-toolbar');
        if (!toolbar || toolbar.dataset.mounted !== '1') {
            return false;
        }

        if (isLegacyOrdersList()) {
            var headingAction = getPanelHeadingAction();
            return !!(headingAction && headingAction.contains(toolbar));
        }

        var gridPanel = getOrderGridPanel();
        if (!gridPanel) {
            return false;
        }

        var header = gridPanel.querySelector('.card-header.js-grid-header')
            || gridPanel.querySelector('.card-header');

        return !!(header && header.contains(toolbar));
    }

    function mountToolbar() {
        var toolbar = document.getElementById('sameday-bulk-awb-toolbar');
        if (!toolbar) {
            return false;
        }

        if (isToolbarMounted()) {
            return true;
        }

        toolbar.dataset.mounted = '0';

        if (isLegacyOrdersList()) {
            var headingAction = getPanelHeadingAction();
            if (!headingAction) {
                return false;
            }

            headingAction.insertBefore(toolbar, headingAction.firstChild);
            toolbar.dataset.mounted = '1';
            toolbar.classList.add('sameday-bulk-toolbar', 'sameday-bulk-toolbar-legacy');

            return true;
        }

        var gridPanel = getOrderGridPanel();
        if (!gridPanel) {
            return false;
        }

        var header = gridPanel.querySelector('.card-header.js-grid-header')
            || gridPanel.querySelector('.card-header');

        if (!header) {
            return false;
        }

        var headerActions = header.querySelector('.float-right')
            || header.querySelector('.float-end')
            || header.querySelector('.d-inline-block.float-right');

        if (headerActions) {
            header.insertBefore(toolbar, headerActions);
        } else {
            header.appendChild(toolbar);
        }

        toolbar.dataset.mounted = '1';
        toolbar.classList.add('sameday-bulk-toolbar');

        return true;
    }

    function scheduleToolbarMount() {
        if (isToolbarMounted()) {
            return;
        }

        mountToolbar();

        var attempts = 0;
        var maxAttempts = 40;
        var retryTimer = window.setInterval(function () {
            attempts += 1;
            mountToolbar();

            if (isToolbarMounted() || attempts >= maxAttempts) {
                window.clearInterval(retryTimer);
            }
        }, 250);
    }

    function fillOrderList(listEl, orderIds) {
        listEl.innerHTML = '';
        orderIds.forEach(function (orderId) {
            var li = document.createElement('li');
            li.textContent = '#' + orderId;
            listEl.appendChild(li);
        });
    }

    function appendLog(logEl, orderId, message, type) {
        var row = document.createElement('div');
        row.className = 'sameday-bulk-log-row is-' + type;
        row.textContent = '#' + orderId + ' — ' + message;
        logEl.appendChild(row);
    }

    function updateOrderFeedback(orderId, feedback) {
        var checkbox = document.querySelector(
            '#order_grid table.js-grid-table tbody input.js-bulk-action-checkbox[value="' + orderId + '"], ' +
            'table.order tbody input[name="orderBox[]"][value="' + orderId + '"]'
        );
        if (!checkbox) {
            var rowById = document.querySelector('#order_grid tbody tr[data-order-id="' + orderId + '"]');
            if (rowById) {
                var cellById = rowById.querySelector('td.column-sameday_feedback');
                if (cellById) {
                    cellById.innerHTML = feedback || '—';
                }
            }

            return;
        }

        var row = checkbox.closest('tr');
        if (!row) {
            return;
        }

        var cell = row.querySelector('td.column-sameday_feedback');
        if (cell) {
            cell.innerHTML = feedback || '—';
        }
    }

    function formatHistoryDate(dateValue) {
        if (!dateValue) {
            return '';
        }

        if (typeof dateValue === 'string') {
            return dateValue.slice(0, 19);
        }

        if (dateValue.date) {
            return String(dateValue.date).slice(0, 19);
        }

        return '';
    }

    function detachModalToBody(modal) {
        if (!modal) {
            return modal;
        }

        var parentModal = modal.parentElement ? modal.parentElement.closest('.modal') : null;
        if (parentModal && parentModal !== modal) {
            document.body.appendChild(modal);
        } else if (modal.parentElement && modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }

        return modal;
    }

    function showBulkModal(modalId) {
        var modal = document.getElementById(modalId);
        if (!modal) {
            return;
        }

        detachModalToBody(modal);
        modal.style.removeProperty('display');
        modal.setAttribute('aria-hidden', 'false');

        if (typeof jQuery !== 'undefined' && typeof jQuery.fn.modal === 'function') {
            jQuery('.modal-backdrop').remove();
            jQuery('body').removeClass('modal-open').css('padding-right', '');
            jQuery(modal).modal('show');
            return;
        }

        modal.classList.add('show');
        modal.style.display = 'block';
        document.body.classList.add('modal-open');

        var backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';
        backdrop.setAttribute('data-sameday-bulk-backdrop', '1');
        document.body.appendChild(backdrop);
    }

    function hideBulkModal(modalId) {
        var modal = document.getElementById(modalId);
        if (!modal) {
            return;
        }

        if (typeof jQuery !== 'undefined' && typeof jQuery.fn.modal === 'function') {
            jQuery(modal).modal('hide');
            return;
        }

        modal.classList.remove('show');
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
        document.querySelectorAll('[data-sameday-bulk-backdrop]').forEach(function (backdrop) {
            backdrop.remove();
        });
        document.body.classList.remove('modal-open');
    }

    function renderAwbHistoryModal(data) {
        var labels = getLabels();
        var summaryBody = document.getElementById('samedayBulkAwbSummary');
        var historiesBody = document.getElementById('samedayBulkAwbHistories');
        if (!summaryBody || !historiesBody) {
            return;
        }

        var summaryHtml = '';
        if (data.summary) {
            Object.keys(data.summary).forEach(function (awb) {
                var summary = data.summary[awb];
                summaryHtml += '<tr><td>' + awb + '</td><td>' + summary.weight + '</td><td>' +
                    summary.delivered + '</td><td>' + summary.deliveredAttempts + '</td><td>' +
                    summary.isPickedUp + '</td><td>' + summary.isPickedUpAt + '</td></tr>';
            });
        }
        summaryBody.innerHTML = summaryHtml || '<tr><td colspan="6">' + labels.noRecords + '</td></tr>';

        var historiesHtml = '';
        if (data.histories) {
            Object.keys(data.histories).forEach(function (awb) {
                (data.histories[awb] || []).forEach(function (history) {
                    historiesHtml += '<tr><td>' + awb + '</td><td>' + history.name + '</td><td>' +
                        history.label + '</td><td>' + history.state + '</td><td>' +
                        formatHistoryDate(history.date) + '</td><td>' + history.county + '</td><td>' +
                        history.transit + '</td><td>' + history.reason + '</td></tr>';
                });
            });
        }
        historiesBody.innerHTML = historiesHtml || '<tr><td colspan="8">' + labels.noRecords + '</td></tr>';

        showBulkModal('samedayBulkAwbHistoryModal');
    }

    function fetchAwbHistory(awbId) {
        if (!config) {
            return Promise.reject(new Error('Missing bulk configuration'));
        }

        var url = config.ajaxUrl +
            '?action=awb_history' +
            '&awb_id=' + encodeURIComponent(awbId) +
            '&token=' + encodeURIComponent(config.token);

        return fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
            },
        }).then(function (response) {
            return response.json();
        });
    }

    function postAction(action, orderId) {
        if (!config) {
            return Promise.reject(new Error('Missing bulk configuration'));
        }

        var url = config.ajaxUrl +
            '?action=' + encodeURIComponent(action) +
            '&token=' + encodeURIComponent(config.token);

        if (orderId) {
            url += '&order_id=' + encodeURIComponent(orderId);
        }

        return fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
            },
        }).then(function (response) {
            return response.json();
        });
    }

    function buildResultEntry(orderId, data, requestFailed) {
        var labels = getLabels();

        if (requestFailed) {
            return {
                orderId: orderId,
                status: labels.statusFailed,
                message: 'Request failed',
                awbNumber: '',
            };
        }

        if (data.skipped) {
            return {
                orderId: orderId,
                status: labels.statusSkipped,
                message: data.message || 'Skipped',
                awbNumber: data.awb_number || '',
            };
        }

        if (data.success) {
            return {
                orderId: orderId,
                status: labels.statusSuccess,
                message: data.message || 'OK',
                awbNumber: data.awb_number || '',
            };
        }

        return {
            orderId: orderId,
            status: labels.statusFailed,
            message: data.error || 'Error',
            awbNumber: '',
        };
    }

    function isSuccessfulResult(entry, labels) {
        return entry.status === labels.statusSuccess || entry.status === labels.statusSkipped;
    }

    function updateResultsSummary(modalConfig, results) {
        var labels = getLabels();
        var successful = 0;
        var failed = 0;

        results.forEach(function (entry) {
            if (isSuccessfulResult(entry, labels)) {
                successful += 1;
            } else {
                failed += 1;
            }
        });

        if (modalConfig.processedEl) {
            modalConfig.processedEl.textContent = String(results.length);
        }
        if (modalConfig.successEl) {
            modalConfig.successEl.textContent = String(successful);
        }
        if (modalConfig.failedEl) {
            modalConfig.failedEl.textContent = String(failed);
        }
        if (modalConfig.resultsEl) {
            modalConfig.resultsEl.style.display = 'block';
        }
    }

    function escapeCsvValue(value) {
        var text = String(value == null ? '' : value);
        if (/[",\n\r]/.test(text)) {
            return '"' + text.replace(/"/g, '""') + '"';
        }

        return text;
    }

    function downloadResultsCsv(resultsKey, actionPrefix) {
        var results = bulkRunResults[resultsKey] || [];
        if (results.length === 0) {
            return;
        }

        var labels = getLabels();
        var lines = [[
            labels.csvOrderId,
            labels.csvStatus,
            labels.csvMessage,
            labels.csvAwb,
        ].map(escapeCsvValue).join(',')];

        results.forEach(function (entry) {
            lines.push([
                entry.orderId,
                entry.status,
                entry.message,
                entry.awbNumber,
            ].map(escapeCsvValue).join(','));
        });

        var blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        var timestamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
        link.href = URL.createObjectURL(blob);
        link.download = 'sameday-bulk-' + actionPrefix + '-' + timestamp + '.csv';
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(link.href);
    }

    function runSequential(orderIds, action, modalConfig) {
        var total = orderIds.length;
        var processed = 0;
        var resultsKey = action === 'bulk_generate_awb' ? 'generate' : 'remove';

        bulkRunResults[resultsKey] = [];

        modalConfig.confirmEl.style.display = 'none';
        modalConfig.progressEl.style.display = 'block';
        modalConfig.footerConfirmEl.style.display = 'none';
        modalConfig.logEl.innerHTML = '';

        if (modalConfig.resultsEl) {
            modalConfig.resultsEl.style.display = 'none';
        }
        if (modalConfig.processedEl) {
            modalConfig.processedEl.textContent = '0';
        }
        if (modalConfig.successEl) {
            modalConfig.successEl.textContent = '0';
        }
        if (modalConfig.failedEl) {
            modalConfig.failedEl.textContent = '0';
        }

        function finish() {
            modalConfig.percentEl.textContent = '100%';
            modalConfig.barEl.style.width = '100%';
            updateResultsSummary(modalConfig, bulkRunResults[resultsKey]);
            modalConfig.footerDoneEl.style.display = 'flex';
        }

        function next() {
            if (processed >= total) {
                finish();
                return;
            }

            var orderId = orderIds[processed];
            postAction(action, orderId).then(function (data) {
                if (typeof data.feedback !== 'undefined') {
                    updateOrderFeedback(orderId, data.feedback);
                } else if (data.error) {
                    updateOrderFeedback(orderId, data.error);
                }

                var entry = buildResultEntry(orderId, data, false);
                bulkRunResults[resultsKey].push(entry);

                if (data.skipped) {
                    appendLog(modalConfig.logEl, orderId, entry.message, 'info');
                } else if (data.success) {
                    appendLog(modalConfig.logEl, orderId, entry.message, 'success');
                } else {
                    appendLog(modalConfig.logEl, orderId, entry.message, 'error');
                }
            }).catch(function () {
                var entry = buildResultEntry(orderId, {}, true);
                bulkRunResults[resultsKey].push(entry);
                appendLog(modalConfig.logEl, orderId, entry.message, 'error');
            }).finally(function () {
                processed += 1;
                var percent = Math.round((processed / total) * 100);
                modalConfig.percentEl.textContent = percent + '%';
                modalConfig.barEl.style.width = percent + '%';
                next();
            });
        }

        next();
    }

    function resetGenerateModal() {
        pendingBulkOrderIds = [];
        document.getElementById('samedayBulkGenerateConfirm').style.display = 'block';
        document.getElementById('samedayBulkGenerateProgress').style.display = 'none';
        document.getElementById('samedayBulkGenerateFooterConfirm').style.display = 'block';
        document.getElementById('samedayBulkGenerateFooterDone').style.display = 'none';
        document.getElementById('samedayBulkGenerateAgree').checked = false;
        document.getElementById('samedayBulkGenerateProcess').disabled = true;
        document.getElementById('samedayBulkGenerateBar').style.width = '0';
        document.getElementById('samedayBulkGeneratePercent').textContent = '0%';
        document.getElementById('samedayBulkGenerateLog').innerHTML = '';
        document.getElementById('samedayBulkGenerateResults').style.display = 'none';
        document.getElementById('samedayBulkGenerateCountProcessed').textContent = '0';
        document.getElementById('samedayBulkGenerateCountSuccess').textContent = '0';
        document.getElementById('samedayBulkGenerateCountFailed').textContent = '0';
        bulkRunResults.generate = [];
    }

    function resetRemoveModal() {
        pendingBulkOrderIds = [];
        document.getElementById('samedayBulkRemoveConfirm').style.display = 'block';
        document.getElementById('samedayBulkRemoveProgress').style.display = 'none';
        document.getElementById('samedayBulkRemoveFooterConfirm').style.display = 'block';
        document.getElementById('samedayBulkRemoveFooterDone').style.display = 'none';
        document.getElementById('samedayBulkRemoveAgree').checked = false;
        document.getElementById('samedayBulkRemoveProcess').disabled = true;
        document.getElementById('samedayBulkRemoveBar').style.width = '0';
        document.getElementById('samedayBulkRemovePercent').textContent = '0%';
        document.getElementById('samedayBulkRemoveLog').innerHTML = '';
        document.getElementById('samedayBulkRemoveResults').style.display = 'none';
        document.getElementById('samedayBulkRemoveCountProcessed').textContent = '0';
        document.getElementById('samedayBulkRemoveCountSuccess').textContent = '0';
        document.getElementById('samedayBulkRemoveCountFailed').textContent = '0';
        bulkRunResults.remove = [];
    }

    function bindModal(modalId, resetFn) {
        var modal = document.getElementById(modalId);
        if (!modal) {
            return;
        }

        if (typeof jQuery !== 'undefined') {
            jQuery(modal).on('hidden.bs.modal', resetFn);
        }
    }

    function init() {
        if (!document.getElementById('sameday-bulk-awb-toolbar')) {
            return;
        }

        if (document.getElementById('order-view-page')) {
            var toolbarOnOrderView = document.getElementById('sameday-bulk-awb-toolbar');
            if (toolbarOnOrderView) {
                toolbarOnOrderView.remove();
            }

            return;
        }

        scheduleToolbarMount();
        document.querySelectorAll('.sameday-bulk-awb-modal').forEach(function (modal) {
            detachModalToBody(modal);
        });
        updateToolbarState();

        document.addEventListener('change', function (event) {
            if (isBulkCheckboxChange(event.target)) {
                updateToolbarState();
            }
        });

        var gridPanel = getOrderGridPanel();
        if (gridPanel) {
            gridPanel.addEventListener('change', function (event) {
                if (isBulkCheckboxChange(event.target)) {
                    updateToolbarState();
                }
            });
        }

        var legacyPanel = getLegacyOrdersPanel();
        if (legacyPanel) {
            legacyPanel.addEventListener('change', function (event) {
                if (isBulkCheckboxChange(event.target)) {
                    updateToolbarState();
                }
            });
        }

        var generateAgree = document.getElementById('samedayBulkGenerateAgree');
        var generateProcess = document.getElementById('samedayBulkGenerateProcess');
        if (generateAgree && generateProcess) {
            generateAgree.addEventListener('change', function () {
                generateProcess.disabled = !generateAgree.checked;
            });
        }

        var removeAgree = document.getElementById('samedayBulkRemoveAgree');
        var removeProcess = document.getElementById('samedayBulkRemoveProcess');
        if (removeAgree && removeProcess) {
            removeAgree.addEventListener('change', function () {
                removeProcess.disabled = !removeAgree.checked;
            });
        }

        var generateBtn = document.getElementById('samedayBulkGenerateBtn');
        if (generateBtn) {
            generateBtn.addEventListener('click', function () {
                var orderIds = getSelectedOrderIds();
                if (orderIds.length === 0) {
                    return;
                }
                resetGenerateModal();
                pendingBulkOrderIds = orderIds.slice();
                fillOrderList(document.getElementById('samedayBulkGenerateOrderList'), pendingBulkOrderIds);
                showBulkModal('samedayBulkGenerateModal');
            });
        }

        if (generateProcess) {
            generateProcess.addEventListener('click', function () {
                var orderIds = pendingBulkOrderIds.slice();
                if (orderIds.length === 0) {
                    return;
                }

                runSequential(orderIds, 'bulk_generate_awb', {
                    confirmEl: document.getElementById('samedayBulkGenerateConfirm'),
                    progressEl: document.getElementById('samedayBulkGenerateProgress'),
                    footerConfirmEl: document.getElementById('samedayBulkGenerateFooterConfirm'),
                    footerDoneEl: document.getElementById('samedayBulkGenerateFooterDone'),
                    logEl: document.getElementById('samedayBulkGenerateLog'),
                    percentEl: document.getElementById('samedayBulkGeneratePercent'),
                    barEl: document.getElementById('samedayBulkGenerateBar'),
                    resultsEl: document.getElementById('samedayBulkGenerateResults'),
                    processedEl: document.getElementById('samedayBulkGenerateCountProcessed'),
                    successEl: document.getElementById('samedayBulkGenerateCountSuccess'),
                    failedEl: document.getElementById('samedayBulkGenerateCountFailed'),
                });
            });
        }

        var generateDownloadCsv = document.getElementById('samedayBulkGenerateDownloadCsv');
        if (generateDownloadCsv) {
            generateDownloadCsv.addEventListener('click', function () {
                downloadResultsCsv('generate', 'generate');
            });
        }

        var removeBtn = document.getElementById('samedayBulkRemoveBtn');
        if (removeBtn) {
            removeBtn.addEventListener('click', function () {
                var orderIds = getSelectedOrderIds();
                if (orderIds.length === 0) {
                    return;
                }
                resetRemoveModal();
                pendingBulkOrderIds = orderIds.slice();
                fillOrderList(document.getElementById('samedayBulkRemoveOrderList'), pendingBulkOrderIds);
                showBulkModal('samedayBulkRemoveModal');
            });
        }

        if (removeProcess) {
            removeProcess.addEventListener('click', function () {
                var orderIds = pendingBulkOrderIds.slice();
                if (orderIds.length === 0) {
                    return;
                }

                runSequential(orderIds, 'bulk_remove_awb', {
                    confirmEl: document.getElementById('samedayBulkRemoveConfirm'),
                    progressEl: document.getElementById('samedayBulkRemoveProgress'),
                    footerConfirmEl: document.getElementById('samedayBulkRemoveFooterConfirm'),
                    footerDoneEl: document.getElementById('samedayBulkRemoveFooterDone'),
                    logEl: document.getElementById('samedayBulkRemoveLog'),
                    percentEl: document.getElementById('samedayBulkRemovePercent'),
                    barEl: document.getElementById('samedayBulkRemoveBar'),
                    resultsEl: document.getElementById('samedayBulkRemoveResults'),
                    processedEl: document.getElementById('samedayBulkRemoveCountProcessed'),
                    successEl: document.getElementById('samedayBulkRemoveCountSuccess'),
                    failedEl: document.getElementById('samedayBulkRemoveCountFailed'),
                });
            });
        }

        var removeDownloadCsv = document.getElementById('samedayBulkRemoveDownloadCsv');
        if (removeDownloadCsv) {
            removeDownloadCsv.addEventListener('click', function () {
                downloadResultsCsv('remove', 'remove');
            });
        }

        var clearBtn = document.getElementById('samedayBulkClearErrorsBtn');
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                if (!window.confirm('Clear bulk feedback for orders without a generated AWB?')) {
                    return;
                }

                postAction('clear_bulk_errors').then(function (data) {
                    if (data.success) {
                        (data.order_ids || []).forEach(function (orderId) {
                            updateOrderFeedback(orderId, '—');
                        });
                    } else {
                        window.alert(data.error || 'Could not clear errors.');
                    }
                }).catch(function () {
                    window.alert('Request failed.');
                });
            });
        }

        bindModal('samedayBulkGenerateModal', resetGenerateModal);
        bindModal('samedayBulkRemoveModal', resetRemoveModal);

        document.addEventListener('click', function (event) {
            var historyLink = event.target.closest('.sameday-feedback-history');
            if (historyLink && config) {
                event.preventDefault();

                var awbId = parseInt(historyLink.getAttribute('data-awb-id'), 10);
                if (!awbId) {
                    return;
                }

                fetchAwbHistory(awbId).then(function (data) {
                    if (data.error || (!data.summary && !data.histories)) {
                        window.alert(data.error || getLabels().historyFailed);
                        return;
                    }

                    renderAwbHistoryModal(data);
                }).catch(function () {
                    window.alert(getLabels().historyFailed);
                });

                return;
            }

            var removeBtn = event.target.closest('.sameday-feedback-remove');
            if (!removeBtn || !config) {
                return;
            }

            event.preventDefault();

            var orderId = parseInt(removeBtn.getAttribute('data-order-id'), 10);
            if (!orderId) {
                return;
            }

            var labels = getLabels();
            var confirmMessage = labels.removeConfirm
                ? labels.removeConfirm.replace('%d', String(orderId))
                : 'Remove AWB for order #' + orderId + '?';

            if (!window.confirm(confirmMessage)) {
                return;
            }

            removeBtn.disabled = true;

            postAction('bulk_remove_awb', orderId).then(function (data) {
                if (typeof data.feedback !== 'undefined') {
                    updateOrderFeedback(orderId, data.feedback);
                } else if (data.error) {
                    updateOrderFeedback(orderId, data.error);
                }

                if (!data.success) {
                    window.alert(data.error || labels.removeFailed || 'Could not remove AWB.');
                }
            }).catch(function () {
                window.alert(labels.removeFailed || 'Could not remove AWB.');
            }).finally(function () {
                removeBtn.disabled = false;
            });
        });

        window.addEventListener('load', function () {
            scheduleToolbarMount();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
