export function callOffSubmission(matrixUrl, reviewUrl, storeUrl) {
    return {
        matrixUrl,
        reviewUrl,
        storeUrl,
        selectedServices: [],
        selectedPlots: [],
        rows: [],
        includedKeys: [],
        reasons: {},
        message: '',
        previewSignature: '',
        loading: false,
        confirming: false,
        error: '',
        modalError: '',

        get selectedRows() {
            return this.rows.filter((row) => row.available && this.includedKeys.includes(row.key));
        },

        get availableRows() {
            return this.rows.filter((row) => row.available);
        },

        get unavailableRows() {
            return this.rows.filter((row) => !row.available);
        },

        get readyToConfirm() {
            return this.selectedRows.length > 0
                && this.selectedRows.every((row) => !row.is_early_exception || (this.reasons[row.key] || '').trim().length > 0);
        },

        headers() {
            return {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': this.$refs.selectionForm.querySelector('input[name="_token"]').value,
            };
        },

        async post(url, body) {
            const response = await fetch(url, {
                method: 'POST',
                headers: this.headers(),
                credentials: 'same-origin',
                body,
            });
            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                const errors = Object.values(payload.errors || {}).flat();
                throw new Error(errors[0] || (response.status === 403
                    ? 'Your site access changed. Refresh this page before trying again.'
                    : 'The call-off could not be checked. Refresh the page and try again.'));
            }

            return payload;
        },

        async openConfirmation() {
            if (this.loading || this.confirming) return;
            this.error = '';
            this.loading = true;

            try {
                const form = new FormData(this.$refs.selectionForm);
                const result = await this.post(this.matrixUrl, form);
                this.rows = result.rows || [];
                this.includedKeys = this.availableRows.map((row) => row.key);
                this.previewSignature = result.preview_signature;
                this.message = form.get('customer_response') || '';
                this.reasons = {};
                const cavityReason = (form.get('cavity_early_reason') || '').trim();
                for (const row of this.rows) {
                    if (row.is_early_exception && row.service === 'cavity_closers') this.reasons[row.key] = cavityReason;
                }

                if (this.availableRows.length === 0) {
                    this.error = 'No selected plot and service combinations are available. Change your selection and try again.';
                    return;
                }

                this.modalError = '';
                this.$refs.confirmation.showModal();
                this.$nextTick(() => this.$refs.confirmationTitle.focus());
            } catch (error) {
                this.error = error.message || 'The call-off could not be checked. Try again.';
            } finally {
                this.loading = false;
            }
        },

        closeConfirmation() {
            if (this.confirming) return;
            this.$refs.confirmation.close();
        },

        restoreFocus() {
            if (!this.confirming) this.$nextTick(() => this.$refs.submitButton.focus());
        },

        async confirm() {
            if (this.confirming || !this.readyToConfirm) return;
            this.confirming = true;
            this.modalError = '';

            try {
                const review = new FormData(this.$refs.selectionForm);
                review.set('preview_signature', this.previewSignature);
                for (const row of this.rows) {
                    if (!this.includedKeys.includes(row.key)) review.append('excluded[]', row.key);
                    if (row.is_early_exception && this.includedKeys.includes(row.key)) {
                        review.append(`early_reasons[${row.key}]`, this.reasons[row.key] || '');
                    }
                }

                const prepared = await this.post(this.reviewUrl, review);
                const submission = new FormData();
                submission.set('confirmation_signature', prepared.confirmation_signature);
                const result = await this.post(this.storeUrl, submission);
                window.location.assign(result.redirect);
            } catch (error) {
                this.modalError = error.message || 'The call-off was not submitted. Close this window and press Submit to try again.';
                this.confirming = false;
                this.$nextTick(() => this.$refs.modalError.focus());
            }
        },
    };
}
