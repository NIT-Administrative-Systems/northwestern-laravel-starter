/**
 * Create a new user and log them in.
 *
 * @param {Object} attributes
 *
 * @example cy.login();
 *          cy.login({ name: 'JohnDoe' });
 *          cy.login({ attributes: { name: 'JohnDoe' }, state: 'guest', load: ['comments] });
 */
/// <reference types="./" />

type FactoryState = string[] | JsonObject;

interface FactoryRequest {
    model: string;
    count: number;
    attributes: JsonObject;
    load: FactoryState;
    state: FactoryState;
}

type FactoryRequestBody = FactoryRequest | JsonObject;
type RouteVisitTarget = {
    route: string;
    parameters?: CypressRouteParameters;
};

const isPlainObject = (
    value: JsonObject | string[] | string | number | boolean | null | undefined,
): value is JsonObject => {
    return typeof value === "object" && value !== null && !Array.isArray(value);
};

const isRouteVisitTarget = (
    value: string | RouteVisitTarget,
): value is RouteVisitTarget => {
    return typeof value === "object" && value !== null;
};

Cypress.Commands.add("login", (attributes: JsonObject = {}) => {
    cy.clearCookies();

    const requestBody: JsonObject =
        "attributes" in attributes ||
        "state" in attributes ||
        "load" in attributes
            ? attributes
            : { attributes };

    return cy
        .csrfToken()
        .then((token: string) => {
            return cy.request<JsonObject>({
                method: "POST",
                url: "/__cypress__/login",
                body: { ...requestBody, _token: token },
                log: false,
            });
        })
        .then(({ body }) => {
            Cypress.Laravel.currentUser = body;

            Cypress.log({
                name: "login",
                message: JSON.stringify(body),
                consoleProps: () => ({ user: body }),
            });
        })
        .its("body", { log: false });
});

/**
 * Fetch the currently authenticated user object.
 *
 * @example cy.currentUser();
 */
Cypress.Commands.add("currentUser", () => {
    return cy
        .csrfToken()
        .then((token: string) =>
            cy.request<JsonObject | null>({
                method: "POST",
                url: "/__cypress__/current-user",
                body: { _token: token },
                log: false,
            }),
        )
        .its("body", { log: false })
        .then((body: JsonObject | null) => {
            Cypress.Laravel.currentUser = body;

            return body;
        }) as unknown as Cypress.Chainable<JsonObject | null>;
});

/**
 * Logout the current user.
 *
 * @example cy.logout();
 */
Cypress.Commands.add("logout", () => {
    cy.csrfToken()
        .then((token: string) => {
            cy.request({
                method: "POST",
                url: "/__cypress__/logout",
                body: { _token: token },
                log: false,
            });
        })
        .then(() => {
            Cypress.log({ name: "logout", message: "" });
        });
});

/**
 * Fetch a CSRF token.
 *
 * @example cy.csrfToken();
 */
Cypress.Commands.add("csrfToken", () => {
    return cy
        .request<string>({
            method: "GET",
            url: "/__cypress__/csrf_token",
            log: false,
        })
        .its("body", { log: false });
});

/**
 * Fetch and store all named routes.
 *
 * @example cy.refreshRoutes();
 */
Cypress.Commands.add("refreshRoutes", () => {
    return cy.csrfToken().then((token: string) => {
        return cy
            .request<CypressRouteTable>({
                method: "POST",
                url: "/__cypress__/routes",
                body: { _token: token },
                log: false,
            })
            .its("body", { log: false })
            .then((routes: CypressRouteTable) => {
                Cypress.Laravel.routes = routes;

                return cy
                    .writeFile(
                        Cypress.config().supportFolder + "/routes.json",
                        routes,
                        {
                            log: false,
                        },
                    )
                    .then(() => routes);
            });
    });
});

/**
 * Visit the given URL or route.
 *
 * @example cy.visit('foo/path');
 *          cy.visit({ route: 'home' });
 *          cy.visit({ route: 'team', parameters: { team: 1 } });
 */
Cypress.Commands.overwrite("visit", ((
    originalFn: any,
    subject: any,
    options: any,
) => {
    if (isRouteVisitTarget(subject)) {
        const visitOptions: Partial<Cypress.VisitOptions> & {
            url: string;
            method: Cypress.VisitOptions["method"];
        } = {
            url: Cypress.Laravel.route(subject.route, subject.parameters || {}),
            method: Cypress.Laravel.routes[subject.route]
                .method[0] as Cypress.VisitOptions["method"],
            ...options,
        };

        return (
            originalFn as unknown as (
                options: Partial<Cypress.VisitOptions> & { url: string },
            ) => Cypress.Chainable<Cypress.AUTWindow>
        )(visitOptions);
    }

    return (
        originalFn as unknown as (
            url: string,
            options?: Partial<Cypress.VisitOptions>,
        ) => Cypress.Chainable<Cypress.AUTWindow>
    )(subject as unknown as string, options);
}) as any);

/**
 * Create a new Eloquent factory.
 *
 * @param {String} model
 * @param {Number|null} times
 * @param {Object} attributes
 *
 * @example cy.create('App\\User');
 *          cy.create('App\\User', 2);
 *          cy.create('App\\User', 2, { active: false });
 *          cy.create('App\\User', { active: false });
 *          cy.create('App\\User', 2, { active: false });
 *          cy.create('App\\User', 2, { active: false }, ['profile']);
 *          cy.create('App\\User', 2, { active: false }, ['profile'], ['guest']);
 *          cy.create('App\\User', { active: false }, ['profile']);
 *          cy.create('App\\User', { active: false }, ['profile'], ['guest']);
 *          cy.create('App\\User', ['profile']);
 *          cy.create('App\\User', ['profile'], ['guest']);
 *          cy.create({ model: 'App\\User', state: ['guest'], relations: ['profile'], count: 2 }
 */
Cypress.Commands.add(
    "create",
    (
        model: string | JsonObject,
        count: number | string[] | JsonObject = 1,
        attributes: JsonObject | string[] = {},
        load: FactoryState = [],
        state: FactoryState = [],
    ) => {
        let requestBody: FactoryRequestBody;
        let messageModel = "";
        let messageCount = 1;

        if (typeof model === "string") {
            if (Array.isArray(count)) {
                state = attributes;
                attributes = {};
                load = count;
                count = 1;
            } else if (isPlainObject(count)) {
                state = load;
                load = attributes;
                attributes = count;
                count = 1;
            }

            messageModel = model;
            messageCount = count;
            requestBody = {
                model,
                state,
                attributes: isPlainObject(attributes) ? attributes : {},
                load,
                count,
            };
        } else {
            requestBody = model;
            messageModel = typeof model.model === "string" ? model.model : "";
            messageCount = typeof model.count === "number" ? model.count : 1;
        }

        return cy
            .csrfToken()
            .then((token: string) => {
                return cy.request<JsonObject | JsonObject[]>({
                    method: "POST",
                    url: "/__cypress__/factory",
                    body: { ...requestBody, _token: token },
                    log: false,
                });
            })
            .then((response) => {
                Cypress.log({
                    name: "create",
                    message:
                        messageModel +
                        (messageCount > 1 ? ` (${messageCount} times)` : ""),
                    consoleProps: () => ({ [messageModel]: response.body }),
                });
            })
            .its("body", { log: false });
    },
);

/**
 * Refresh the database state.
 *
 * @param {Object} options
 *
 * @example cy.refreshDatabase();
 *          cy.refreshDatabase({ '--drop-views': true });
 */
Cypress.Commands.add(
    "refreshDatabase",
    (options: Record<string, JsonPrimitive> = {}) => {
        return cy.artisan("migrate:fresh", options);
    },
);

/**
 * Seed the database.
 *
 * @param {String} seederClass
 *
 * @example cy.seed();
 *          cy.seed('PlansTableSeeder');
 */
Cypress.Commands.add("seed", (seederClass: string = "") => {
    const options: Record<string, JsonPrimitive> = {};

    if (seederClass) {
        options["--class"] = seederClass;
    }

    return cy.artisan("db:seed", options);
});

/**
 * Trigger an Artisan command.
 *
 * @param {String} command
 * @param {Object} parameters
 * @param {Object} options
 *
 * @example cy.artisan('cache:clear');
 */
Cypress.Commands.add(
    "artisan",
    (
        command: string,
        parameters: Record<string, JsonPrimitive> = {},
        options: { log?: boolean } = {},
    ) => {
        const resolvedOptions = { log: true, ...options };

        if (resolvedOptions.log) {
            Cypress.log({
                name: "artisan",
                message: (() => {
                    let message = command;

                    for (const key in parameters) {
                        message += ` ${key}="${parameters[key]}"`;
                    }

                    return message;
                })(),
                consoleProps: () => ({ command, parameters }),
            });
        }

        return cy.csrfToken().then((token: string) => {
            return cy.request({
                method: "POST",
                url: "/__cypress__/artisan",
                body: {
                    command: command,
                    parameters: parameters,
                    _token: token,
                },
                log: false,
                timeout: 90000,
            });
        });
    },
);

/**
 * Execute arbitrary PHP.
 *
 * @param {String} command
 *
 * @example cy.php('2 + 2');
 *          cy.php('App\\User::count()');
 */
Cypress.Commands.add("php", (command: string) => {
    return cy
        .csrfToken()
        .then((token: string) => {
            return cy.request<{ result: string }>({
                method: "POST",
                url: "/__cypress__/run-php",
                body: { command: command, _token: token },
                log: false,
            });
        })
        .then((response) => {
            Cypress.log({
                name: "php",
                message: command,
                consoleProps: () => ({ result: response.body.result }),
            });
        })
        .its("body.result", { log: false });
});
