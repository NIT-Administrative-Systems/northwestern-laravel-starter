// @ts-check
import { defineNorthwesternConfig } from '@nu-appdev/northwestern-starlight-theme/config';
import starlightLinksValidator from 'starlight-links-validator';
import starlightOpenAPI, { openAPISidebarGroups } from 'starlight-openapi';

// https://astro.build/config
export default defineNorthwesternConfig({
	site: 'https://laravel-starter.entapp.northwestern.edu',
	theme: { homepage: { showTitle: false, imageWidth: '750px' } },
	starlight: {
		title: 'Northwestern Laravel Starter',
		editLink: {
			baseUrl:
				'https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter/edit/main/docs/',
		},
		social: [
			{
				label: 'GitHub',
				icon: 'github',
				href: 'https://github.com/NIT-Administrative-Systems/northwestern-laravel-starter',
			},
		],
		sidebar: [
			{
				label: 'Getting Started',
				items: [{ autogenerate: { directory: 'getting-started' } }],
			},
			{
				label: 'Architecture',
				items: [{ autogenerate: { directory: 'architecture' } }],
			},
			{
				label: 'Features',
				items: [{ autogenerate: { directory: 'features' } }],
			},
			{
				label: 'Northwestern Integrations',
				items: [{ autogenerate: { directory: 'northwestern-integrations' } }],
			},
			{
				label: 'Guides',
				items: [{ autogenerate: { directory: 'guides' } }],
			},
			{
				label: 'Reference',
				items: [{ autogenerate: { directory: 'reference' } }],
			},
			...openAPISidebarGroups,
		],
	},
	plugins: [
		starlightOpenAPI([
			{
				base: 'api',
				schema: './schemas/api-schema.yaml',
				sidebar: {
					label: 'API Specification',
					operations: {
						badges: true,
					},
				},
			},
		]),
		starlightLinksValidator({
			exclude: ['/api/**'],
		}),
	],
});
