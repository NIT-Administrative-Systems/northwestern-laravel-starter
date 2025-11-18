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

import axios from "axios";
import * as https from "node:https";
import seeders from "../support/seeders";
import { activateCypressEnvFile, activateLocalEnvFile } from "./swap-env";

type ArtisanParameters = Record<string, any>;

/**
 * @type {Cypress.PluginConfig}
 */
export default (on, config) => {
    const artisan = async (
        command: string,
        parameters: ArtisanParameters = {},
    ): Promise<void> => {
        console.log(`⏳ ${command} ${JSON.stringify(parameters)}`);

        try {
            await axios.post(
                `${config.baseUrl}/__cypress__/artisan`,
                {
                    command: command,
                    parameters: parameters,
                },
                {
                    httpsAgent: new https.Agent({
                        rejectUnauthorized: false,
                    }),
                    headers: {
                        "Content-Type": "application/json",
                    },
                },
            );

            console.log(`✅ ${command} ${JSON.stringify(parameters)}`);
        } catch (error) {
            console.log(error);
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

            await artisan("db:snapshot:create");
        }
        await artisan("cache:clear");
    });

    on("after:run", () => {
        activateLocalEnvFile();
    });
};
