describe("Home Page", () => {
    beforeEach(() => {
        cy.loadDatabaseSnapshot();
    });

    context("Unauthenticated users", () => {
        it("should redirect to login selection page", () => {
            cy.visit("/");
            cy.url().should("include", "/auth/type");
        });
    });

    context("Authenticated users without roles", () => {
        beforeEach(() => {
            cy.loginAsGenericUser();
        });

        it("should display the home page with logout link", () => {
            cy.visit("/");
            cy.get("h1")
                .should("contain.text", "Northwestern Laravel Starter")
                .should("have.css", "opacity", "1");
            cy.getBySel("sign-out-link").should("be.visible");
            cy.getBySel("logged-in").should("be.visible");
            cy.checkAxeViolations();
        });

        it("should not display admin panel link for users without permissions", () => {
            cy.visit("/");
            cy.getBySel("admin-panel-link").should("not.exist");
        });
    });

    context("Super administrators", () => {
        beforeEach(() => {
            cy.loginAsSuperAdmin();
        });

        it("should display admin panel link for authorized users", () => {
            cy.visit("/");
            cy.get("h1")
                .should("contain.text", "Northwestern Laravel Starter")
                .should("have.css", "opacity", "1");
            cy.getBySel("admin-panel-link").should("be.visible");
            cy.checkAxeViolations();
        });

        it("should navigate to Filament panel when clicking admin link", () => {
            cy.visit("/");
            cy.getBySel("admin-panel-link").click();
            cy.url().should("include", "/administration");
        });
    });
});
