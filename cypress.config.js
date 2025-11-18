import { defineConfig } from "cypress";
import { GenerateCtrfReport } from "cypress-ctrf-json-reporter";
import fs from "fs";
import path from "path";
import plugin from "./cypress/plugins/index.ts";

/*
 * Users might have an alternate APP_URL in their .env file. This function
 * will extract that value and use it as the baseUrl for Cypress tests.
 */
function getBaseUrlFromEnv() {
    const envFilePath = path.resolve(process.cwd(), ".env");
    const envFileContent = fs.readFileSync(envFilePath, "utf8");
    const appUrl = envFileContent
        .split("\n")
        .find((line) => line.startsWith("APP_URL="));

    return appUrl ? appUrl.split("=")[1] : "";
}

export default defineConfig({
    defaultCommandTimeout: 5000,
    chromeWebSecurity: false,
    env: {
        axe_skip_failures: "false",
        axe_excluded_selectors: "",
    },
    retries: {
        runMode: 2,
    },
    watchForFileChanges: false,
    videosFolder: "cypress/videos",
    screenshotsFolder: "cypress/screenshots",
    fixturesFolder: "cypress/fixture",
    viewportWidth: 1920,
    viewportHeight: 1080,
    e2e: {
        baseUrl:
            getBaseUrlFromEnv() ||
            "https://northwestern-laravel-starter-example.adoes.northwestern.edu",
        supportFile: "cypress/support/index.js",
        setupNodeEvents(on, config) {
            initPlugins(on, config);
        },
    },
    experimentalInteractiveRunEvents: true,
});

function initPlugins(on, config) {
    const eventCallbacks = {};

    const customOn = (eventName, callback) => {
        if (eventName === "task") {
            if (!eventCallbacks[eventName]) {
                eventCallbacks[eventName] = {};
                on(eventName, eventCallbacks[eventName]);
            }
            Object.assign(eventCallbacks[eventName], callback);
            return;
        }

        if (!eventCallbacks[eventName]) {
            eventCallbacks[eventName] = [];
            on(eventName, async (...args) => {
                for (const cb of eventCallbacks[eventName]) {
                    await cb(...args);
                }
            });
        }

        eventCallbacks[eventName].push(callback);
    };

    plugin(customOn, config);

    new GenerateCtrfReport({
        on: customOn,
        outputDir: ".build",
        outputFile: "ctrf-report.json",
        minimal: false,
        testType: "e2e",
        appName:
            process.env.GITHUB_REPOSITORY?.split("/")[1]
                .split("-")
                .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
                .join(" ") || "",
        buildNumber: process.env.GITHUB_RUN_NUMBER || "",
        buildUrl:
            process.env.GITHUB_SERVER_URL &&
            process.env.GITHUB_REPOSITORY &&
            process.env.GITHUB_RUN_ID
                ? `${process.env.GITHUB_SERVER_URL}/${process.env.GITHUB_REPOSITORY}/actions/runs/${process.env.GITHUB_RUN_ID}`
                : "",
        repositoryName: process.env.GITHUB_REPOSITORY?.split("/")[1] || "",
        repositoryUrl:
            process.env.GITHUB_SERVER_URL && process.env.GITHUB_REPOSITORY
                ? `${process.env.GITHUB_SERVER_URL}/${process.env.GITHUB_REPOSITORY}`
                : "",
        branchName: process.env.GITHUB_REF_NAME || "",
        osPlatform: process.env.RUNNER_OS || "",
        osRelease: process.env.RUNNER_ARCH || "",
        osVersion: process.env.RUNNER_ENVIRONMENT || "",
    });
}
