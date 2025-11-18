import tailwindcss from "@tailwindcss/vite";
import autoprefixer from "autoprefixer";
import laravel from "laravel-vite-plugin";
import { defineConfig } from "vite";

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
        tailwindcss(),
        laravel({
            input: [
                "resources/sass/app.scss",
                "resources/js/app.js",
                "resources/css/filament/administration/theme.css",
            ],
            refresh: true,
        }),
    ],
});
