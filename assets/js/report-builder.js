window.MyVHReportBuilder = (function() {
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

    function ensureState() {
        const context = window.MyVHReportContext || {};
        const bootstrap = window.MyVHReportBootstrap || {};

        if (!window.MyVHReportState || typeof window.MyVHReportState !== 'object') {
            window.MyVHReportState = {
                context: context,
                bootstrap: bootstrap,
                table: null,
                builder: {
                    reportId: 0,
                    reportType: 'user',
                    source: '',
                    name: '',
                    description: '',
                    selectedFields: [],
                    groupBy: [],
                    aggregates: [],
                    filters: [],
                    orderBy: [],
                    limit: 1000
                }
            };
        }

        window.MyVHReportState.context = context;
        window.MyVHReportState.bootstrap = bootstrap;

        return window.MyVHReportState;
    }

    function initializeNewBuilderState(state) {
        const sources = getSources(state);

        state.builder = {
            reportId: 0,
            reportType: 'user',
            source: sources.length > 0 ? String(sources[0].source || '') : '',
            name: '',
            description: '',
            selectedFields: [],
            groupBy: [],
            aggregates: [],
            filters: [],
            orderBy: [],
            limit: 1000
        };
    }

    function resetBuilderToNewReport(root, state) {
        initializeNewBuilderState(state);

        const reportSelect = root ? root.querySelector('[data-report-select]') : null;
        if (reportSelect) {
            reportSelect.value = '';
        }
    }

    function getSources(state) {
        const schema = state.bootstrap.schema || {};
        return Array.isArray(schema.sources) ? schema.sources : [];
    }

    function getSourceConfig(state, source) {
        const sources = getSources(state);
        return sources.find(function(item) {
            return item && item.source === source;
        }) || null;
    }

    function syncFieldSelections(state) {
        const sourceConfig = getSourceConfig(state, state.builder.source);
        const fields = sourceConfig && Array.isArray(sourceConfig.fields) ? sourceConfig.fields : [];
        const fieldNames = fields.map(function(field) {
            return String(field.name || '');
        }).filter(Boolean);

        state.builder.selectedFields = state.builder.selectedFields.filter(function(name) {
            return fieldNames.indexOf(name) !== -1;
        });

        state.builder.groupBy = state.builder.groupBy.filter(function(name) {
            return fieldNames.indexOf(String(name || '')) !== -1;
        });

        state.builder.filters = state.builder.filters.filter(function(filter) {
            return filter && fieldNames.indexOf(String(filter.field || '')) !== -1;
        });

        state.builder.orderBy = state.builder.orderBy.filter(function(order) {
            return order && fieldNames.indexOf(String(order.field || '')) !== -1;
        });
    }

    function parseDefinition(raw) {
        if (!raw || typeof raw !== 'string') {
            return null;
        }

        try {
            const parsed = JSON.parse(raw);
            return parsed && typeof parsed === 'object' ? parsed : null;
        } catch (error) {
            return null;
        }
    }

    function formatReportLabel(report) {
        const name = String((report && report.name) || '');
        const type = String((report && report.type) || 'user').toLowerCase() === 'system' ? 'System' : 'User';
        const locked = Number((report && report.id) || 0) <= 0 ? ', locked' : '';

        return name ? name + ' (' + type + locked + ')' : type + locked;
    }

    function updateDeleteButtonState(root, state) {
        const deleteButton = root.querySelector('[data-builder-delete]');
        if (!deleteButton) {
            return;
        }

        const reportId = Number(state.builder.reportId || 0);
        const canDelete = reportId > 0;

        deleteButton.disabled = !canDelete;
        deleteButton.title = canDelete ? '' : 'Built-in system reports cannot be deleted.';
    }

    function renderSourceOptions(root, state) {
        const sourceSelect = root.querySelector('[data-builder-source]');
        if (!sourceSelect) {
            return;
        }

        const sources = getSources(state);
        sourceSelect.innerHTML = '';

        sources.forEach(function(source) {
            const option = document.createElement('option');
            option.value = String(source.source || '');
            option.textContent = String(source.label || source.source || '');
            sourceSelect.appendChild(option);
        });

        if (!state.builder.source && sources.length > 0) {
            state.builder.source = String(sources[0].source || '');
        }

        sourceSelect.value = state.builder.source;
    }

    function renderFieldLists(root, state) {
        const availableContainer = root.querySelector('[data-builder-available-fields]');
        const selectedContainer = root.querySelector('[data-builder-selected-fields]');

        if (!availableContainer || !selectedContainer) {
            return;
        }

        const sourceConfig = getSourceConfig(state, state.builder.source);
        const fields = sourceConfig && Array.isArray(sourceConfig.fields) ? sourceConfig.fields : [];

        availableContainer.innerHTML = '';
        selectedContainer.innerHTML = '';

        fields.forEach(function(field) {
            const fieldName = String(field.name || '');
            if (fieldName === '') {
                return;
            }

            const label = String(field.label || fieldName);

            const availableButton = document.createElement('button');
            availableButton.type = 'button';
            availableButton.className = 'button button-small';
            availableButton.textContent = label;
            availableButton.addEventListener('click', function() {
                if (state.builder.selectedFields.indexOf(fieldName) === -1) {
                    state.builder.selectedFields.push(fieldName);
                    renderFieldLists(root, state);
                    renderFilters(root, state);
                    renderSort(root, state);
                }
            });
            availableContainer.appendChild(availableButton);

            if (state.builder.selectedFields.indexOf(fieldName) !== -1) {
                const selectedButton = document.createElement('button');
                selectedButton.type = 'button';
                selectedButton.className = 'button button-small';
                selectedButton.textContent = label + ' ×';
                selectedButton.addEventListener('click', function() {
                    state.builder.selectedFields = state.builder.selectedFields.filter(function(item) {
                        return item !== fieldName;
                    });
                    syncFieldSelections(state);
                    renderFieldLists(root, state);
                    renderFilters(root, state);
                    renderSort(root, state);
                });
                selectedContainer.appendChild(selectedButton);
            }
        });
    }

    function renderFilters(root, state) {
        const filtersContainer = root.querySelector('[data-builder-filters]');
        if (!filtersContainer) {
            return;
        }

        filtersContainer.innerHTML = '';

        const sourceConfig = getSourceConfig(state, state.builder.source);
        const fields = sourceConfig && Array.isArray(sourceConfig.fields) ? sourceConfig.fields : [];
        const fieldsByName = {};
        fields.forEach(function(field) {
            fieldsByName[String(field.name || '')] = field;
        });

        state.builder.filters.forEach(function(filter, index) {
            const row = document.createElement('div');
            row.className = 'myvh-report-builder-row';

            const fieldSelect = document.createElement('select');
            fieldSelect.className = 'myvh-report-builder-select';
            state.builder.selectedFields.forEach(function(fieldName) {
                const config = fieldsByName[fieldName] || { label: fieldName };
                const option = document.createElement('option');
                option.value = fieldName;
                option.textContent = String(config.label || fieldName);
                fieldSelect.appendChild(option);
            });
            fieldSelect.value = String(filter.field || state.builder.selectedFields[0] || '');
            row.appendChild(fieldSelect);

            const operatorSelect = document.createElement('select');
            operatorSelect.className = 'myvh-report-builder-select';
            const selectedFieldConfig = fieldsByName[fieldSelect.value] || { operators: ['='] };
            const operators = Array.isArray(selectedFieldConfig.operators) ? selectedFieldConfig.operators : ['='];
            operators.forEach(function(operator) {
                const option = document.createElement('option');
                option.value = operator;
                option.textContent = operator;
                operatorSelect.appendChild(option);
            });
            operatorSelect.value = String(filter.operator || operators[0] || '=');
            row.appendChild(operatorSelect);

            const valueInput = document.createElement('input');
            valueInput.type = 'text';
            valueInput.className = 'myvh-report-builder-input';
            valueInput.value = String(filter.value || '');
            row.appendChild(valueInput);

            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'button button-small myvh-report-builder-remove-btn';
            removeButton.innerHTML = '<span class="dashicons dashicons-no-alt" aria-hidden="true"></span><span>Remove</span>';
            removeButton.addEventListener('click', function() {
                state.builder.filters.splice(index, 1);
                renderFilters(root, state);
            });
            row.appendChild(removeButton);

            const sync = function() {
                filter.field = fieldSelect.value;
                filter.operator = operatorSelect.value;
                filter.value = valueInput.value;
            };

            fieldSelect.addEventListener('change', function() {
                const nextFieldConfig = fieldsByName[fieldSelect.value] || { operators: ['='] };
                const nextOperators = Array.isArray(nextFieldConfig.operators) ? nextFieldConfig.operators : ['='];
                operatorSelect.innerHTML = '';
                nextOperators.forEach(function(operator) {
                    const option = document.createElement('option');
                    option.value = operator;
                    option.textContent = operator;
                    operatorSelect.appendChild(option);
                });
                operatorSelect.value = nextOperators[0] || '=';
                sync();
            });

            operatorSelect.addEventListener('change', sync);
            valueInput.addEventListener('input', sync);

            sync();
            filtersContainer.appendChild(row);
        });
    }

    function renderSort(root, state) {
        const sortContainer = root.querySelector('[data-builder-sort]');
        if (!sortContainer) {
            return;
        }

        sortContainer.innerHTML = '';

        state.builder.orderBy.forEach(function(sort, index) {
            const row = document.createElement('div');
            row.className = 'myvh-report-builder-row';

            const fieldSelect = document.createElement('select');
            fieldSelect.className = 'myvh-report-builder-select';
            state.builder.selectedFields.forEach(function(fieldName) {
                const option = document.createElement('option');
                option.value = fieldName;
                option.textContent = fieldName;
                fieldSelect.appendChild(option);
            });
            fieldSelect.value = String(sort.field || state.builder.selectedFields[0] || '');
            row.appendChild(fieldSelect);

            const directionSelect = document.createElement('select');
            directionSelect.className = 'myvh-report-builder-select';
            ['ASC', 'DESC'].forEach(function(direction) {
                const option = document.createElement('option');
                option.value = direction;
                option.textContent = direction;
                directionSelect.appendChild(option);
            });
            directionSelect.value = String(sort.direction || 'ASC').toUpperCase() === 'DESC' ? 'DESC' : 'ASC';
            row.appendChild(directionSelect);

            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'button button-small myvh-report-builder-remove-btn';
            removeButton.innerHTML = '<span class="dashicons dashicons-no-alt" aria-hidden="true"></span><span>Remove</span>';
            removeButton.addEventListener('click', function() {
                state.builder.orderBy.splice(index, 1);
                renderSort(root, state);
            });
            row.appendChild(removeButton);

            const sync = function() {
                sort.field = fieldSelect.value;
                sort.direction = directionSelect.value;
            };

            fieldSelect.addEventListener('change', sync);
            directionSelect.addEventListener('change', sync);

            sync();
            sortContainer.appendChild(row);
        });
    }

    function refreshReportSelectOptions(root, state) {
        const reportSelect = root.querySelector('[data-report-select]');
        if (!reportSelect) {
            return;
        }

        const current = Number(reportSelect.value || 0);
        const reports = Array.isArray(state.bootstrap.reports) ? state.bootstrap.reports : [];

        reportSelect.innerHTML = '';

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = 'Create a new report';
        reportSelect.appendChild(placeholder);

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

    function definitionFromState(state) {
        return {
            select: state.builder.selectedFields,
            group_by: Array.isArray(state.builder.groupBy) ? state.builder.groupBy.filter(function(field) {
                return String(field || '').trim() !== '';
            }) : [],
            aggregates: Array.isArray(state.builder.aggregates) ? state.builder.aggregates.filter(function(aggregate) {
                return aggregate && aggregate.field && aggregate.function;
            }).map(function(aggregate) {
                return {
                    field: String(aggregate.field || ''),
                    function: String(aggregate.function || 'SUM').toUpperCase(),
                    alias: String(aggregate.alias || '')
                };
            }) : [],
            filters: state.builder.filters.filter(function(filter) {
                return filter && filter.field && filter.operator;
            }).map(function(filter) {
                return {
                    field: String(filter.field || ''),
                    operator: String(filter.operator || '='),
                    value: String(filter.value || '')
                };
            }),
            order_by: state.builder.orderBy.filter(function(order) {
                return order && order.field;
            }).map(function(order) {
                return {
                    field: String(order.field || ''),
                    direction: String(order.direction || 'ASC').toUpperCase() === 'DESC' ? 'DESC' : 'ASC'
                };
            }),
            limit: Number(state.builder.limit || 1000)
        };
    }

    function setBuilderFromReport(state, report) {
        const definition = parseDefinition(report && report.query_json ? report.query_json : '');
        const source = String((report && report.data_source) || state.builder.source || '');

        state.builder.reportId = Number((report && report.id) || 0);
        state.builder.reportType = String((report && report.type) || 'user');
        state.builder.source = source;
        state.builder.name = String((report && report.name) || '');
        state.builder.description = String((report && report.description) || '');
        state.builder.selectedFields = Array.isArray(definition && definition.select) ? definition.select.slice() : [];
        state.builder.groupBy = Array.isArray(definition && definition.group_by) ? definition.group_by.slice() : [];
        state.builder.aggregates = Array.isArray(definition && definition.aggregates) ? definition.aggregates.slice() : [];
        state.builder.filters = Array.isArray(definition && definition.filters) ? definition.filters.slice() : [];
        state.builder.orderBy = Array.isArray(definition && definition.order_by) ? definition.order_by.slice() : [];
        state.builder.limit = Number((definition && definition.limit) || 1000);

        syncFieldSelections(state);
    }

    function bindEvents(root, state) {
        const sourceSelect = root.querySelector('[data-builder-source]');
        const nameInput = root.querySelector('[data-builder-name]');
        const addFilterButton = root.querySelector('[data-builder-add-filter]');
        const addSortButton = root.querySelector('[data-builder-add-sort]');
        const newButton = root.querySelector('[data-builder-new]');
        const deleteButton = root.querySelector('[data-builder-delete]');
        const saveButton = root.querySelector('[data-builder-save]');
        const status = root.querySelector('[data-builder-status]');
        const reportSelect = root.querySelector('[data-report-select]');

        if (sourceSelect) {
            sourceSelect.addEventListener('change', function() {
                state.builder.source = sourceSelect.value;
                syncFieldSelections(state);
                renderFieldLists(root, state);
                renderFilters(root, state);
                renderSort(root, state);
            });
        }

        if (nameInput) {
            nameInput.addEventListener('input', function() {
                state.builder.name = nameInput.value;
            });
        }

        if (addFilterButton) {
            addFilterButton.addEventListener('click', function() {
                const fallbackField = state.builder.selectedFields[0] || '';
                state.builder.filters.push({
                    field: fallbackField,
                    operator: '=',
                    value: ''
                });
                renderFilters(root, state);
            });
        }

        if (addSortButton) {
            addSortButton.addEventListener('click', function() {
                const fallbackField = state.builder.selectedFields[0] || '';
                state.builder.orderBy.push({
                    field: fallbackField,
                    direction: 'ASC'
                });
                renderSort(root, state);
            });
        }

        if (reportSelect) {
            reportSelect.addEventListener('change', function() {
                const reports = Array.isArray(state.bootstrap.reports) ? state.bootstrap.reports : [];
                const selectedId = Number(reportSelect.value || 0);
                if (!selectedId) {
                            resetBuilderToNewReport(root, state);
                    syncUiFromBuilder(root, state);
                    return;
                }

                const report = reports.find(function(item) {
                    return Number(item.id || 0) === selectedId;
                });

                if (!report) {
                    return;
                }

                setBuilderFromReport(state, report);
                syncUiFromBuilder(root, state);
            });
        }

        if (newButton) {
            newButton.addEventListener('click', function() {
                resetBuilderToNewReport(root, state);
                syncUiFromBuilder(root, state);
                if (status) {
                    status.textContent = 'Building a new report.';
                }
            });
        }

        if (deleteButton) {
            deleteButton.addEventListener('click', function() {
                const reportId = Number(state.builder.reportId || 0);
                if (!reportId) {
                    if (status) {
                        status.textContent = 'Select an existing report to delete.';
                    }
                    return;
                }

                if (reportId <= 0) {
                    if (status) {
                        status.textContent = 'Built-in system reports cannot be deleted.';
                    }
                    return;
                }

                const endpoints = state.bootstrap.endpoints || {};
                const deleteBase = String(endpoints.delete || '');
                if (!deleteBase) {
                    if (status) {
                        status.textContent = 'Delete endpoint is not configured.';
                    }
                    return;
                }

                if (!window.confirm('Delete this report? This cannot be undone.')) {
                    return;
                }

                deleteButton.disabled = true;
                if (status) {
                    status.textContent = 'Deleting report...';
                }

                fetch(deleteBase.replace(/\/$/, '') + '/' + String(reportId), {
                    method: 'DELETE',
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
                                payload: payload || {}
                            };
                        });
                    })
                    .then(function(result) {
                        if (!result.ok) {
                            throw new Error(String((result.payload && result.payload.message) || 'Unable to delete report.'));
                        }

                        const reports = Array.isArray(state.bootstrap.reports) ? state.bootstrap.reports : [];
                        state.bootstrap.reports = reports.filter(function(item) {
                            return Number(item.id || 0) !== reportId;
                        });

                        resetBuilderToNewReport(root, state);
                        refreshReportSelectOptions(root, state);
                        syncUiFromBuilder(root, state);

                        if (status) {
                            status.textContent = 'Report deleted.';
                        }

                        document.dispatchEvent(new CustomEvent('myvh:report-deleted', {
                            detail: {
                                reportId: reportId
                            }
                        }));
                    })
                    .catch(function(error) {
                        if (status) {
                            status.textContent = error && error.message ? error.message : 'Unable to delete report.';
                        }
                    })
                    .finally(function() {
                        deleteButton.disabled = false;
                    });
            });
        }

        if (saveButton) {
            saveButton.addEventListener('click', function() {
                if (!state.context || !state.context.permissions || !state.context.permissions.canCreateReports) {
                    if (status) {
                        status.textContent = 'You do not have permission to save reports.';
                    }
                    return;
                }

                const endpoints = state.bootstrap.endpoints || {};
                const saveUrl = String(endpoints.save || '');
                if (!saveUrl) {
                    if (status) {
                        status.textContent = 'Save endpoint is not configured.';
                    }
                    return;
                }

                const selectedReportType = state.builder.reportType === 'system' ? 'system' : 'user';
                const requestBody = {
                    id: Number(state.builder.reportId || 0),
                    name: String(state.builder.name || '').trim(),
                    description: String(state.builder.description || '').trim(),
                    type: selectedReportType,
                    data_source: String(state.builder.source || ''),
                    definition: definitionFromState(state)
                };

                if (requestBody.name === '' || !requestBody.data_source || requestBody.definition.select.length === 0) {
                    if (status) {
                        status.textContent = 'Select a data source, choose fields, and provide a report name.';
                    }
                    return;
                }

                saveButton.disabled = true;
                if (status) {
                    status.textContent = 'Saving report...';
                }

                fetch(saveUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': String(state.bootstrap.restNonce || '')
                    },
                    body: JSON.stringify(requestBody)
                })
                    .then(function(response) {
                        return response.json().then(function(payload) {
                            return {
                                ok: response.ok,
                                payload: payload || {}
                            };
                        });
                    })
                    .then(function(result) {
                        if (!result.ok) {
                            throw new Error(String((result.payload && result.payload.message) || 'Unable to save report.'));
                        }

                        const report = result.payload && result.payload.report ? result.payload.report : null;
                        if (report && typeof report === 'object') {
                            const reports = Array.isArray(state.bootstrap.reports) ? state.bootstrap.reports : [];
                            const id = Number(report.id || 0);
                            const idx = reports.findIndex(function(item) {
                                return Number(item.id || 0) === id;
                            });

                            if (idx >= 0) {
                                reports[idx] = report;
                            } else {
                                reports.push(report);
                            }

                            state.bootstrap.reports = reports;
                            state.builder.reportId = id;

                            document.dispatchEvent(new CustomEvent('myvh:report-saved', {
                                detail: {
                                    report: report
                                }
                            }));
                        }

                        if (status) {
                            status.textContent = 'Report saved.';
                        }
                    })
                    .catch(function(error) {
                        if (status) {
                            status.textContent = error && error.message ? error.message : 'Unable to save report.';
                        }
                    })
                    .finally(function() {
                        saveButton.disabled = false;
                    });
            });
        }
    }

    function syncUiFromBuilder(root, state) {
        const sourceSelect = root.querySelector('[data-builder-source]');
        const nameInput = root.querySelector('[data-builder-name]');
        const reportSelect = root.querySelector('[data-report-select]');

        if (sourceSelect) {
            sourceSelect.value = state.builder.source;
        }

        if (reportSelect) {
            reportSelect.value = state.builder.reportId > 0 ? String(state.builder.reportId) : '';
        }

        if (nameInput) {
            nameInput.value = state.builder.name;
        }

        updateDeleteButtonState(root, state);

        renderFieldLists(root, state);
        renderFilters(root, state);
        renderSort(root, state);
    }

    function initRoot(root) {
        if (!root || root.dataset.reportBuilderInitialized === '1') {
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

        initializeNewBuilderState(state);

        renderSourceOptions(root, state);

        if (!state.builder.source) {
            const sources = getSources(state);
            if (sources.length > 0) {
                state.builder.source = String(sources[0].source || '');
            }
        }

        syncFieldSelections(state);
        syncUiFromBuilder(root, state);
        bindEvents(root, state);

        root.dataset.reportBuilderInitialized = '1';
    }

    function init() {
        document.querySelectorAll('.myvh-report-app').forEach(initRoot);
    }

    return {
        init: init
    };
})();
