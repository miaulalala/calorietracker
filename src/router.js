/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createRouter, createWebHashHistory } from 'vue-router'
import DayView from './components/DayView.vue'

export default createRouter({
	history: createWebHashHistory(),
	routes: [
		{
			path: '/',
			component: DayView,
		},
	],
})
