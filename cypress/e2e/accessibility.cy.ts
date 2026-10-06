/**
 * Every page the starter ships passes axe. Add your application's pages to these lists.
 *
 * Pages that answer with an error status (the 404 and 403 pages) are visited with
 * failOnStatusCode: false so the page itself is checked.
 */
const visit = (path: string): void => {
    cy.visit(path, { failOnStatusCode: false });
};

describe("Accessibility", () => {
    beforeEach(() => {
        cy.loadDatabaseSnapshot();
    });

    context("Guests", () => {
        [
            "/",
            "/app/login",
            "/app/login/email",
            "/support/changelog",
            "/this-page-does-not-exist",
        ].forEach((path) => {
            it(`${path} has no violations`, () => {
                visit(path);
                cy.checkAxeViolations();
            });
        });

        it("the email sign-in code step has no violations", () => {
            visit("/app/login/email");
            cy.getBySel("email-input").type("partner-user@uchicago.edu", {
                force: true,
            });
            cy.getBySel("continue-button").click();
            cy.getBySel("code-input").should("be.visible");
            cy.checkAxeViolations();
        });

        it("a changelog entry has no violations", () => {
            cy.php(
                "return App\\Domains\\Support\\Models\\Changelog::factory()->create(['body' => \"### Changes\\n\\n- A change\"])->slug;",
            ).then((slug) => {
                visit(`/support/changelog/${slug}`);
                cy.checkAxeViolations();
            });
        });
    });

    context("Signed-in users", () => {
        beforeEach(() => {
            cy.loginAsGenericUser();
        });

        [
            "/app",
            "/app/gallery",
            "/app/account/profile",
            "/app/account/preferences",
            "/app/account/connected-applications",
            "/app/announcements",
            "/app/support/contact",
            "/app/access-restricted",
            "/support/changelog",
            "/this-page-does-not-exist",
            "/administration",
        ].forEach((path) => {
            it(`${path} has no violations`, () => {
                visit(path);
                cy.checkAxeViolations();
            });
        });

        it("the announcement banner and page have no violations", () => {
            cy.php(
                "App\\Domains\\Support\\Models\\Announcement::factory()->severity(App\\Domains\\Support\\Enums\\AnnouncementSeverity::Warning)->create(['title' => 'Scheduled maintenance', 'body' => 'The application is **unavailable** Saturday from 6 to 8 AM.']); App\\Domains\\Support\\Models\\Announcement::factory()->create(); return true;",
            );

            visit("/app");
            cy.get('[data-testid="announcement-banner"]').should("be.visible");
            cy.checkAxeViolations();

            visit("/app/announcements");
            cy.contains("Scheduled maintenance");
            cy.checkAxeViolations();
        });

        it("the OAuth consent screen has no violations", () => {
            cy.php(
                "[, $client] = resolve(App\\Domains\\Auth\\Actions\\Applications\\RegisterOAuthApplication::class)('Reporting Tool', ['http://localhost:4100/callback'], false, ['view-users'], description: 'Weekly enrollment reports.'); return $client->getKey();",
            ).then((clientId) => {
                const query = new URLSearchParams({
                    client_id: String(clientId),
                    redirect_uri: "http://localhost:4100/callback",
                    response_type: "code",
                    scope: "view-users",
                    state: "axe",
                    code_challenge:
                        "E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM",
                    code_challenge_method: "S256",
                });

                visit(`/oauth/authorize?${query.toString()}`);
                cy.contains("Reporting Tool Wants to Access Your");
                cy.checkAxeViolations();
            });
        });
    });

    context("Administrators", () => {
        beforeEach(() => {
            cy.loginAsSuperAdmin();
        });

        [
            "/administration",
            "/administration/overview",
            "/administration/users",
            "/administration/roles",
            "/administration/audits",
            "/administration/login-records",
            "/administration/support-tickets",
            "/administration/api",
            "/administration/api/requests",
            "/administration/api/applications",
            "/administration/api/mcp-clients",
            "/administration/roles/create",
            "/administration/roles/activity",
            "/administration/announcements",
            "/administration/announcements/create",
            // The super administrator holds CreatePersonalAccessTokens; generic users don't see the page.
            "/app/account/access-tokens",
        ].forEach((path) => {
            it(`${path} has no violations`, () => {
                visit(path);
                cy.checkAxeViolations();
            });
        });

        // Approving an MCP client needs UseMcp, which the super administrator holds.
        it("the consent screen for a self-registered MCP client has no violations", () => {
            cy.request("POST", "/oauth/register", {
                client_name: "Claude Code",
                redirect_uris: ["http://localhost:4100/callback"],
            }).then(({ body }) => {
                const query = new URLSearchParams({
                    client_id: String(body.client_id),
                    redirect_uri: "http://localhost:4100/callback",
                    response_type: "code",
                    scope: "mcp:use",
                    state: "axe",
                    code_challenge:
                        "E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM",
                    code_challenge_method: "S256",
                });

                visit(`/oauth/authorize?${query.toString()}`);
                cy.contains("Unverified AI Client");
                cy.checkAxeViolations();
            });
        });

        // Record pages, each on a record made for it.
        (
            [
                [
                    "a NetID user",
                    "App\\Domains\\User\\Models\\User::factory()->create()",
                    (id) => `/administration/users/${id}`,
                ],
                [
                    "an API user",
                    "App\\Domains\\User\\Models\\User::factory()->api()->create()",
                    (id) => `/administration/users/${id}`,
                ],
                [
                    "a role",
                    "App\\Domains\\Auth\\Models\\Role::factory()->create()",
                    (id) => `/administration/roles/${id}`,
                ],
                [
                    "a role's edit page",
                    "App\\Domains\\Auth\\Models\\Role::factory()->create()",
                    (id) => `/administration/roles/${id}/edit`,
                ],
                [
                    "a role's history",
                    "App\\Domains\\Auth\\Models\\Role::factory()->create()",
                    (id) => `/administration/roles/${id}/history`,
                ],
                [
                    "an announcement's edit page",
                    "App\\Domains\\Support\\Models\\Announcement::factory()->create()",
                    (id) => `/administration/announcements/${id}/edit`,
                ],
                [
                    "a support ticket",
                    "App\\Domains\\Support\\Models\\SupportTicket::factory()->create()",
                    (id) => `/administration/support-tickets/${id}`,
                ],
            ] as [string, string, (id: string) => string][]
        ).forEach(([name, factory, path]) => {
            it(`${name} has no violations`, () => {
                cy.php(`return ${factory}->getKey();`).then((id) => {
                    visit(path(id));
                    cy.checkAxeViolations();
                });
            });
        });

        it("an audit record has no violations", () => {
            cy.php(
                "$user = App\\Domains\\User\\Models\\User::query()->firstOrFail(); $user->forceFill(['first_name' => 'Accessibility'])->save(); return App\\Domains\\User\\Models\\Audit::query()->latest('id')->value('id');",
            ).then((id) => {
                visit(`/administration/audits/${id}`);

                // The @pierre/diffs viewer renders syntax colors on its change backgrounds inside a shadow
                // DOM, and none of its themes meet color contrast there.
                cy.checkAxeViolations(["diffs-container"]);
            });
        });
    });
});
