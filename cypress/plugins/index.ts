/// <reference types="cypress" />
// ***********************************************************
// This example plugins/index.js can be used to load plugins
//
// You can change the location of this file or turn off loading
// the plugins file with the 'pluginsFile' configuration option.
//
// You can read more here:
// https://on.cypress.io/plugins-guide
// ***********************************************************

import * as http from "node:http";
import * as https from "node:https";
import seeders from "../support/seeders";
import { activateCypressEnvFile, activateLocalEnvFile } from "./swap-env";

type ArtisanParameter = string | number | boolean | null;

type ArtisanParameters = Record<string, ArtisanParameter>;

/**
 * POST a JSON body and resolve on a 2xx response. Local servers can use self-signed
 * certificates, so HTTPS skips certificate verification.
 */
const postJson = (url: string, body: unknown): Promise<void> =>
    new Promise((resolve, reject) => {
        const target = new URL(url);
        const payload = JSON.stringify(body);
        const options = {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Content-Length": Buffer.byteLength(payload),
            },
        };
        const onResponse = (response: http.IncomingMessage): void => {
            response.resume();
            response.on("end", () => {
                const status = response.statusCode ?? 0;

                if (status >= 200 && status < 300) {
                    resolve();
                } else {
                    reject(
                        new Error(`Request failed with status code ${status}`),
                    );
                }
            });
        };
        const request =
            target.protocol === "https:"
                ? https.request(
                      target,
                      { ...options, rejectUnauthorized: false },
                      onResponse,
                  )
                : http.request(target, options, onResponse);

        request.on("error", reject);
        request.end(payload);
    });

/**
 * @type {Cypress.PluginConfig}
 */
export default (
    on: Cypress.PluginEvents,
    config: Cypress.PluginConfigOptions,
): void => {
    const artisan = async (
        command: string,
        parameters: ArtisanParameters = {},
    ): Promise<void> => {
        console.log(`⏳ ${command} ${JSON.stringify(parameters)}`);

        try {
            await postJson(`${config.baseUrl}/__cypress__/artisan`, {
                command: command,
                parameters: parameters,
            });

            console.log(`✅ ${command} ${JSON.stringify(parameters)}`);
        } catch (error: unknown) {
            console.log(error instanceof Error ? error.message : String(error));
            throw new Error(`Failed to run artisan command: ${command}`);
        }
    };

    on("task", {
        activateCypressEnvFile: () => {
            activateCypressEnvFile();
            return null;
        },
        activateLocalEnvFile: () => {
            activateLocalEnvFile();
            return null;
        },
    });

    on("before:run", async () => {
        activateCypressEnvFile();

        if (!config.env.SKIP_DATABASE_REBUILD) {
            await artisan("migrate:fresh", { "--seed": true });

            for (const seeder of seeders) {
                await artisan("db:seed", {
                    "--class": `Database\\Seeders\\${seeder}`,
                });
            }

            await artisan("db:snapshot:create", { filename: "cypress" });
        }
        await artisan("cache:clear");
    });

    on("after:run", () => {
        activateLocalEnvFile();
    });
};
