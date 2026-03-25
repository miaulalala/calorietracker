/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { vi, beforeEach } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

beforeEach(() => {
	setActivePinia(createPinia())
})

vi.mock('@nextcloud/l10n', () => ({
	translate: vi.fn((app, str) => str),
	translatePlural: vi.fn((app, singular, plural, count) => count === 1 ? singular : plural),
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn().mockImplementation((app, key, fallback) => fallback),
}))

window._oc_webroot = ''

global.OC = {
	requestToken: 'test-token',
	coreApps: ['core'],
	config: { modRewriteWorking: true },
	dialogs: {},
	isUserAdmin: () => true,
	getLanguage: () => 'en-GB',
	getLocale: () => 'en_GB',
	PERMISSION_NONE: 0,
	PERMISSION_READ: 1,
	PERMISSION_UPDATE: 2,
	PERMISSION_CREATE: 4,
	PERMISSION_DELETE: 8,
	PERMISSION_SHARE: 16,
	PERMISSION_ALL: 31,
}
global.OCA = {}
global.OCP = {
	Accessibility: {
		disableKeyboardShortcuts: () => false,
	},
}

global.IntersectionObserver = vi.fn(class {

	observe = vi.fn()
	unobserve = vi.fn()
	disconnect = vi.fn()

})

global.ResizeObserver = vi.fn(class {

	observe = vi.fn()
	unobserve = vi.fn()
	disconnect = vi.fn()

})

// Suppress debug noise in test output
console.debug = vi.fn()
