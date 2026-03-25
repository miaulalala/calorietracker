/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, test, vi } from 'vitest'
import { useSettingsStore } from './settings.js'

vi.mock('../services/SettingsApi.js', () => ({
	default: {
		getSettings: vi.fn(),
		saveSettings: vi.fn(),
	},
}))

describe('settings store', () => {
	let store
	let api

	beforeEach(async () => {
		store = useSettingsStore()
		const module = await import('../services/SettingsApi.js')
		api = module.default
		vi.clearAllMocks()
	})

	// ─── Actions ───────────────────────────────────────────────────────────────

	describe('fetchSettings', () => {
		test('fetches from api and updates all goal values', async () => {
			const data = { dailyCalorieGoal: 2200, dailyProteinGoal: 130, dailyCarbsGoal: 280, dailyFatGoal: 75 }
			api.getSettings.mockResolvedValue(data)
			await store.fetchSettings()
			expect(api.getSettings).toHaveBeenCalledOnce()
			expect(store.dailyCalorieGoal).toBe(2200)
			expect(store.dailyProteinGoal).toBe(130)
			expect(store.dailyCarbsGoal).toBe(280)
			expect(store.dailyFatGoal).toBe(75)
		})

		test('defaults missing values to 0', async () => {
			api.getSettings.mockResolvedValue({})
			await store.fetchSettings()
			expect(store.dailyCalorieGoal).toBe(0)
			expect(store.dailyProteinGoal).toBe(0)
			expect(store.dailyCarbsGoal).toBe(0)
			expect(store.dailyFatGoal).toBe(0)
		})
	})

	describe('saveSettings', () => {
		test('saves to api and updates state with response', async () => {
			const goals = { dailyCalorieGoal: 1800, dailyProteinGoal: 120, dailyCarbsGoal: 200, dailyFatGoal: 60 }
			api.saveSettings.mockResolvedValue(goals)
			await store.saveSettings(goals)
			expect(api.saveSettings).toHaveBeenCalledWith(goals)
			expect(store.dailyCalorieGoal).toBe(1800)
		})
	})

	describe('openSettings / closeSettings', () => {
		test('openSettings sets open to true', () => {
			store.openSettings()
			expect(store.open).toBe(true)
		})

		test('closeSettings sets open to false', () => {
			store.open = true
			store.closeSettings()
			expect(store.open).toBe(false)
		})
	})
})
