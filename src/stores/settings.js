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
		energyUnit: 'kcal',
		measurementSystem: 'metric',
		showWeightOnDayView: false,
	}),

	getters: {
		isImperial: (state) => state.measurementSystem === 'imperial',
		isKj: (state) => state.energyUnit === 'kj',
	},

	actions: {
		async fetchSettings() {
			const data = await api.getSettings()
			this.dailyCalorieGoal = data.dailyCalorieGoal ?? 0
			this.dailyProteinGoal = data.dailyProteinGoal ?? 0
			this.dailyCarbsGoal = data.dailyCarbsGoal ?? 0
			this.dailyFatGoal = data.dailyFatGoal ?? 0
			this.energyUnit = data.energyUnit ?? 'kcal'
			this.measurementSystem = data.measurementSystem ?? 'metric'
			this.showWeightOnDayView = data.showWeightOnDayView ?? false
		},

		async saveSettings(goals) {
			const data = await api.saveSettings(goals)
			this.dailyCalorieGoal = data.dailyCalorieGoal ?? 0
			this.dailyProteinGoal = data.dailyProteinGoal ?? 0
			this.dailyCarbsGoal = data.dailyCarbsGoal ?? 0
			this.dailyFatGoal = data.dailyFatGoal ?? 0
			this.energyUnit = data.energyUnit ?? 'kcal'
			this.measurementSystem = data.measurementSystem ?? 'metric'
			this.showWeightOnDayView = data.showWeightOnDayView ?? false
		},

		openSettings() {
			this.open = true
		},

		closeSettings() {
			this.open = false
		},
	},
})
