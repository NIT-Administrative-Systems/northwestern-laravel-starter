@props([
    'length' => 6,
    'separator' => 3,
    'private' => false,
    'numeric' => true,
    'autofocus' => false,
])

<div class="d-flex align-items-center otp-wrapper gap-2"
     role="group"
     aria-label="Verification Code Input"
     wire:ignore.self
     x-data="otpInput({
         length: {{ $length }},
         numeric: {{ $numeric ? 'true' : 'false' }},
         autofocus: {{ $autofocus ? 'true' : 'false' }}
     })"
     x-modelable="value"
     {{ $attributes->whereStartsWith('wire:model') }}>

    <input name="{{ $attributes->get('name') }}"
           type="hidden"
           x-model="value">

    <template x-for="(char, index) in length" :key="index">
        <div class="d-flex align-items-center">
            <template x-if="shouldShowSeparator(index)">
                <span class="otp-separator text-muted" aria-hidden="true">&mdash;</span>
            </template>

            <div class="otp-item">
                <input class="form-control otp-input bg-white p-0 text-center"
                       data-1p-ignore
                       data-lpignore="true"
                       data-form-type="other"
                       type="{{ $private ? 'password' : 'text' }}"
                       inputmode="{{ $numeric ? 'numeric' : 'text' }}"
                       pattern="{{ $numeric ? '[0-9]*' : null }}"
                       autocomplete="one-time-code"
                       :aria-label="`Digit ${index + 1}`"
                       x-model="digits[index]"
                       @input="handleInput($event, index)"
                       @keydown="handleKeyDown($event, index)"
                       @paste="handlePaste($event)"
                       @focus="$event.target.select()"
                       @click="$event.target.select()">
            </div>
        </div>
    </template>
</div>

@once
    <style>
        .otp-input {
            width: 4rem;
            height: 5.5rem;
            font-size: 3.125rem;
            font-weight: 500;
            line-height: 3.5rem;
            padding: 0;
        }

        .otp-separator {
            font-weight: 500;
            user-select: none;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 0.75rem;
            margin-right: 0.5rem;
        }

        .otp-item {
            position: relative;
        }

        .otp-input::-webkit-outer-spin-button,
        .otp-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .otp-input[type=number] {
            -moz-appearance: textfield;
        }
    </style>

    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('otpInput', ({
                    length,
                    numeric,
                    autofocus
                }) => ({
                    length: length,
                    digits: Array(length).fill(''),
                    value: '',
                    numeric: numeric,

                    init() {
                        this.$watch('value', (val) => {
                            if (val && val.length === this.length) {
                                this.digits = val.split('');
                            } else if (!val) {
                                this.digits = Array(this.length).fill('');
                            }
                        });

                        this.$watch('digits', (val) => {
                            this.value = val.join('');
                            if (this.value.length === this.length) {
                                this.$dispatch('otp-completed', this.value);
                            }
                        });

                        if (autofocus) {
                            this.$nextTick(() => this.inputs()[0]?.focus());
                        }
                    },

                    inputs() {
                        return this.$root.querySelectorAll('.otp-input');
                    },

                    shouldShowSeparator(index) {
                        return index > 0 && {{ $separator ?: 0 }} > 0 && index % {{ $separator ?: 0 }} ===
                        0;
                    },

                    handleInput(e, index) {
                        let val = e.target.value;
                        if (this.numeric) val = val.replace(/\D/g, '');

                        if (val.length > 2) {
                            const chars = val.split('').slice(0, this.length);
                            this.fillValues(chars);
                            return;
                        }

                        if (val.length > 1) val = val.slice(-1);
                        this.digits[index] = val;

                        if (val && index < this.length - 1) {
                            this.focusNext(index);
                        }
                    },

                    handleKeyDown(e, index) {
                        const key = e.key;
                        if (key === 'Backspace') {
                            if (!this.digits[index] && index > 0) {
                                e.preventDefault();
                                this.focusPrev(index);
                            } else {
                                this.digits[index] = '';
                            }
                        } else if (key === 'Delete') {
                            this.digits[index] = '';
                        } else if (key === 'ArrowLeft') {
                            e.preventDefault();
                            this.focusPrev(index);
                        } else if (key === 'ArrowRight') {
                            e.preventDefault();
                            this.focusNext(index);
                        }
                    },

                    handlePaste(e) {
                        e.preventDefault();
                        let pasteData = (e.clipboardData || window.clipboardData).getData('text');
                        if (this.numeric) pasteData = pasteData.replace(/\D/g, '');
                        this.fillValues(pasteData.split(''));
                    },

                    fillValues(chars) {
                        chars.forEach((char, i) => {
                            if (i < this.length) this.digits[i] = char;
                        });

                        let nextIndex = Math.min(chars.length, this.length - 1);
                        this.$nextTick(() => this.inputs()[nextIndex]?.focus());
                    },

                    focusNext(index) {
                        if (index < this.length - 1) {
                            this.$nextTick(() => this.inputs()[index + 1]?.focus());
                        }
                    },

                    focusPrev(index) {
                        if (index > 0) {
                            this.$nextTick(() => {
                                const prev = this.inputs()[index - 1];
                                prev?.focus();
                                prev?.select();
                            });
                        }
                    }
                }));
            });
        </script>
    @endpush
@endonce
