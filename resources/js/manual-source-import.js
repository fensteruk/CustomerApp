const PARTIAL_SCOPE = 'PARTIAL_FILTERED_EXPORT';
const SITE_SCOPE = 'SITE_COMPLETE_SNAPSHOT';

const CATEGORY_LABELS = {
    NEW: 'New',
    UNCHANGED: 'Unchanged',
    UPDATED: 'Updated',
    COMPLETED: 'Completed',
    COMPLETION_REVERSED: 'Completion reversed',
    MISSING_FROM_SOURCE: 'Missing from source',
    SITE_MAPPING_REQUIRED: 'Mapping required',
    UNKNOWN_CALL_TYPE: 'Unknown call type',
    INVALID: 'Invalid',
    RECONCILIATION_REQUIRED: 'Reconciliation needed',
};

const ROLE_LABELS = {
    CALL_NUMBER: 'Call Number',
    SITE_NAME: 'Site',
    SITE_EXTERNAL_ID: 'Permanent Site ID',
    PLOT_REFERENCE: 'Plot',
    CALL_TYPE: 'Call Type',
    COMPLETION_FLAG: 'Completion',
    COMPLETED_DATE: 'Completed Date',
    OPERATIONAL_TARGET_DATE: 'PC1 arrival / installation date',
    PRODUCT_QUANTITY: 'Product quantity',
    COMMERCIAL_VALUE: 'Ignored commercial value',
    IGNORE: 'Ignored',
    UNKNOWN: 'Unknown',
};

const ROLE_OPTIONS = Object.entries(ROLE_LABELS).map(([value, label]) => ({ value, label }));

const PRODUCT_CODES = [
    'VS', 'TT', 'BAY', 'ALI', 'AOV', 'FI',
    'PSU', 'PSG', 'CDF', 'CDU', 'CDG', 'PSP', 'BF',
    'CAS', 'FLU', 'PFD', 'GLS', 'WP', 'MISC',
];

export default function manualSourceImport(configuration) {
    return {
        configuration,
        step: 1,
        busy: false,
        bindingsLoading: true,
        error: '',
        successMessage: '',
        file: null,
        fileError: '',
        scope: PARTIAL_SCOPE,
        confirmScope: false,
        completeSiteIdentifiers: [],
        preview: null,
        mapping: { sheet: '', header_row: 1, columns: [] },
        bindings: [],
        bindingSelections: {},
        rowFilter: 'changed',
        result: null,
        resultTab: 'summary',
        importConfirmed: false,
        roleOptions: ROLE_OPTIONS,
        productCodes: PRODUCT_CODES,

        async init() {
            await this.loadBindings();
        },

        csrfHeaders(json = true) {
            const headers = {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            };

            if (json) headers['Content-Type'] = 'application/json';

            return headers;
        },

        async request(url, options = {}) {
            const response = await fetch(url, options);
            let payload = {};
            const contentType = response.headers.get('content-type') ?? '';

            try {
                if (contentType.includes('application/json')) {
                    payload = await response.json();
                }
            } catch {
                payload = {};
            }

            if (!response.ok || response.redirected || !contentType.includes('application/json')) {
                const validation = Object.values(payload.errors ?? {}).flat().filter(Boolean);
                const firstValidation = validation[0];
                const message = (typeof firstValidation === 'object' ? firstValidation?.message : firstValidation)
                    ?? payload.message
                    ?? (response.redirected
                        ? 'Your session or upload could not be verified. Refresh the page and try again.'
                        : 'The server returned an unexpected response. Please try again.');
                throw new Error(message);
            }

            return payload;
        },

        clearMessages() {
            this.error = '';
            this.successMessage = '';
        },

        showError(error) {
            this.error = error instanceof Error ? error.message : 'Something went wrong. Please try again.';
            this.$nextTick(() => this.$refs.errorSummary?.focus());
        },

        announce(message) {
            this.successMessage = message;
            this.$nextTick(() => this.$refs.statusMessage?.focus());
        },

        async loadBindings() {
            this.bindingsLoading = true;

            try {
                const payload = await this.request(this.configuration.endpoints.bindings, {
                    headers: this.csrfHeaders(false),
                });
                this.bindings = payload.data ?? [];
            } catch (error) {
                this.showError(error);
            } finally {
                this.bindingsLoading = false;
            }
        },

        chooseFile(event) {
            this.file = event.target.files?.[0] ?? null;
            this.fileError = '';

            if (!this.file) return;

            if (!this.file.name.toLowerCase().endsWith('.xlsx')) {
                this.fileError = 'Choose an Excel workbook ending in .xlsx.';
                return;
            }

            if (this.file.size > this.configuration.maxUploadBytes) {
                this.fileError = 'This workbook is larger than the 10 MB upload limit.';
            }
        },

        scopeReady() {
            if (this.scope === PARTIAL_SCOPE) return true;
            if (!this.confirmScope) return false;

            return this.scope !== SITE_SCOPE || this.completeSiteIdentifiers.length > 0;
        },

        async upload() {
            this.clearMessages();

            if (!this.file || this.fileError) {
                this.fileError ||= 'Choose an XLSX spreadsheet to continue.';
                this.$nextTick(() => this.$refs.workbook?.focus());
                return;
            }

            if (!this.scopeReady()) {
                this.showError(new Error('Confirm the complete-export scope before continuing.'));
                return;
            }

            this.busy = true;
            const body = new FormData();
            body.append('workbook', this.file);
            body.append('import_scope', this.scope);

            if (this.scope !== PARTIAL_SCOPE) body.append('confirm_scope', '1');
            this.completeSiteIdentifiers.forEach((key) => body.append('complete_site_identifiers[]', key));

            try {
                this.preview = await this.request(this.configuration.endpoints.preview, {
                    method: 'POST',
                    headers: this.csrfHeaders(false),
                    body,
                });
                this.prepareMapping();
                this.step = 2;
                this.announce('Spreadsheet analysed. Review the detected structure.');
            } catch (error) {
                this.showError(error);
            } finally {
                this.busy = false;
            }
        },

        get interpretation() {
            return this.preview?.workbook_interpretation ?? null;
        },

        get selectedSheet() {
            const sheets = this.interpretation?.sheets ?? [];
            return sheets.find((sheet) => sheet.sheet === this.mapping.sheet)
                ?? sheets.find((sheet) => sheet.sheet === this.interpretation?.selected_sheet)
                ?? sheets.find((sheet) => sheet.visible)
                ?? sheets[0]
                ?? null;
        },

        prepareMapping() {
            if (!this.interpretation) return;

            const suggestion = this.interpretation.profile_match?.suggested_mappings;
            const selectedName = suggestion?.sheet ?? this.interpretation.selected_sheet
                ?? this.interpretation.sheets?.find((sheet) => sheet.visible)?.sheet
                ?? this.interpretation.sheets?.[0]?.sheet
                ?? '';
            const sheet = this.interpretation.sheets?.find((item) => item.sheet === selectedName);
            const suggestions = new Map((suggestion?.columns ?? []).map((column) => [column.source_index, column]));

            this.mapping = {
                sheet: selectedName,
                header_row: suggestion?.header_row ?? sheet?.header_row ?? this.interpretation.header_row ?? 1,
                columns: (sheet?.columns ?? []).map((column) => {
                    const proposed = suggestions.get(column.source_index);
                    return {
                        ...column,
                        semantic_role: proposed?.semantic_role ?? column.semantic_role,
                        subtype: proposed?.subtype ?? column.subtype,
                    };
                }),
            };
        },

        changeSheet() {
            const sheet = this.interpretation?.sheets?.find((item) => item.sheet === this.mapping.sheet);
            this.mapping.header_row = sheet?.header_row ?? 1;
            this.mapping.columns = (sheet?.columns ?? []).map((column) => ({ ...column }));
        },

        mappingStatus(column) {
            if (column.semantic_role === 'IGNORE') return 'Ignored';
            if (column.semantic_role === 'UNKNOWN') return 'Unknown';
            if (column.confirmation_needed) return 'Needs confirmation';
            return 'Confirmed';
        },

        confidenceLabel(score) {
            if (score >= 95) return 'High confidence';
            if (score >= 75) return 'Review';
            return 'Low confidence';
        },

        confidenceStatusClass(score) {
            if (score >= 95) return 'status-green';
            if (score >= 75) return 'status-amber';
            return 'status-red';
        },

        roleLabel(role) {
            return ROLE_LABELS[role] ?? role ?? 'Unknown';
        },

        categoryLabel(category) {
            return CATEGORY_LABELS[category] ?? category ?? 'Unknown';
        },

        categoryTone(category) {
            if (['NEW', 'COMPLETED'].includes(category)) return 'emerald';
            if (['UPDATED', 'COMPLETION_REVERSED', 'MISSING_FROM_SOURCE', 'RECONCILIATION_REQUIRED'].includes(category)) return 'amber';
            if (['INVALID', 'UNKNOWN_CALL_TYPE', 'SITE_MAPPING_REQUIRED'].includes(category)) return 'rose';
            return 'slate';
        },

        mappingIsComplete() {
            const roles = this.mapping.columns.map((column) => column.semantic_role);
            const critical = ['CALL_NUMBER', 'SITE_NAME', 'PLOT_REFERENCE', 'CALL_TYPE'];

            return critical.every((role) => roles.filter((value) => value === role).length === 1)
                && !roles.includes('UNKNOWN')
                && this.mapping.columns.every((column) => column.semantic_role !== 'PRODUCT_QUANTITY' || column.subtype);
        },

        goToMapping() {
            this.clearMessages();
            this.step = 3;
            this.announce('Review and confirm the detected column mappings.');
        },

        async confirmMapping(nextStep = null) {
            this.clearMessages();

            if (!this.mappingIsComplete()) {
                this.showError(new Error('Confirm each required column and mark unknown columns as ignored or map them safely.'));
                return;
            }

            this.busy = true;

            try {
                const url = this.configuration.endpoints.interpretation.replace('__PREVIEW__', this.preview.preview_uuid);
                this.preview = await this.request(url, {
                    method: 'POST',
                    headers: this.csrfHeaders(),
                    body: JSON.stringify({
                        sheet: this.mapping.sheet,
                        header_row: Number(this.mapping.header_row),
                        columns: this.mapping.columns.map((column) => ({
                            source_index: Number(column.source_index),
                            semantic_role: column.semantic_role,
                            subtype: column.semantic_role === 'PRODUCT_QUANTITY' ? column.subtype : null,
                        })),
                        confirm: true,
                    }),
                });
                this.prepareMapping();
                await this.loadBindings();
                const destination = nextStep ?? 4;
                this.step = destination;
                this.announce(destination === 4 ? 'Column mappings saved. Map the detected source sites.' : 'Mappings saved. Review the dry-run preview.');
            } catch (error) {
                this.showError(error);
            } finally {
                this.busy = false;
            }
        },

        get detectedSourceSites() {
            const sites = new Map();
            (this.preview?.rows ?? []).forEach((row) => {
                if (row.source_site_key) {
                    sites.set(row.source_site_key, row.source_site_name || row.source_site_key);
                }
            });

            return Array.from(sites, ([key, name]) => ({ key, name }));
        },

        get unmappedSourceSites() {
            const sites = new Map();
            (this.preview?.rows ?? []).forEach((row) => {
                if (row.source_site_key && !row.mapped_portal_site) {
                    sites.set(row.source_site_key, {
                        key: row.source_site_key,
                        name: row.source_site_name || row.source_site_key,
                    });
                }
            });

            return Array.from(sites.values());
        },

        get mappedSourceSites() {
            const sites = new Map();
            (this.preview?.rows ?? []).forEach((row) => {
                if (row.source_site_key && row.mapped_portal_site) {
                    sites.set(row.source_site_key, {
                        key: row.source_site_key,
                        name: row.source_site_name || row.source_site_key,
                        portalSite: row.mapped_portal_site,
                    });
                }
            });

            return Array.from(sites.values());
        },

        async saveSiteMappings() {
            this.clearMessages();
            const missing = this.unmappedSourceSites.find((site) => !this.bindingSelections[site.key]);

            if (missing) {
                this.showError(new Error(`Choose a Portal site for ${missing.name}.`));
                return;
            }

            this.busy = true;

            try {
                for (const sourceSite of this.unmappedSourceSites) {
                    await this.request(this.configuration.endpoints.bindings, {
                        method: 'POST',
                        headers: this.csrfHeaders(),
                        body: JSON.stringify({
                            source_namespace: 'siteapp-xlsx',
                            source_site_key: sourceSite.key,
                            original_name: sourceSite.name,
                            portal_site_uuid: this.bindingSelections[sourceSite.key],
                        }),
                    });
                }

                await this.loadBindings();
                await this.confirmMapping(5);
            } catch (error) {
                this.showError(error);
                this.busy = false;
            }
        },

        get filteredRows() {
            const rows = this.preview?.rows ?? [];

            return rows.filter((row) => {
                if (this.rowFilter === 'all') return true;
                if (this.rowFilter === 'changed') return row.diff_category !== 'UNCHANGED';
                if (this.rowFilter === 'errors') return (row.errors ?? []).length > 0;
                if (this.rowFilter === 'warnings') return (row.warnings ?? []).length > 0;
                if (this.rowFilter === 'mapping') return row.diff_category === 'SITE_MAPPING_REQUIRED';
                if (this.rowFilter === 'completed') return row.diff_category === 'COMPLETED';
                if (this.rowFilter === 'reversed') return row.diff_category === 'COMPLETION_REVERSED';

                return true;
            });
        },

        summaryCount(category) {
            return Number(this.preview?.summary?.[category] ?? 0);
        },

        resultCategoryCount(category) {
            return (this.result?.rows ?? []).filter((row) => row.diff_category === category).length;
        },

        get warningCount() {
            return (this.preview?.rows ?? []).reduce((total, row) => total + (row.warnings?.length ?? 0), 0);
        },

        get rowErrorCount() {
            return (this.preview?.rows ?? []).reduce((total, row) => total + (row.errors?.length ?? 0), 0);
        },

        stateSummary(state) {
            if (!state) return 'No current source record';
            const parts = [state.completed ? 'Completed' : 'Not completed'];
            if (state.completed_date) parts.push(`date ${this.formatDate(state.completed_date)}`);
            if (state.operational_target_date) parts.push(`PC1 target ${this.formatDate(state.operational_target_date)}`);
            if (state.job_stage) parts.push(`stage ${state.job_stage}`);
            return parts.join(' · ');
        },

        positiveProducts(products) {
            return Object.entries(products ?? {})
                .filter(([, quantity]) => Number(quantity) > 0)
                .map(([code, quantity]) => `${code} ${Number(quantity).toLocaleString('en-GB')}`)
                .join(', ') || 'No positive quantities';
        },

        rowMessages(row) {
            return [...(row.errors ?? []), ...(row.warnings ?? [])];
        },

        formatDate(value) {
            if (!value) return 'Not supplied';
            const date = new Date(`${value}T00:00:00`);
            return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
        },

        formatDateTime(value) {
            if (!value) return 'Not supplied';
            const date = new Date(value);
            return Number.isNaN(date.getTime()) ? value : date.toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' });
        },

        goToConfirmation() {
            this.clearMessages();
            this.importConfirmed = false;
            this.step = 6;
            this.announce('Review the final confirmation before importing.');
        },

        async commit() {
            this.clearMessages();
            if (!this.preview?.can_commit || !this.importConfirmed) return;
            this.busy = true;

            try {
                const url = this.configuration.endpoints.commit.replace('__PREVIEW__', this.preview.preview_uuid);
                this.result = await this.request(url, {
                    method: 'POST',
                    headers: this.csrfHeaders(),
                    body: JSON.stringify({
                        confirm: true,
                        content_sha256: this.preview.metadata.sha256,
                    }),
                });
                this.step = 7;
                this.resultTab = 'summary';
                this.announce('Import finished. Review the results and reconciliation items.');
            } catch (error) {
                this.showError(error);
            } finally {
                this.busy = false;
            }
        },

        cancelPreview() {
            this.reset();
            this.successMessage = 'Preview cancelled. Nothing was imported.';
            this.announce(this.successMessage);
        },

        reset() {
            this.step = 1;
            this.error = '';
            this.successMessage = '';
            this.file = null;
            this.fileError = '';
            this.scope = PARTIAL_SCOPE;
            this.confirmScope = false;
            this.completeSiteIdentifiers = [];
            this.preview = null;
            this.mapping = { sheet: '', header_row: 1, columns: [] };
            this.bindingSelections = {};
            this.rowFilter = 'changed';
            this.result = null;
            this.resultTab = 'summary';
            this.importConfirmed = false;
            this.$nextTick(() => {
                if (this.$refs.workbook) this.$refs.workbook.value = '';
                this.$refs.pageTitle?.focus();
            });
        },

    };
}
