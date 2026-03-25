/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, test, vi } from 'vitest'

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: vi.fn(),
		put: vi.fn(),
	},
}))

vi.mock('@nextcloud/router', () => ({
	generateUrl: vi.fn((path) => `/nc${path}`),
}))

const { default: axios } = await import('@nextcloud/axios')
const { default: api } = await import('./SettingsApi.js')

describe('SettingsApi', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	test('getSettings GETs /settings and returns data', async () => {
		const data = { dailyCalorieGoal: 2000, dailyProteinGoal: 150 }
		axios.get.mockResolvedValue({ data })
		const result = await api.getSettings()
		expect(axios.get).toHaveBeenCalledWith('/nc/apps/calorietracker/settings')
		expect(result).toEqual(data)
	})

	test('saveSettings PUTs /settings with goals payload and returns data', async () => {
		const goals = { dailyCalorieGoal: 1800, dailyProteinGoal: 120 }
		axios.put.mockResolvedValue({ data: goals })
		const result = await api.saveSettings(goals)
		expect(axios.put).toHaveBeenCalledWith('/nc/apps/calorietracker/settings', goals)
		expect(result).toEqual(goals)
	})
})
