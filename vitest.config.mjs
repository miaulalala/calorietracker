/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vitest/config'

export default defineConfig({
	plugins: [vue()],
	test: {
		include: ['src/**/*.{test,spec}.?(c|m)[jt]s?(x)'],
		server: {
			deps: {
				// Allow importing CSS from dependencies
				inline: ['@nextcloud/vue'],
			},
		},
		environment: 'jsdom',
		environmentOptions: {
			jsdom: {
				url: 'http://localhost',
			},
		},
		setupFiles: ['src/test-setup.js'],
		globalSetup: 'src/test-global-setup.js',
	},
})
