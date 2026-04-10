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
const { default: api } = await import('./FoodItemApi.js')

describe('FoodItemApi', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	test('getFrequent GETs /food-items with sort and limit params', async () => {
		const items = [{ id: 1, name: 'Banana' }]
		axios.get.mockResolvedValue({ data: items })

		const result = await api.getFrequent(5)

		expect(axios.get).toHaveBeenCalledWith(
			'/nc/apps/calorietracker/food-items',
			{ params: { sort: 'frequent', limit: 5 } },
		)
		expect(result).toEqual(items)
	})

	test('getFrequent uses default limit of 8', async () => {
		axios.get.mockResolvedValue({ data: [] })

		await api.getFrequent()

		expect(axios.get).toHaveBeenCalledWith(
			'/nc/apps/calorietracker/food-items',
			{ params: { sort: 'frequent', limit: 8 } },
		)
	})
})
