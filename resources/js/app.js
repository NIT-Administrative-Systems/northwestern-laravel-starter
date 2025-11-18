import ClipboardJS from "clipboard";
import TomSelect from "tom-select";
import {
    Alpine,
    Livewire,
} from "../../vendor/livewire/livewire/dist/livewire.esm";
import "./bootstrap";
import OptionCount from "./tom-select/plugins/option_count";
import Utils from "./utils";

window.ClipboardJS = ClipboardJS;

TomSelect.define("option_count", OptionCount);
window.TomSelect = TomSelect;

window.Livewire = Livewire;
Livewire.start();

window.Alpine = Alpine;

window.Utils = Utils;
Utils.initTooltips();
