describe("Authentication - Logout", () => {
    beforeEach(() => {
        cy.loadDatabaseSnapshot();
    });

    it("should log out user and clear session", () => {
        cy.loginAsGenericUser();

        cy.getBySel("sign-out-link").click();
        cy.getBySel("logged-in").should("not.exist");
    });

    it("should redirect to home after logout", () => {
        cy.loginAsSuperAdmin();
        cy.visit("/administration");

        cy.get(".fi-user-menu").click();
        cy.getBySel("sign-out-menu-link").click();
        cy.url().should("include", "/auth/type");
    });

    it("should prevent access to protected pages after logout", () => {
        cy.loginAsSuperAdmin();
        cy.visit("/administration");
        cy.url().should("include", "/administration");

        cy.get(".fi-user-menu").click();
        cy.getBySel("sign-out-menu-link").click();

        cy.visit("/administration", { failOnStatusCode: false });
        cy.url().should("include", "/auth/type");
    });
});
