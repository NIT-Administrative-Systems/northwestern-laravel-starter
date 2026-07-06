/// <reference types="cypress" />

type JsonPrimitive = string | number | boolean | null;

interface JsonObject {
    [key: string]: JsonValue;
}

type JsonValue = JsonPrimitive | JsonObject | JsonValue[];

interface CypressRouteDefinition {
    name: string;
    domain: string | null;
    action: string;
    uri: string;
    method: string[];
}

type CypressRouteTable = Record<string, CypressRouteDefinition>;
type CypressRouteParameters = Record<string, JsonPrimitive>;

declare namespace Cypress {
    interface Cypress {
        Laravel: {
            routes: CypressRouteTable;
            currentUser?: JsonObject | null;
            route: (
                name: string,
                parameters?: CypressRouteParameters,
            ) => string;
        };
    }

    type GetCommandOptions = Partial<
        Loggable & Timeoutable & Withinable & Shadow
    >;

    interface Chainable<Subject> {
        /**
         * Log in the user with the given attributes, or create a new user and then log them in.
         *
         * @example
         * cy.login()
         * cy.login({ id: 1 })
         */
        login(attributes?: JsonObject): Chainable<JsonObject>;

        /**
         * Log in as the given user.
         *
         * @example
         * cy.loginAs('generic.user')
         */
        loginAs(username: string): Chainable<AUTWindow>;

        /**
         * Log in to a sample generic account and visit the home page.
         */
        loginAsGenericUser(): Chainable<AUTWindow>;

        /**
         * Log in to a sample Super Administrator account and visit the home page.
         */
        loginAsSuperAdmin(): Chainable<AUTWindow>;

        /**
         * Log out the current user.
         *
         * @example
         * cy.logout()
         */
        logout(): Chainable<void>;

        /**
         * Fetch the currently authenticated user.
         *
         * @example
         * cy.currentUser()
         */
        currentUser(): Chainable<JsonObject | null>;

        /**
         * Fetch a CSRF token from the server.
         *
         * @example
         * cy.logout()
         */
        csrfToken(): Chainable<string>;

        /**
         * Fetch a fresh list of URI routes from the server.
         *
         * @example
         * cy.logout()
         */
        refreshRoutes(): Chainable<CypressRouteTable>;

        /**
         * Create and persist a new Eloquent record using Laravel model factories.
         *
         * @example
         * cy.create('App\\User');
         * cy.create('App\\User', 2);
         * cy.create('App\\User', 2, { active: false });
         * cy.create({ model: 'App\\User', state: ['guest'], relations: ['profile'], count: 2 }
         */
        create(
            model: string | JsonObject,
            count?: number | string[] | JsonObject,
            attributes?: JsonObject,
            load?: string[],
            state?: string[],
        ): Chainable<JsonObject | JsonObject[]>;

        /**
         * Refresh the database state using Laravel's migrate:fresh command.
         *
         * @example
         * cy.refreshDatabase()
         * cy.refreshDatabase({ '--drop-views': true }
         */
        refreshDatabase(
            options?: Record<string, JsonPrimitive>,
        ): Chainable<Response<JsonValue>>;

        /**
         * Resets the database state to the given snapshot file (defaults to "cypress").
         */
        loadDatabaseSnapshot(filename?: string): Chainable<void>;

        /**
         * Run Artisan's db:seed command.
         *
         * @example
         * cy.seed()
         * cy.seed('PlansTableSeeder')
         */
        seed(seederClass?: string): Chainable<Response<JsonValue>>;

        /**
         * Run an Artisan command.
         *
         * @example
         * cy.artisan('cache:clear')
         */
        artisan(
            command: string,
            parameters?: Record<string, JsonPrimitive>,
            options?: { log?: boolean },
        ): Chainable<Response<JsonValue>>;

        /**
         * Execute arbitrary PHP on the server.
         *
         * @example
         * cy.php('2 + 2')
         * cy.php('App\\User::count()')
         */
        php(command: string): Chainable<string>;

        /**
         * Assert that the current URL matches the given path.
         *
         * @example
         * cy.assertRedirect('/')
         */
        assertRedirect(path: string): Chainable<void>;

        /**
         * Check for accessibility violations.
         */
        checkAxeViolations(): Chainable<void>;

        /**
         * Select an element by its `data-cy` attribute.
         */
        getBySel(
            selector: string,
            ...args: [options?: GetCommandOptions]
        ): Chainable<JQuery<HTMLElement>>;

        /**
         * Select an element by a partial match of its `id`.
         */
        getBySelLike(
            selector: string,
            ...args: [options?: GetCommandOptions]
        ): Chainable<JQuery<HTMLElement>>;

        /**
         * Select a DOM element by its `wire:model` attribute.
         *
         * This command is especially useful for fetching elements dynamically generated by libraries
         * like {@link https://github.com/rappasoft/laravel-livewire-tables|Laravel Livewire Tables},
         * where DOM elements have Livewire properties bound to them, and it's not possible to set
         * the `data-cy` attribute.
         *
         * @example
         * // If you have a Livewire component with a bound property like this:
         * // <input wire:model="user.name" type="text" />
         * // You can fetch it like this:
         * cy.getByLivewireProperty('user.name').type('New Name');
         */
        getByLivewireProperty(
            selector: string,
            ...args: [options?: GetCommandOptions]
        ): Chainable<JQuery<HTMLElement>>;

        /**
         * Registers standard URL intercepts that are used by most tests:
         *
         *  - @livewireUpdate hooks the /livewire/update route that all components communicate with.
         *  - @broadcastAuthBlocker hooks /broadcasting/auth to disable the constant spam of 403s.
         */
        registerStandardIntercepts(): void;
    }
}
