/**
 * SPDX-FileCopyrightText: 2026 Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Vue from 'vue'
import Vuex from 'vuex'
import foodEntries from './foodEntries.js'

Vue.use(Vuex)

export default new Vuex.Store({
	modules: {
		foodEntries,
	},
})
