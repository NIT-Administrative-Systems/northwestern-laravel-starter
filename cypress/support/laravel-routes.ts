type LaravelRouteParameters = Record<string, JsonPrimitive>;

Cypress.Laravel = {
    routes: {},

    route: (name: string, parameters: LaravelRouteParameters = {}) => {
        assert(
            Cypress.Laravel.routes.hasOwnProperty(name),
            `Laravel route "${name}" does not exist.`,
        );

        return ((uri) => {
            Object.keys(parameters).forEach((parameter) => {
                uri = uri.replace(
                    new RegExp(`{${parameter}}`),
                    String(parameters[parameter]),
                );
            });

            return uri;
        })(Cypress.Laravel.routes[name].uri);
    },
};
