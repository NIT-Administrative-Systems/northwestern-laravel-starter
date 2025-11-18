export default {
    initTooltips: function () {
        // Fixes BS tooltip content not updating when the data-bs-title attribute is updated. Probably fixed in BS 5.3,
        // at which point this can be removed? <https://github.com/twbs/bootstrap/issues/36432>
        const tooltipConfig = {
            popperConfig: function (defaultBsPopperConfig) {
                defaultBsPopperConfig.modifiers.push({
                    name: "FixDynamicTitles",
                    enabled: true,
                    phase: "beforeRead",
                    fn({ state }) {
                        // Fix for dynamic titles using `data-bs-title`.
                        let title = state.elements.reference.dataset.bsTitle;
                        if (title) {
                            state.elements.popper.querySelector(
                                ".tooltip-inner",
                            ).innerHTML = title;
                        }
                    },
                });

                return defaultBsPopperConfig;
            },
        };

        const initializeTooltips = () => {
            const tooltipElements = [
                ...document.querySelectorAll('[data-bs-toggle="tooltip"]'),
            ];

            tooltipElements.forEach((tip) => {
                const tooltipInstance = bootstrap.Tooltip.getInstance(tip);
                if (!tooltipInstance) {
                    new bootstrap.Tooltip(tip, tooltipConfig);
                }
            });
        };

        if (typeof window !== "undefined") {
            initializeTooltips();
        }

        if (typeof window !== "undefined" && window.Livewire) {
            Livewire.hook("commit", ({ succeed }) => {
                succeed(() => {
                    queueMicrotask(() => {
                        setTimeout(() => initializeTooltips(), 100);
                    });
                });
            });
        }
    },
};
