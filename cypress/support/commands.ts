// ***********************************************
// For more comprehensive examples of custom
// commands please read more here:
// https://on.cypress.io/custom-commands
// ***********************************************

import "cypress-axe";
import "cypress-wait-until";

Cypress.Commands.add("loginAs", (username: string) => {
    cy.session(
        username,
        () => {
            cy.login({ username });
        },
        {
            validate() {
                cy.visit("/");
                cy.getBySel("sign-out-link").should("be.visible");
                cy.contains("Sign out").should("be.visible");
                cy.getBySel("logged-in").should("be.visible");
            },
        },
    );

    cy.visit("/");
});

Cypress.Commands.add("loginAsGenericUser", () => {
    cy.loginAs("generic.user");
});

Cypress.Commands.add("loginAsSuperAdmin", () => {
    cy.loginAs("nuit.admin");
});

Cypress.Commands.add(
    "getBySel",
    (selector: string, ...args: [options?: Cypress.GetCommandOptions]) => {
        return cy.get(`[data-cy=${selector}]`, ...args);
    },
);

Cypress.Commands.add(
    "getBySelLike",
    (selector: string, ...args: [options?: Cypress.GetCommandOptions]) => {
        return cy.get(`[id*=${selector}]`, ...args);
    },
);

Cypress.Commands.add("loadDatabaseSnapshot", (filename: string = "cypress") => {
    cy.log(`Loading DB snapshot: ${filename}`);
    cy.artisan(
        "db:snapshot:restore",
        { filename, "--force": true, "--skip-schema-validation": true },
        { log: false },
    );
});

Cypress.Commands.add("checkAxeViolations", () => {
    cy.injectAxe();
    cy.configureAxe({
        rules: [{ id: "duplicate-id", enabled: false }],
    });

    const axeSkipFailures = Cypress.env("axe_skip_failures");
    const axeExcludedSelectors = Cypress.env("axe_excluded_selectors");

    if (axeSkipFailures === "true") {
        cy.checkA11y(undefined, undefined, undefined, true);
    } else if (
        typeof axeExcludedSelectors === "string" &&
        axeExcludedSelectors !== ""
    ) {
        cy.checkA11y({
            exclude: axeExcludedSelectors
                .split(",")
                .map((selector) => selector.trim())
                .filter(Boolean),
        });
    } else {
        cy.checkA11y();
    }
});

Cypress.Commands.add("registerStandardIntercepts", () => {
    cy.intercept({ url: "/livewire/update", method: "POST" }).as(
        "livewireUpdate",
    );

    cy.intercept("/broadcasting/auth", (req) => {
        req.reply({
            statusCode: 200,
            body: {},
        });
    }).as("broadcastAuthBlocker");
});
