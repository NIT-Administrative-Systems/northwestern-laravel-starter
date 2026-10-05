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

        it("a changelog entry has no violations", () => {
            cy.php(
                "return App\\Domains\\Support\\Models\\Changelog::factory()->create(['body' => \"### Changes\\n\\n- A change\"])->slug;",
            ).then((slug) => {
                visit(`/support/changelog/${slug}`);

                // Entries are written with ### headings, which follow the index page's <h2> titles
                // but skip a level after an entry page's <h1>.
                cy.checkAxeViolations([
                    ".fi-prose h1",
                    ".fi-prose h2",
                    ".fi-prose h3",
                    ".fi-prose h4",
                ]);
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
        ].forEach((path) => {
            it(`${path} has no violations`, () => {
                visit(path);
                cy.checkAxeViolations();
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
