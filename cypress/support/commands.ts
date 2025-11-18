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
                cy.getBySel("logout-link").should("be.visible");
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

Cypress.Commands.add("getBySel", (selector, ...args) => {
    return cy.get(`[data-cy=${selector}]`, ...args);
});

Cypress.Commands.add("getBySelLike", (selector, ...args) => {
    return cy.get(`[id*=${selector}]`, ...args);
});

Cypress.Commands.add("loadDatabaseSnapshot", () => {
    cy.log("Loading DB snapshot...");
    cy.artisan("db:snapshot:restore", {}, { log: false });
});

Cypress.Commands.add("checkAxeViolations", () => {
    cy.injectAxe();
    cy.configureAxe({
        rules: [{ id: "duplicate-id", enabled: false }],
    });

    if (Cypress.env("axe_skip_failures") === "true") {
        cy.checkA11y(null, null, null, true);
    } else {
        if (Cypress.env("axe_excluded_selectors")) {
            cy.checkA11y({
                exclude: Cypress.env("axe_excluded_selectors").split(),
            });
        } else {
            cy.checkA11y();
        }
    }
});
