// @ts-check
import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';
import starlightLinksValidator from 'starlight-links-validator';
import mermaid from 'astro-mermaid';
import starlightOpenAPI, { openAPISidebarGroups } from 'starlight-openapi';


// https://astro.build/config
export default defineConfig({
	integrations: [
        mermaid({
            theme: 'default',
            autoTheme: true,
        }),
		starlight({
			title: 'Northwestern Laravel Starter',
            components: {
              EditLink: './src/components/ConditionalEditLink.astro',
              Hero: './src/components/Hero.astro'
            },
            editLink: {
                baseUrl: 'https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/edit/main/docs/',
            },
            favicon: '/favicon.ico',
            customCss: [
              './src/styles/custom.css',
                './src/styles/layout.css',
                '@fontsource/poppins/400.css',
                '@fontsource/poppins/600.css',
            ],
			social: [
                { label: 'GitHub', icon: 'github', href: 'https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter' },
            ],
            sidebar: [
                {
                    label: '🚀 Getting Started',
                    autogenerate: { directory: 'getting-started' },
                },
                {
                    label: '🏗️ Architecture',
                    autogenerate: { directory: 'architecture' },
                },
                {
                    label: '✨ Features',
                    autogenerate: { directory: 'features' },
                },
                {
                    label: '📖 Guides',
                    autogenerate: { directory: 'guides' },
                },
                {
                    label: '📋 Reference',
                    autogenerate: { directory: 'reference' },
                },
                ...openAPISidebarGroups,
            ],
            plugins: [
                starlightOpenAPI([
                    {
                        base: 'api',
                        schema: './schemas/api-schema.yaml',
                        sidebar: {
                            label: '📘 API Specification',
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
