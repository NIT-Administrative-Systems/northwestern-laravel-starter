// @ts-check
import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';
import starlightLinksValidator from 'starlight-links-validator';
import starlightOpenAPI, { openAPISidebarGroups } from 'starlight-openapi';
import northwesternTheme from '@nu-appdev/northwestern-starlight-theme';

// https://astro.build/config
export default defineConfig({
	integrations: [
		starlight({
			title: 'Northwestern Laravel Starter',
            editLink: {
                baseUrl: 'https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/edit/main/docs/',
            },
			social: [
                { label: 'GitHub', icon: 'github', href: 'https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter' },
            ],
            sidebar: [
                {
                    label: 'Getting Started',
                    autogenerate: { directory: 'getting-started' },
                },
                {
                    label: 'Architecture',
                    autogenerate: { directory: 'architecture' },
                },
                {
                    label: 'Features',
                    autogenerate: { directory: 'features' },
                },
                {
                    label: 'Northwestern Integrations',
                    autogenerate: { directory: 'northwestern-integrations' },
                },
                {
                    label: 'Guides',
                    autogenerate: { directory: 'guides' },
                },
                {
                    label: 'Reference',
                    autogenerate: { directory: 'reference' },
                },
                ...openAPISidebarGroups,
            ],
            plugins: [
                northwesternTheme({ homepage: { showTitle: false, imageWidth: "750px" } }),
                starlightOpenAPI([
                    {
                        base: 'api',
                        schema: './schemas/api-schema.yaml',
                        sidebar: {
                            label: 'API Specification',
                          operations: {
                              badges: true,
                          }
                        },
                    }
                ]),
                starlightLinksValidator({
                    exclude: [
                        '/api/**',
                    ],
                }),
            ]
		}),
	],
});
