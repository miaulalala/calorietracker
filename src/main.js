/**
 * SPDX-FileCopyrightText: 2026 Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Vue from 'vue'
import App from './App.vue'
import router from './router.js'
import store from './store/index.js'
import { translate, translatePlural } from '@nextcloud/l10n'

Vue.prototype.t = translate
Vue.prototype.n = translatePlural

new Vue({
	el: '#content',
	name: 'CalorieTracker',
	router,
	store,
	render: h => h(App),
})
