import TomSelect from "tom-select";
import { TomItem, TomOption } from "tom-select/src/types";
import { escape_html } from "tom-select/src/utils";
import { getDom } from "tom-select/src/vanilla";

interface OptionCountPluginOptions {
    singularNoun?: string;
    pluralNoun?: string;
}

export default function (
    this: TomSelect,
    pluginOptions: OptionCountPluginOptions,
): void {
    const tomSelectInstance = this;

    const options = {
        singularNoun: "Item",
        pluralNoun: "Items",
        ...pluginOptions,
    };

    // Ensure multiple selection is allowed
    if (!tomSelectInstance.input.hasAttribute("multiple")) {
        throw new Error(
            "option_count plugin can only be used with <select multiple> elements.",
        );
    }

    // Hide placeholder by default
    tomSelectInstance.settings.hidePlaceholder = true;

    // Create a span to display the selection count
    tomSelectInstance.countIndicator = getDom(`
      <span class="ts-plugin-option-count ps-1 me-4">No selection</span>
    `);

    tomSelectInstance.hook("after", "setupTemplates", () => {
        const originalRenderItem = tomSelectInstance.settings.render.item;

        tomSelectInstance.settings.render.item = (
            data: TomOption,
            escape: typeof escape_html,
        ) => {
            const itemElement = getDom(
                originalRenderItem.call(tomSelectInstance, data, escape),
            ) as TomItem;
            itemElement.classList.add("d-none");
            return itemElement;
        };
    });

    // Disable "select all" (CTRL + A) on the main TomSelect control
    tomSelectInstance.hook("instead", "selectAll", () => {
        // Intentionally left blank to disable the action
    });

    // Update the count indicator text based on selected items
    const updateSelectionCountIndicator = (): void => {
        const selectedCount = tomSelectInstance.getValue().length;

        if (selectedCount === 0) {
            tomSelectInstance.countIndicator.textContent = `All ${options.pluralNoun} Selected`;
            return;
        }

        const noun =
            selectedCount === 1 ? options.singularNoun : options.pluralNoun;
        tomSelectInstance.countIndicator.textContent = `${selectedCount} ${noun} Selected`;
    };

    tomSelectInstance.on("initialize", () => {
        tomSelectInstance.control.append(tomSelectInstance.countIndicator);
        updateSelectionCountIndicator();
    });

    // Update the count indicator whenever the selection changes
    tomSelectInstance.on("change", updateSelectionCountIndicator);
}
