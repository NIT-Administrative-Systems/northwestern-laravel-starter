import type {
    BaseDiffOptions,
    LineDiffTypes,
    ThemeTypes,
    ThemesType,
} from "@pierre/diffs";
import { FileDiff } from "@pierre/diffs";

declare global {
    interface Window {
        Alpine: typeof import("alpinejs");
    }
}

type JsonPrimitive = string | number | boolean | null;

interface JsonObject {
    [key: string]: JsonValue;
}

type JsonValue = JsonPrimitive | JsonObject | JsonValue[];

type AuditValues = JsonValue | null;

function getFilamentTheme(): Exclude<ThemeTypes, "system"> {
    return document.documentElement.classList.contains("dark")
        ? "dark"
        : "light";
}

function toJson(values: AuditValues): string {
    if (values == null) return "{}\n";
    return JSON.stringify(values, null, 2) + "\n";
}

window.Alpine.data(
    "auditDiffViewer",
    (oldValues: AuditValues, newValues: AuditValues) => ({
        diffInstance: null as FileDiff | null,
        diffStyle: "split" as NonNullable<BaseDiffOptions["diffStyle"]>,
        lineDiffType: "word" as LineDiffTypes,
        diffIndicators: "bars" as NonNullable<
            BaseDiffOptions["diffIndicators"]
        >,
        overflow: "wrap" as NonNullable<BaseDiffOptions["overflow"]>,

        init() {
            this.createDiff(toJson(oldValues), toJson(newValues));

            this.$watch("$store.theme", () => {
                this.diffInstance?.setThemeType(getFilamentTheme());
            });
        },

        createDiff(oldJson: string, newJson: string) {
            this.diffInstance?.cleanUp();

            const container: HTMLElement = this.$refs.diffContainer;
            while (container.firstChild) {
                container.removeChild(container.firstChild);
            }

            const theme: ThemesType = {
                dark: "pierre-dark",
                light: "pierre-light",
            };

            this.diffInstance = new FileDiff({
                theme,
                themeType: getFilamentTheme(),
                diffStyle: this.diffStyle,
                lineDiffType: this.lineDiffType,
                diffIndicators: this.diffIndicators,
                overflow: this.overflow,
                disableFileHeader: true,
                disableLineNumbers: false,
            });

            this.diffInstance.render({
                oldFile: {
                    name: "previous_values.json",
                    contents: oldJson,
                },
                newFile: {
                    name: "new_values.json",
                    contents: newJson,
                },
                containerWrapper: container,
            });
        },

        updateOption(option: "diffStyle" | "overflow", value: string) {
            if (option === "diffStyle") {
                this.diffStyle = value as NonNullable<
                    BaseDiffOptions["diffStyle"]
                >;
            } else {
                this.overflow = value as NonNullable<
                    BaseDiffOptions["overflow"]
                >;
            }
            this.createDiff(toJson(oldValues), toJson(newValues));
        },

        destroy() {
            this.diffInstance?.cleanUp();
            this.diffInstance = null;
        },
    }),
);
