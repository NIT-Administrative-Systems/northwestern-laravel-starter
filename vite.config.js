import { sentryVitePlugin } from "@sentry/vite-plugin";
import tailwindcss from "@tailwindcss/vite";
import autoprefixer from "autoprefixer";
import laravel from "laravel-vite-plugin";
import { defineConfig } from "vite";
import { shikiMinimalBundle } from "./resources/js/shiki/vite-plugin.mjs";

export default defineConfig({
    build: {
        sourcemap: true,
    },
    css: {
        devSourcemap: true,
        postcss: {
            plugins: [autoprefixer()],
        },
    },
    plugins: [
        shikiMinimalBundle(),
        tailwindcss(),
        laravel({
            input: [
                "resources/sass/app.scss",
                "resources/js/app.js",
                "resources/js/audit-diff.ts",
                "resources/css/filament/administration/theme.css",
            ],
            refresh: true,
        }),
        sentryVitePlugin({
            org: process.env.SENTRY_ORG,
            project: process.env.SENTRY_PROJECT,
            disable: !process.env.SENTRY_AUTH_TOKEN,
            telemetry: false,
            release: {
                name: process.env.SENTRY_RELEASE,
                setCommits: {
                    auto: true,
                },
            },
            sourcemaps: {
                filesToDeleteAfterUpload: "public/build/assets/*.map",
            },
        }),
    ],
});
