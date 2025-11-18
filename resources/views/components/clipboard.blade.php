@props([
    'text' => '',
    'label' => null,
    'isButton' => true,
    'buttonSize' => null,
    'buttonVariant' => 'outline-secondary',
    'successVariant' => 'outline-success',
    'iconPosition' => 'left',
])

@php
    $buttonClasses = ['btn', 'clipboard-btn'];
    $successButtonClasses = ['btn', 'clipboard-btn', 'clipboard-btn-success'];

    if ($buttonSize) {
        $buttonClasses[] = "btn-$buttonSize";
        $successButtonClasses[] = "btn-$buttonSize";
    }

    $buttonClasses[] = "btn-$buttonVariant";
    $successButtonClasses[] = "btn-$successVariant";

    $nonButtonClasses = ['clipboard-trigger', 'cursor-pointer'];

    $buttonClassString = implode(' ', $buttonClasses);
    $successButtonClassString = implode(' ', $successButtonClasses);
    $nonButtonClassString = implode(' ', $nonButtonClasses);

    $computedAriaLabel = $label ? "Copy $label to clipboard" : 'Copy to clipboard';
@endphp

<div class="clipboard-component"
     x-data="clipboardComponent(@js($text))"
     x-cloak>
    @if ($isButton)
        <button type="button"
                aria-label="{{ $computedAriaLabel }}"
                x-ref="trigger"
                :class="copied ? '{{ $successButtonClassString }}' : '{{ $buttonClassString }}'"
                {{ $attributes }}
                @click.debounce.1200ms>
        @else
            <span role="button"
                  aria-label="{{ $computedAriaLabel }}"
                  tabindex="0"
                  x-ref="trigger"
                  {{ $attributes->merge(['class' => $nonButtonClassString]) }}
                  :class="copied ? 'text-success' : 'text-secondary'"
                  {{ $attributes }}
                  @click.debounce.1200ms>
    @endif
    <span class="d-inline-flex align-items-center">
        {{-- Icon on the left --}}
        @if ($iconPosition === 'left')
            <div class="position-relative d-inline-flex">
                <i class="fas fa-fw fa-copy copy-icon"
                   aria-hidden="true"
                   :class="{ 'copy-icon-hide': copied }"></i>
                <i class="fas fa-fw fa-check copy-icon copy-icon-check position-absolute"
                   aria-hidden="true"
                   style="{{ $iconPosition === 'left' ? 'left: 0; right: auto;' : 'right: 0; left: auto;' }}"
                   :class="{ 'copy-icon-show': copied }"></i>
            </div>
        @endif

        {{-- Label --}}
        @if ($label)
            <span
                  class="clipboard-label @unless ($isButton) text-dark @endunless {{ $iconPosition === 'left' ? 'ms-1' : ($iconPosition === 'right' ? 'me-1' : '') }}">
                {{ $label }}
            </span>
        @endif

        {{-- Icon on the right --}}
        @if ($iconPosition === 'right')
            <div class="position-relative d-inline-flex">
                <i class="fas fa-fw fa-copy copy-icon"
                   aria-hidden="true"
                   :class="{ 'copy-icon-hide': copied }"></i>
                <i class="fas fa-fw fa-check copy-icon copy-icon-check position-absolute"
                   aria-hidden="true"
                   style="{{ $iconPosition === 'left' ? 'left: 0; right: auto;' : 'right: 0; left: auto;' }}"
                   :class="{ 'copy-icon-show': copied }"></i>
            </div>
        @endif
    </span>
    @if ($isButton)
        </button>
    @else
        </span>
    @endif
</div>

@once
    <script lang="text/javascript">
        function clipboardComponent(textToCopy) {
            return {
                text: textToCopy,
                copied: false,
                tooltip: null,
                clipboard: null,
                tooltipTimer: null,

                init() {
                    if (!window.ClipboardJS) {
                        console.error('ClipboardJS not found. Make sure it is loaded before this component.');
                        return;
                    }

                    this.initClipboard();
                    this.initTooltip();

                    this.$cleanup = () => {
                        if (this.clipboard) {
                            this.clipboard.destroy();
                        }

                        if (this.tooltip) {
                            this.tooltip.dispose();
                        }

                        if (this.tooltipTimer) {
                            clearTimeout(this.tooltipTimer);
                        }

                        this.$refs.trigger.removeEventListener('mouseleave', () => {
                            if (this.tooltip) {
                                this.tooltip.hide();
                            }
                        });
                    };
                },

                initClipboard() {
                    this.clipboard = new ClipboardJS(this.$refs.trigger, {
                        text: () => this.text,
                    });

                    this.clipboard.on('success', () => {
                        this.copied = true;

                        if (this.tooltipTimer) {
                            clearTimeout(this.tooltipTimer);
                        }

                        if (this.tooltip) {
                            this.showSuccessTooltip('Copied');
                        }

                        this.tooltipTimer = setTimeout(() => {
                            this.copied = false;

                            if (this.tooltip) {
                                this.resetTooltip();
                            }

                            this.tooltipTimer = null;
                        }, 1200);

                        this.$dispatch('clipboard-success', {
                            text: this.text
                        });
                    });

                    this.clipboard.on('error', (e) => {
                        if (this.tooltip) {
                            this.showSuccessTooltip('Failed to copy');
                        }

                        if (this.tooltipTimer) {
                            clearTimeout(this.tooltipTimer);
                        }

                        this.tooltipTimer = setTimeout(() => {
                            this.resetTooltip();
                            this.tooltipTimer = null;
                        }, 1000);

                        console.error('Clipboard error:', e);
                        this.$dispatch('clipboard-error', {
                            error: e
                        });
                    });
                },

                initTooltip() {
                    if (window.bootstrap && window.bootstrap.Tooltip) {
                        this.$refs.trigger.setAttribute('data-bs-title', 'Copy to clipboard');

                        try {
                            this.tooltip = new bootstrap.Tooltip(this.$refs.trigger, {
                                trigger: 'hover focus'
                            });
                        } catch (e) {
                            console.error('Error initializing tooltip:', e);
                        }
                    }
                },

                showSuccessTooltip(title) {
                    try {
                        if (this.tooltip) {
                            this.tooltip.dispose();
                        }

                        this.$refs.trigger.setAttribute('data-bs-title', title);

                        this.tooltip = new bootstrap.Tooltip(this.$refs.trigger, {
                            trigger: 'manual'
                        });

                        this.tooltip.show();
                    } catch (e) {
                        console.warn('Error showing tooltip:', e);
                    }
                },

                resetTooltip() {
                    try {
                        if (this.tooltip) {
                            this.tooltip.dispose();
                        }

                        this.$refs.trigger.setAttribute('data-bs-title', 'Copy to clipboard');

                        this.tooltip = new bootstrap.Tooltip(this.$refs.trigger, {
                            trigger: 'hover focus'
                        });
                    } catch (e) {
                        console.warn('Error resetting tooltip:', e);
                    }
                }
            };
        }
    </script>

    <style>
        .clipboard-trigger {
            display: inline-flex;
            align-items: center;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .clipboard-component button {
            transition: all 0.3s ease;
        }

        .clipboard-btn:active {
            transform: scale(0.95);
        }

        .copy-icon {
            opacity: 1;
            transform: scale(1);
            transition: all 0.3s ease;
        }

        .copy-icon-hide {
            opacity: 0;
            transform: scale(0.5);
        }

        .copy-icon-check {
            opacity: 0;
            transform: scale(0.5);
            transition: all 0.3s ease;
        }

        .copy-icon-show {
            opacity: 1;
            transform: scale(1);
        }
    </style>
@endonce
