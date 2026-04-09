/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, test, vi } from 'vitest'

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: vi.fn(),
	},
}))

vi.mock('@nextcloud/router', () => ({
	generateUrl: vi.fn((path) => `/nc${path}`),
}))

const { default: axios } = await import('@nextcloud/axios')
const { default: api } = await import('./OpenFoodFactsApi.js')

describe('OpenFoodFactsApi', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	test('search GETs /off/search with query param', async () => {
		axios.get.mockResolvedValue({ data: [] })
		await api.search('banana')
		expect(axios.get).toHaveBeenCalledWith(
			'/nc/apps/calorietracker/off/search',
			{ params: { query: 'banana' } },
		)
	})

	test('search returns the response data', async () => {
		const results = [
			{ source: 'off', name: 'Banana', caloriesPer100g: 89, proteinPer100g: 1, carbsPer100g: 23, fatPer100g: 0 },
		]
		axios.get.mockResolvedValue({ data: results })
		const result = await api.search('banana')
		expect(result).toEqual(results)
	})

	test('search propagates errors', async () => {
		axios.get.mockRejectedValue(new Error('network error'))
		await expect(api.search('banana')).rejects.toThrow('network error')
	})
})
