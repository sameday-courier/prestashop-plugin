(function () {
    'use strict';

    var config = typeof samedayBulkAwb !== 'undefined' ? samedayBulkAwb : null;
    var BULK_CHECKBOX_SELECTOR = '#order_grid input.js-bulk-action-checkbox';
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
        };
    }

    function getSelectedOrderIds() {
        var ids = [];
        document.querySelectorAll(BULK_CHECKBOX_SELECTOR + ':checked').forEach(function (input) {
            var value = parseInt(input.value, 10);
            if (value > 0) {
                ids.push(value);
            }
        });

        return ids;
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

    function mountToolbar() {
        var gridPanel = document.getElementById('order_grid_panel');
        var toolbar = document.getElementById('sameday-bulk-awb-toolbar');

        if (!gridPanel || !toolbar || toolbar.dataset.mounted === '1') {
            return;
        }

        var header = gridPanel.querySelector('.card-header.js-grid-header')
            || gridPanel.querySelector('.card-header');

        if (!header) {
            return;
        }

        var headerActions = header.querySelector('.float-right')
            || header.querySelector('.float-end');

        if (headerActions) {
            headerActions.insertBefore(toolbar, headerActions.firstChild);
        } else {
            header.appendChild(toolbar);
        }

        toolbar.dataset.mounted = '1';
        toolbar.classList.add('sameday-bulk-toolbar');
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
            BULK_CHECKBOX_SELECTOR + '[value="' + orderId + '"]'
        );
        if (!checkbox) {
            return;
        }

        var row = checkbox.closest('tr');
        if (!row) {
            return;
        }

        var cell = row.querySelector('td.column-sameday_feedback');
        if (cell) {
            cell.textContent = feedback || '—';
        }
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

        mountToolbar();
        updateToolbarState();

        document.addEventListener('change', function (event) {
            if (event.target && event.target.matches(BULK_CHECKBOX_SELECTOR)) {
                updateToolbarState();
            }
        });

        var gridPanel = document.getElementById('order_grid_panel');
        if (gridPanel) {
            gridPanel.addEventListener('change', function (event) {
                if (event.target && event.target.matches(BULK_CHECKBOX_SELECTOR)) {
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
                fillOrderList(document.getElementById('samedayBulkGenerateOrderList'), orderIds);
                resetGenerateModal();
                if (typeof jQuery !== 'undefined') {
                    jQuery('#samedayBulkGenerateModal').modal('show');
                }
            });
        }

        if (generateProcess) {
            generateProcess.addEventListener('click', function () {
                var orderIds = getSelectedOrderIds();
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
                fillOrderList(document.getElementById('samedayBulkRemoveOrderList'), orderIds);
                resetRemoveModal();
                if (typeof jQuery !== 'undefined') {
                    jQuery('#samedayBulkRemoveModal').modal('show');
                }
            });
        }

        if (removeProcess) {
            removeProcess.addEventListener('click', function () {
                var orderIds = getSelectedOrderIds();
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

        if (!document.getElementById('order_grid_panel')) {
            var retries = 0;
            var retryTimer = window.setInterval(function () {
                mountToolbar();
                retries += 1;
                if (document.getElementById('order_grid_panel') || retries >= 20) {
                    window.clearInterval(retryTimer);
                }
            }, 250);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
