/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import api from '../services/SettingsApi.js'

export const useSettingsStore = defineStore('settings', {
	state: () => ({
		open: false,
		dailyCalorieGoal: 0,
		dailyProteinGoal: 0,
		dailyCarbsGoal: 0,
		dailyFatGoal: 0,
	}),

	actions: {
		async fetchSettings() {
			const data = await api.getSettings()
			this.dailyCalorieGoal = data.dailyCalorieGoal ?? 0
			this.dailyProteinGoal = data.dailyProteinGoal ?? 0
			this.dailyCarbsGoal = data.dailyCarbsGoal ?? 0
			this.dailyFatGoal = data.dailyFatGoal ?? 0
		},

		async saveSettings(goals) {
			const data = await api.saveSettings(goals)
			this.dailyCalorieGoal = data.dailyCalorieGoal ?? 0
			this.dailyProteinGoal = data.dailyProteinGoal ?? 0
			this.dailyCarbsGoal = data.dailyCarbsGoal ?? 0
			this.dailyFatGoal = data.dailyFatGoal ?? 0
		},

		openSettings() {
			this.open = true
		},

		closeSettings() {
			this.open = false
		},
	},
})
