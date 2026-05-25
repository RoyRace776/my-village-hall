window.MyVHReportRunner = (function() {
    function readRootPayload(root) {
        const contextScript = root.querySelector('script[data-myvh-report-context]');
        const bootstrapScript = root.querySelector('script[data-myvh-report-bootstrap]');

        const parse = function(scriptElement) {
            if (!scriptElement) {
                return null;
            }

            const raw = String(scriptElement.textContent || '').trim();
            if (raw === '') {
                return null;
            }

            try {
                const parsed = JSON.parse(raw);
                return parsed && typeof parsed === 'object' ? parsed : null;
            } catch (_error) {
                return null;
            }
        };

        return {
            context: parse(contextScript),
            bootstrap: parse(bootstrapScript)
        };
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function createFallbackTable(tableElement) {
        const state = {
            data: [],
            columns: []
        };

        const render = function() {
            const columns = Array.isArray(state.columns) && state.columns.length > 0
                ? state.columns
                : defaultColumnsForRows(state.data);

            if (!Array.isArray(state.data) || state.data.length === 0 || columns.length === 0) {
                tableElement.innerHTML = '<div class="myvh-report-empty">No data to display.</div>';
                return;
            }

            const thead = '<tr>' + columns.map(function(column) {
                return '<th>' + escapeHtml(column.title || column.field || '') + '</th>';
            }).join('') + '</tr>';

            const tbody = state.data.map(function(row) {
                return '<tr>' + columns.map(function(column) {
                    const value = row && Object.prototype.hasOwnProperty.call(row, column.field)
                        ? row[column.field]
                        : '';
                    return '<td>' + escapeHtml(value == null ? '' : value) + '</td>';
                }).join('') + '</tr>';
            }).join('');

            tableElement.innerHTML = [
                '<table class="myvh-report-fallback-table">',
                '<thead>', thead, '</thead>',
                '<tbody>', tbody, '</tbody>',
                '</table>'
            ].join('');
        };

        return {
            setColumns: function(columns) {
                state.columns = Array.isArray(columns) ? columns.slice() : [];
                render();
            },
            setData: function(rows) {
                state.data = Array.isArray(rows) ? rows.slice() : [];
                render();
                return Promise.resolve();
            },
            getData: function() {
                return state.data.slice();
            },
            download: function(_type, filename) {
                const rows = state.data;
                if (!Array.isArray(rows) || rows.length === 0) {
                    return;
                }

                const columns = Array.isArray(state.columns) && state.columns.length > 0
                    ? state.columns
                    : defaultColumnsForRows(rows);

                const csvRows = [];
                csvRows.push(columns.map(function(column) {
                    const header = String(column.title || column.field || '');
                    return '"' + header.replace(/"/g, '""') + '"';
                }).join(','));

                rows.forEach(function(row) {
                    csvRows.push(columns.map(function(column) {
                        const value = row && Object.prototype.hasOwnProperty.call(row, column.field)
                            ? row[column.field]
                            : '';
                        return '"' + String(value == null ? '' : value).replace(/"/g, '""') + '"';
                    }).join(','));
                });

                const blob = new Blob([csvRows.join('\n')], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = filename || 'report.csv';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
            }
        };
    }

    function ensureState() {
        if (!window.MyVHReportState || typeof window.MyVHReportState !== 'object') {
            window.MyVHReportState = {
                context: window.MyVHReportContext || {},
                bootstrap: window.MyVHReportBootstrap || {},
                table: null,
                builder: {}
            };
        }

        return window.MyVHReportState;
    }

    function defaultColumnsForRows(rows) {
        const first = Array.isArray(rows) && rows.length > 0 ? rows[0] : null;
        if (!first || typeof first !== 'object') {
            return [];
        }

        return Object.keys(first).map(function(key) {
            return {
                title: String(key || '').replace(/_/g, ' ').replace(/\b\w/g, function(character) {
                    return character.toUpperCase();
                }),
                field: key,
                sorter: 'string',
                headerFilter: 'input'
            };
        });
    }

    function setStatus(statusElement, message, isError) {
        if (!statusElement) {
            return;
        }

        statusElement.textContent = String(message || '');
        statusElement.classList.toggle('is-error', !!isError);
    }

    function runTable(state, table, reportId, statusElement) {
        const endpoints = state.bootstrap.endpoints || {};
        const runUrl = String(endpoints.run || '');

        if (!runUrl || !reportId) {
            setStatus(statusElement, 'Select a report before running.', true);
            return Promise.resolve();
        }

        setStatus(statusElement, 'Running report...', false);

        const url = new URL(runUrl, window.location.origin);
        url.searchParams.set('report_id', String(reportId));

        return fetch(url.toString(), {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'X-WP-Nonce': String(state.bootstrap.restNonce || '')
            }
        })
            .then(function(response) {
                return response.json().catch(function() {
                    return {};
                }).then(function(payload) {
                    return {
                        ok: response.ok,
                        payload: payload
                    };
                });
            })
            .then(function(result) {
                if (!result.ok) {
                    const message = result.payload && result.payload.message
                        ? String(result.payload.message)
                        : 'Failed to run report.';
                    throw new Error(message);
                }

                const rows = Array.isArray(result.payload && result.payload.rows)
                    ? result.payload.rows
                    : Array.isArray(result.payload && result.payload.data)
                        ? result.payload.data
                    : [];

                const columns = defaultColumnsForRows(rows);
                if (columns.length > 0) {
                    table.setColumns(columns);
                }

                return table.setData(rows).then(function() {
                    if (rows.length === 0) {
                        setStatus(statusElement, 'Report ran successfully. No rows returned.', false);
                    } else {
                        setStatus(statusElement, 'Report ran successfully. ' + rows.length + ' row(s) loaded.', false);
                    }
                });
            })
            .catch(function(error) {
                table.setData([]);
                const message = error && error.message ? error.message : 'Failed to run report.';
                setStatus(statusElement, message, true);
            });
    }

    function refreshReportSelectOptions(root, state) {
        const reportSelect = root.querySelector('[data-report-select]');
        if (!reportSelect) {
            return;
        }

        const formatReportLabel = function(report) {
            const name = String((report && report.name) || '');
            const type = String((report && report.type) || 'user').toLowerCase() === 'system' ? 'System' : 'User';
            const locked = Number((report && report.id) || 0) <= 0 ? ', locked' : '';

            return name ? name + ' (' + type + locked + ')' : type + locked;
        };

        const current = Number(reportSelect.value || 0);
        const reports = Array.isArray(state.bootstrap.reports) ? state.bootstrap.reports : [];

        reportSelect.innerHTML = '';
        reports.forEach(function(report) {
            const option = document.createElement('option');
            option.value = String(report.id || '');
            option.textContent = formatReportLabel(report);
            option.dataset.source = String(report.data_source || '');
            option.dataset.reportType = String(report.type || 'user');
            option.dataset.reportDeletable = Number(report.id || 0) > 0 ? '1' : '0';
            reportSelect.appendChild(option);
        });

        if (current > 0 && reports.some(function(item) {
            return Number(item.id || 0) === current;
        })) {
            reportSelect.value = String(current);
        }
    }

    function resolvePageLengthFromBootstrap(bootstrap) {
        const configured = Number((bootstrap && bootstrap.pagination && bootstrap.pagination.pageLength) || 25);
        return configured > 0 ? configured : 25;
    }

    function applyPageLengthToTable(table, pageLength) {
        if (!table || typeof table.setPageSize !== 'function') {
            return;
        }

        table.setPageSize(pageLength);
    }

    function initRoot(root) {
        if (!root || root.dataset.reportRunnerInitialized === '1') {
            return;
        }

        const state = ensureState();
        const payload = readRootPayload(root);

        if (payload.context && typeof payload.context === 'object') {
            state.context = payload.context;
        }

        if (payload.bootstrap && typeof payload.bootstrap === 'object') {
            state.bootstrap = payload.bootstrap;
        }

        const reportSelect = root.querySelector('[data-report-select]');
        const runButton = root.querySelector('[data-report-run]');
        const exportButton = root.querySelector('[data-report-export]');
        const statusElement = root.querySelector('[data-report-status]');
        const tableElement = root.querySelector('#myvh-report-table');
        const endpoints = state.bootstrap.endpoints || {};
        const runUrl = String(endpoints.run || '');
        const initialReportId = Number((state.bootstrap && state.bootstrap.initialReportId) || 0);
        const pageLength = resolvePageLengthFromBootstrap(state.bootstrap);

        if (reportSelect && initialReportId > 0) {
            const hasOption = Array.from(reportSelect.options || []).some(function(option) {
                return Number(option.value || 0) === initialReportId;
            });

            if (hasOption) {
                reportSelect.value = String(initialReportId);
            }
        }

        if (!tableElement || !runUrl) {
            setStatus(statusElement, 'Report runner is not configured correctly.', true);
            root.dataset.reportRunnerInitialized = '1';
            return;
        }

        const hasTabulator = typeof Tabulator !== 'undefined';

        const table = hasTabulator
            ? new Tabulator(tableElement, {
                layout: 'fitColumns',
                data: [],
                pagination: true,
                paginationSize: pageLength
            })
            : createFallbackTable(tableElement);

        if (!hasTabulator) {
            setStatus(statusElement, 'Table view is running in compatibility mode.', false);
        }

        state.table = table;

        if (runButton && reportSelect) {
            runButton.addEventListener('click', function() {
                const selectedId = Number(reportSelect.value || 0);
                runTable(state, table, selectedId, statusElement);
            });
        }

        if (exportButton) {
            exportButton.addEventListener('click', function() {
                const selectedId = Number(reportSelect && reportSelect.value ? reportSelect.value : 0);
                if (!selectedId) {
                    setStatus(statusElement, 'Select a report before exporting CSV.', true);
                    return;
                }

                const reportName = reportSelect && reportSelect.selectedOptions && reportSelect.selectedOptions[0]
                    ? String(reportSelect.selectedOptions[0].textContent || 'report')
                    : 'report';
                const safeName = reportName.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '') || 'report';
                const csvUrl = new URL(runUrl, window.location.origin);
                csvUrl.searchParams.set('report_id', String(selectedId));
                csvUrl.searchParams.set('format', 'csv');

                setStatus(statusElement, 'Preparing CSV export...', false);

                fetch(csvUrl.toString(), {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        'X-WP-Nonce': String(state.bootstrap.restNonce || '')
                    }
                })
                    .then(function(response) {
                        return response.blob().then(function(blob) {
                            return {
                                ok: response.ok,
                                blob: blob,
                                headers: response.headers
                            };
                        });
                    })
                    .then(function(result) {
                        if (!result.ok) {
                            throw new Error('Failed to export CSV.');
                        }

                        const blobUrl = URL.createObjectURL(result.blob);
                        const link = document.createElement('a');
                        link.href = blobUrl;
                        link.download = safeName + '.csv';
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        URL.revokeObjectURL(blobUrl);
                        setStatus(statusElement, 'CSV export started.', false);
                    })
                    .catch(function(error) {
                        const message = error && error.message ? error.message : 'Failed to export CSV.';
                        setStatus(statusElement, message, true);
                    });
            });
        }

        document.addEventListener('myvh:report-saved', function(event) {
            refreshReportSelectOptions(root, state);

            const savedReport = event && event.detail && event.detail.report ? event.detail.report : null;
            const savedReportId = savedReport ? Number(savedReport.id || 0) : 0;
            if (reportSelect && savedReportId > 0) {
                reportSelect.value = String(savedReportId);
            }
        });

        document.addEventListener('myvh:settings-saved', function(event) {
            const detail = event && event.detail ? event.detail : {};
            if (String((detail && detail.group) || '') !== 'general') {
                return;
            }

            const settings = detail && detail.settings && typeof detail.settings === 'object' ? detail.settings : {};
            const nextPageLength = Number(settings.report_page_length || 0);
            const pageLengthValue = nextPageLength > 0 ? nextPageLength : 25;

            if (!state.bootstrap || typeof state.bootstrap !== 'object') {
                state.bootstrap = {};
            }

            if (!state.bootstrap.pagination || typeof state.bootstrap.pagination !== 'object') {
                state.bootstrap.pagination = {};
            }

            state.bootstrap.pagination.pageLength = pageLengthValue;
            applyPageLengthToTable(table, pageLengthValue);
        });

        if (reportSelect && reportSelect.options.length === 0) {
            if (runButton) {
                runButton.disabled = true;
            }
            if (exportButton) {
                exportButton.disabled = true;
            }
            setStatus(statusElement, 'No reports are available for your account.', true);
        } else if (reportSelect && Number(reportSelect.value || 0) > 0) {
            runTable(state, table, Number(reportSelect.value || 0), statusElement);
        }

        root.dataset.reportRunnerInitialized = '1';
    }

    function init() {
        document.querySelectorAll('.myvh-report-app').forEach(initRoot);
    }

    return {
        init: init
    };
})();
