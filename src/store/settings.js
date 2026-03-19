/**
 * SPDX-FileCopyrightText: 2026 Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import api from '../services/SettingsApi.js'

export default {
	namespaced: true,

	state: () => ({
		open: false,
		dailyCalorieGoal: 0,
		dailyProteinGoal: 0,
		dailyCarbsGoal: 0,
		dailyFatGoal: 0,
	}),

	mutations: {
		SET_GOALS(state, goals) {
			state.dailyCalorieGoal = goals.dailyCalorieGoal ?? 0
			state.dailyProteinGoal = goals.dailyProteinGoal ?? 0
			state.dailyCarbsGoal = goals.dailyCarbsGoal ?? 0
			state.dailyFatGoal = goals.dailyFatGoal ?? 0
		},
		SET_OPEN(state, open) {
			state.open = open
		},
	},

	actions: {
		async fetchSettings({ commit }) {
			const data = await api.getSettings()
			commit('SET_GOALS', data)
		},

		async saveSettings({ commit }, goals) {
			const data = await api.saveSettings(goals)
			commit('SET_GOALS', data)
		},

		openSettings({ commit }) {
			commit('SET_OPEN', true)
		},

		closeSettings({ commit }) {
			commit('SET_OPEN', false)
		},
	},
}
