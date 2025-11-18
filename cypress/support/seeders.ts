/**
 * This should mostly match DemoSeeder, but it's broken out here for a couple of reasons:
 *
 *  Calling the DemoSeeder means there's a 30s timeout for everything together, which is too short.
 *  Some DemoSeeders for local don't make sense for E2E, e.g. we don't need to load stakeholders
 *  if we're working with demo users.
 */

export default ["Sample\\DemoUserSeeder"];
