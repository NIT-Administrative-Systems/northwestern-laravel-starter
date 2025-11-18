@props(['ignoreLivewireUpdates' => false])

<div id="for-{{ $id }}" @if ($ignoreLivewireUpdates) wire:ignore @endif>
    <label class="sr-only"
           id="label-{{ $id }}"
           for="{{ $id }}">Select an option</label>
    <select id="{{ $id }}"
            role="combobox"
            aria-labelledby="label-{{ $id }}"
            aria-expanded="false"
            wire:key="select-{{ $id }}"
            {{ $attributes->merge(['class' => 'form-select']) }}
            autocomplete="off"
            x-ref="nativeSelect"
            x-cloak
            x-show="tomSelectInstance !== null"
            x-data='{
                tomSelectInstance: null,
                onItemAddCallback() {
                    this.setTextboxValue("");
                },
                init() {
                    const selectElement = $refs.nativeSelect;

                    selectElement.classList.remove("form-select");
                },
                onInitializeCallback() {
                     const settings = {{ json_encode($getTomSelectConfig(), JSON_PRETTY_PRINT) }};

                     if (settings.searchDisabled) {
                        this.control_input.setAttribute("readonly", "readonly");
                    } else {
                        this.control_input.removeAttribute("readonly");
                    }
                },
                loadCallback(query, callback) {
                    const params = new URLSearchParams({
                        q: query,
                        l: {{ $maxOptions() }}
                    });

                    fetch("{{ $searchUrl }}?" + params.toString(), {
                        headers: {
                            "Accept": "application/json"
                        }
                    })
                        .then(response => response.json())
                        .then(json => callback(json))
                        .catch((error) => {
                            console.error("Error fetching data: ", error);
                            callback();
                        });
                },
                tomSettings() {
                    const settings = {{ json_encode($getTomSelectConfig(), JSON_PRETTY_PRINT) }};

                    settings.render = {
                        option: function(data, escape) {
                			return `<div class="ts-option-innerLabel">${escape(data.text)}</div>`;
                        },
                    };

                    if (settings.onItemAdd) {
                        settings.onItemAdd = this.onItemAddCallback;
                    }

                    if (settings.onInitialize) {
                        settings.onInitialize = this.onInitializeCallback;
                    }

                    if (settings.load) {
                        settings.load = this.loadCallback;
                    }

                    return settings;
                },

                initializer() {
                    if (! this.tomSelectInstance) {
                        this.tomSelectInstance = new TomSelect($refs.nativeSelect, this.tomSettings());

                        if (!$refs.nativeSelect.hasAttribute("disabled")) {
                            this.tomSelectInstance.enable();
                        }

                        $refs.nativeSelect.tomselect = this.tomSelectInstance;
                    } else {
                        this.tomSelectInstance.sync();
                    }
                },
            }'
            x-init="$nextTick(() => initializer())">
        @if ($attributes->has('placeholder'))
            <option value="">{{ $attributes->get('placeholder') }}</option>
        @endif
        @foreach ($options as $key => $label)
            @if (is_array($label))
                {{-- If an associative array is passed, we're dealing with <optgroups> --}}
                <optgroup label="{{ $key }}">
                    @foreach ($label as $subKey => $subLabel)
                        <option value="{{ $subKey }}">{{ $subLabel }}</option>
                    @endforeach
                </optgroup>
            @else
                {{-- If it's a regular array, it's a basic select --}}
                <option value="{{ $key }}">{{ $label }}</option>
            @endif
        @endforeach
    </select>
</div>
