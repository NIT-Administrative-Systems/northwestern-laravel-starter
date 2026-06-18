import * as fs from "node:fs";

function shouldSwapEnvFiles(): boolean {
    return !process.env.CI;
}

export function activateCypressEnvFile() {
    if (shouldSwapEnvFiles() && fs.existsSync(".env.cypress")) {
        fs.renameSync(".env", ".env.backup");
        fs.renameSync(".env.cypress", ".env");
    }

    return null;
}

export function activateLocalEnvFile() {
    if (shouldSwapEnvFiles() && fs.existsSync(".env.backup")) {
        fs.renameSync(".env", ".env.cypress");
        fs.renameSync(".env.backup", ".env");
    }

    return null;
}
