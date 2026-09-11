export function importStudioDemo(totalSteps = 12) {
    return {
        step: 1,
        totalSteps,

        goTo(value) {
            const requested = Number(value);
            this.step = Math.min(this.totalSteps, Math.max(1, Number.isFinite(requested) ? requested : 1));
            this.focusStep();
        },

        next() {
            this.goTo(this.step + 1);
        },

        previous() {
            this.goTo(this.step - 1);
        },

        focusStep() {
            this.$nextTick(() => document.getElementById(`import-demo-step-${this.step}`)?.focus());
        },
    };
}
