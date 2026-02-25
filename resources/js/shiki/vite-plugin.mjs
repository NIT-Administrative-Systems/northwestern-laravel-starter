import { createRequire } from "node:module";
import { dirname } from "node:path";
import { fileURLToPath } from "node:url";

/**
 * Redirects Shiki's bundled language/theme modules to local minimal stubs.
 *
 * @pierre/diffs depends on shiki, which dynamically imports ~200 languages
 * and ~65 themes. Vite generates a chunk for each one even though we only
 * use JSON highlighting in the audit diff viewer.
 */
export function shikiMinimalBundle() {
    const require = createRequire(import.meta.url);
    const shikiLangsStub = fileURLToPath(
        new URL("./langs.mjs", import.meta.url),
    );
    const shikiThemesStub = fileURLToPath(
        new URL("./themes.mjs", import.meta.url),
    );
    const pierreDiffsEntry = fileURLToPath(import.meta.resolve("@pierre/diffs"));
    const shikiPackageJson = require.resolve("shiki/package.json", {
        paths: [dirname(pierreDiffsEntry)],
    });
    const shikiJsonLanguageModule = require.resolve("@shikijs/langs/json", {
        paths: [dirname(shikiPackageJson)],
    });

    return {
        name: "shiki-minimal-bundle",
        enforce: "pre",
        resolveId(source, importer) {
            if (source === "@shikijs/langs/json" && importer === shikiLangsStub) {
                return shikiJsonLanguageModule;
            }

            // Coupled to shiki's internal directory layout — if shiki
            // restructures its dist/ folder this guard may need updating.
            if (!importer?.includes("/shiki/dist/")) {
                return null;
            }

            if (source === "./langs.mjs") return shikiLangsStub;
            if (source === "./themes.mjs") return shikiThemesStub;

            return null;
        },
    };
}
