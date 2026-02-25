const bundledLanguagesInfo = [
    {
        id: "json",
        name: "JSON",
        import: () => import("@shikijs/langs/json"),
    },
];

const bundledLanguages = Object.fromEntries(
    bundledLanguagesInfo.map((language) => [language.id, language.import]),
);

const bundledLanguagesAlias = {};

const bundledLanguagesBase = Object.fromEntries(
    bundledLanguagesInfo.map((language) => [language.id, language.import]),
);

export {
    bundledLanguages,
    bundledLanguagesAlias,
    bundledLanguagesBase,
    bundledLanguagesInfo,
};
