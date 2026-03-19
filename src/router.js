/**
 * SPDX-FileCopyrightText: 2026 Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Vue from 'vue'
import Router from 'vue-router'
import DayView from './components/DayView.vue'
import SettingsView from './components/SettingsView.vue'

Vue.use(Router)

export default new Router({
	mode: 'hash',
	routes: [
		{
			path: '/',
			component: DayView,
		},
		{
			path: '/settings',
			component: SettingsView,
		},
	],
})
