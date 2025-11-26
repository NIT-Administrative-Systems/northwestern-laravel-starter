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
                    label: 'About',
                    autogenerate: { directory: 'about' },
                },
				{
					label: 'Guides',
                    autogenerate: { directory: 'guides' },
				},
				{
					label: 'Reference',
					autogenerate: { directory: 'reference' },
				},
			],
            plugins: [
                starlightLinksValidator(),
            ]
		}),
	],
});
