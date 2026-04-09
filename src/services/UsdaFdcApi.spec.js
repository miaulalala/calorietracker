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
const { default: api } = await import('./UsdaFdcApi.js')

describe('UsdaFdcApi', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	test('search GETs /usda/search with query param', async () => {
		axios.get.mockResolvedValue({ data: [] })
		await api.search('chicken')
		expect(axios.get).toHaveBeenCalledWith(
			'/nc/apps/calorietracker/usda/search',
			{ params: { query: 'chicken' } },
		)
	})

	test('search returns the response data', async () => {
		const results = [
			{ source: 'usda_fdc', name: 'Chicken, broilers or fryers, breast, meat only, cooked, roasted', caloriesPer100g: 165, proteinPer100g: 31, carbsPer100g: 0, fatPer100g: 4 },
		]
		axios.get.mockResolvedValue({ data: results })
		const result = await api.search('chicken')
		expect(result).toEqual(results)
	})

	test('search propagates errors', async () => {
		axios.get.mockRejectedValue(new Error('network error'))
		await expect(api.search('chicken')).rejects.toThrow('network error')
	})
})
