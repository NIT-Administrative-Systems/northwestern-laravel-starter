// @ts-check
import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';
import starlightLinksValidator from 'starlight-links-validator';

// https://astro.build/config
export default defineConfig({
	integrations: [
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
            ],
			social: {
				github: 'https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter',
			},
			sidebar: [
                {
                    label: 'Getting Started',
                    autogenerate: { directory: 'getting-started' },
                },
                {
                    label: 'Architecture',
                    autogenerate: { directory: 'architecture' },
                },
			],
            plugins: [
                starlightLinksValidator(),
            ]
		}),
	],
});
