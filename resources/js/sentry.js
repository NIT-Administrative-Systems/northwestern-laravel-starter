import {
    browserTracingIntegration,
    captureException,
    captureFeedback,
    init,
    setUser,
} from "@sentry/browser";

/**
 * Starts the Sentry browser SDK from the JSON config that the `<x-sentry-browser />`
 * component renders into the page.
 *
 * Sentry v11 collects user info, cookies, HTTP headers, and request/response bodies unless
 * `dataCollection` is set, so keep the v10 defaults.
 */
const dataCollection = {
    userInfo: false,
    cookies: false,
    httpHeaders: {
        request: { deny: ["forwarded", "-ip", "remote-", "via", "-user"] },
        response: { deny: ["forwarded", "-ip", "remote-", "via", "-user"] },
    },
    httpBodies: [],
    urlQueryParams: { deny: ["forwarded", "-ip", "remote-", "via", "-user"] },
    genAI: { inputs: false, outputs: false },
    databaseQueryData: false,
    queues: false,
    graphQL: { document: false, variables: false },
};

const element = document.getElementById("sentry-browser-config");
const config = element ? JSON.parse(element.textContent) : null;

if (config?.dsn) {
    init({
        dsn: config.dsn,
        environment: config.environment,
        tunnel: config.tunnel ?? undefined,
        tracesSampleRate: config.tracesSampleRate,
        integrations: config.tracing ? [browserTracingIntegration()] : [],
        dataCollection,
    });

    if (config.user) {
        setUser(config.user);
    }
}

// Pages call these directly, for example to send user feedback from an error page.
window.Sentry = { captureException, captureFeedback };
