describe("Authentication - Login", () => {
    beforeEach(() => {
        cy.loadDatabaseSnapshot();
    });

    context("Login selection page", () => {
        it("should display available login methods", () => {
            cy.visit("/app/login");

            cy.php("config('local-auth.enabled')").then((enabled) => {
                if (enabled) {
                    cy.getBySel("email-login").should("be.visible");
                }
            });

            cy.checkAxeViolations();
        });
    });

    context("Local login", () => {
        beforeEach(function () {
            cy.php("config('local-auth.enabled')").then((enabled) => {
                if (!enabled) {
                    cy.log("Local auth disabled, skipping test");
                    this.skip();
                }
            });
        });

        it("should show login code request form", () => {
            cy.visit("/app/login/email");
            cy.getBySel("email-input").should("be.visible");
            cy.getBySel("continue-button").should("be.visible");
            cy.checkAxeViolations();
        });

        it("should send login code email for valid user", () => {
            cy.visit("/app/login/email");
            cy.getBySel("email-input").type("partner-user@uchicago.edu", {
                force: true,
            });
            cy.getBySel("continue-button").click();
            cy.contains("Check your email").should("be.visible");
            cy.getBySel("code-input").should("be.visible");
        });

        it("should validate login code and authenticate user", () => {
            cy.visit("/app/login/email");
            cy.getBySel("email-input").type("partner-user@uchicago.edu", {
                force: true,
            });
            cy.getBySel("continue-button").click();

            cy.getBySel("code-input").type("123456");
            cy.getBySel("verify-button").click();

            cy.url().should("not.include", "/app/login");
            cy.getBySel("logged-in").should("be.visible");
        });

        it("should reject invalid login codes", () => {
            cy.visit("/app/login/email");
            cy.getBySel("email-input").type("partner-user@uchicago.edu", {
                force: true,
            });
            cy.getBySel("continue-button").click();

            cy.getBySel("code-input").type("999999");
            cy.getBySel("verify-button").click();

            cy.url().should("include", "/app/login/email");
            cy.contains("Invalid code");
        });
    });
});
