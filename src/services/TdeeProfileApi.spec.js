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
const { default: api } = await import('./TdeeProfileApi.js')

describe('TdeeProfileApi', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	test('get GETs /tdee-profile and returns data', async () => {
		const profile = { sex: 'female', age: 30, height: 165, weight: 60, activity: 'moderate', goal: 'maintain' }
		axios.get.mockResolvedValue({ data: profile })
		const result = await api.get()
		expect(axios.get).toHaveBeenCalledWith('/nc/apps/calorietracker/tdee-profile')
		expect(result).toEqual(profile)
	})

	test('get returns null when no profile is stored', async () => {
		axios.get.mockResolvedValue({ data: null })
		const result = await api.get()
		expect(result).toBeNull()
	})

	test('save PUTs /tdee-profile with the profile and returns data', async () => {
		const profile = { sex: 'male', age: 25, height: 180, weight: 80, activity: 'light', goal: 'lose' }
		axios.put.mockResolvedValue({ data: profile })
		const result = await api.save(profile)
		expect(axios.put).toHaveBeenCalledWith('/nc/apps/calorietracker/tdee-profile', profile)
		expect(result).toEqual(profile)
	})
})
