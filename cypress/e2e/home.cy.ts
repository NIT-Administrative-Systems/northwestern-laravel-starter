describe("Home Page", () => {
    beforeEach(() => {
        cy.loadDatabaseSnapshot();
    });

    context("Unauthenticated users", () => {
        it("should display the landing page", () => {
            cy.visit("/");
            cy.url().should("not.include", "/app");
            cy.get("h1").should("contain.text", "Northwestern Laravel Starter");
            cy.getBySel("sign-in-link").should("be.visible");
            cy.checkAxeViolations();
        });

        it("should link to the login page", () => {
            cy.visit("/");
            cy.getBySel("sign-in-link").click();
            cy.url().should("include", "/app/login");
        });
    });

    context("Authenticated users without roles", () => {
        beforeEach(() => {
            cy.loginAsGenericUser();
        });

        it("should be sent to the app panel", () => {
            cy.visit("/");
            cy.url().should("include", "/app");
            cy.get(".fi-user-menu").should("be.visible");
            cy.checkAxeViolations();
        });

        it("should not display admin panel link for users without permissions", () => {
            cy.visit("/app");
            cy.get(".fi-user-menu").click();
            cy.getBySel("admin-panel-link").should("not.exist");
        });
    });

    context("Super administrators", () => {
        beforeEach(() => {
            cy.loginAsSuperAdmin();
        });

        it("should display admin panel link for authorized users", () => {
            cy.visit("/app");
            cy.get(".fi-user-menu").click();
            cy.getBySel("admin-panel-link").should("be.visible");
        });

        it("should navigate to Filament panel when clicking admin link", () => {
            cy.visit("/app");
            cy.get(".fi-user-menu").click();
            cy.getBySel("admin-panel-link").click();
            cy.url().should("include", "/administration");
        });
    });
});
