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
const { default: api } = await import('./CookbookApi.js')

describe('CookbookApi', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	test('search GETs /cookbook/search with query param', async () => {
		const recipes = [{ id: 1, name: 'Pasta' }]
		axios.get.mockResolvedValue({ data: recipes })

		const result = await api.search('pasta')

		expect(axios.get).toHaveBeenCalledWith(
			'/nc/apps/calorietracker/cookbook/search',
			{ params: { query: 'pasta' } },
		)
		expect(result).toEqual(recipes)
	})

	test('getRecipe GETs /cookbook/recipes/:id', async () => {
		const recipe = { id: 42, name: 'Soup' }
		axios.get.mockResolvedValue({ data: recipe })

		const result = await api.getRecipe(42)

		expect(axios.get).toHaveBeenCalledWith('/nc/apps/calorietracker/cookbook/recipes/42')
		expect(result).toEqual(recipe)
	})

	test('updateNutrition PUTs /cookbook/recipes/:id/nutrition', async () => {
		axios.put.mockResolvedValue({ data: { status: 'ok' } })

		const nutrition = {
			calories: 250,
			protein: 10,
			carbs: 30,
			fat: 8,
			servingSize: '1/4 of recipe',
		}
		const result = await api.updateNutrition(42, nutrition)

		expect(axios.put).toHaveBeenCalledWith(
			'/nc/apps/calorietracker/cookbook/recipes/42/nutrition',
			nutrition,
		)
		expect(result).toEqual({ status: 'ok' })
	})
})
