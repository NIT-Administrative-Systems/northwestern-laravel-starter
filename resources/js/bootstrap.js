import {
    browserTracingIntegration,
    captureException,
    captureFeedback,
    init as sentryInit,
    setUser as sentrySetUser,
} from "@sentry/browser";
import axios from "axios";
import "bootstrap";
import * as bootstrap from "bootstrap";

/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

window.axios = axios;

window.axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";

window.bootstrap = bootstrap;

/**
 * Sentry v11 collects user info, cookies, HTTP headers, and request/response bodies unless `dataCollection`
 * is set. The layout's Sentry.init() options set none, so keep the v10 defaults unless a caller overrides them.
 */
const sentryDataCollection = {
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

window.Sentry = {
    browserTracingIntegration,
    captureException,
    captureFeedback,
    init: (options) =>
        sentryInit({ dataCollection: sentryDataCollection, ...options }),
    setUser: sentrySetUser,
};
