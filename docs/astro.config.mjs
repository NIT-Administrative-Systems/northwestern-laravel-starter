// @ts-check
import { defineNorthwesternConfig } from '@nu-appdev/northwestern-starlight-theme/config';
import starlightAgentDocs from '@nu-appdev/starlight-agent-docs';
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
				label: 'Building Your Application',
				items: [{ autogenerate: { directory: 'building' } }],
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
		// A Markdown copy of every page, and /llms.txt, for coding agents. Registered last, after
		// anything that overrides EditLink, so its "View as Markdown" link is kept.
		starlightAgentDocs({
			guidance:
				'Start with Getting Started to create an application, then Building Your Application for where code goes. Features and Northwestern Integrations explain what the starter ships and how to configure it. An application built from the starter also has an AGENTS.md in its repository root with the rules for working in it.',
		}),
	],
});
