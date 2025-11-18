/**
 * Example test cases for the starter kit provided home page are defined, but they
 * should be modified as you build out the application and adjust the home page.
 */

describe("Home Page", () => {
    beforeEach(() => {
        cy.loadDatabaseSnapshot();
    });

    it("should display the correct elements for a user without assigned roles", () => {
        cy.loginAsGenericUser();

        // Wait until the H1's opacity is fully visible
        cy.get("h1")
            .should("contain.text", "Northwestern Laravel Starter")
            .should("have.css", "opacity", "1");

        cy.checkAxeViolations();

        cy.get("h1").should("contain.text", "Northwestern Laravel Starter");
    });

    it("should display proper elements for a user with the Super Administrator role", () => {
        cy.loginAsSuperAdmin();

        // Wait until the H1's opacity is fully visible
        cy.get("h1")
            .should("contain.text", "Northwestern Laravel Starter")
            .should("have.css", "opacity", "1");

        cy.checkAxeViolations();

        cy.get("h1").should("contain.text", "Northwestern Laravel Starter");
    });
});
