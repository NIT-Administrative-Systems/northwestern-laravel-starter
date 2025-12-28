describe("Authentication - Login", () => {
    beforeEach(() => {
        cy.loadDatabaseSnapshot();
    });

    context("Login selection page", () => {
        it("should display available login methods", () => {
            cy.visit("/auth/type");
            cy.getBySel("netid-login").should("be.visible");

            cy.php("config('auth.local.enabled')").then((enabled) => {
                if (enabled) {
                    cy.getBySel("email-login").should("be.visible");
                }
            });

            cy.checkAxeViolations();
        });
    });

    context("Local login", () => {
        beforeEach(() => {
            cy.php("config('auth.local.enabled')").then((enabled) => {
                if (!enabled) {
                    cy.log("Local auth disabled, skipping test");
                    this.skip();
                }
            });
        });

        it("should show login code request form", () => {
            cy.visit("/auth/login");
            cy.getBySel("email-input").should("be.visible");
            cy.getBySel("continue-button").should("be.visible");
            cy.checkAxeViolations();
        });

        it("should send login code email for valid user", () => {
            cy.visit("/auth/login");
            cy.getBySel("email-input").type("partner-user@uchicago.edu", {
                force: true,
            });
            cy.getBySel("continue-button").click();
            cy.url().should("include", "/auth/login/code");
        });

        it("should validate login code and authenticate user", () => {
            cy.visit("/auth/login");
            cy.getBySel("email-input").type("partner-user@uchicago.edu", {
                force: true,
            });
            cy.getBySel("continue-button").click();

            cy.get(".otp-item > input").first().type("123456");

            cy.url().should("not.include", "/auth/login");
            cy.getBySel("logged-in").should("be.visible");
        });

        it("should reject invalid login codes", () => {
            cy.visit("/auth/login");
            cy.getBySel("email-input").type("partner-user@uchicago.edu", {
                force: true,
            });
            cy.getBySel("continue-button").click();

            cy.get(".otp-item > input").first().type("999999");

            cy.url().should("include", "/auth/login");
            cy.contains("Invalid code");
        });
    });
});
