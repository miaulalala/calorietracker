/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, test, vi } from 'vitest'

vi.mock('../services/UsdaFdcApi.js', () => ({
	default: {
		search: vi.fn(),
		batchSearch: vi.fn(),
	},
}))

const { parseIngredient, estimateRecipeNutrition } = await import('./ingredientParser.js')
const { default: usdaApi } = await import('../services/UsdaFdcApi.js')

describe('parseIngredient', () => {
	test('parses quantity + unit + name', () => {
		const result = parseIngredient('200g chicken breast')
		expect(result).toEqual({
			quantity: 200,
			unit: 'g',
			name: 'chicken breast',
			estimatedGrams: 200,
		})
	})

	test('parses fraction quantity', () => {
		const result = parseIngredient('1/2 cup flour')
		expect(result).toEqual({
			quantity: 0.5,
			unit: 'cup',
			name: 'flour',
			estimatedGrams: 120,
		})
	})

	test('parses mixed number with unicode fraction', () => {
		const result = parseIngredient('1½ cups sugar')
		expect(result).toEqual({
			quantity: 1.5,
			unit: 'cups',
			name: 'sugar',
			estimatedGrams: 360,
		})
	})

	test('parses quantity without unit (assumes 100g per piece)', () => {
		const result = parseIngredient('2 eggs')
		expect(result).toEqual({
			quantity: 2,
			unit: null,
			name: 'eggs',
			estimatedGrams: 200,
		})
	})

	test('handles plain name with no quantity', () => {
		const result = parseIngredient('salt')
		expect(result).toEqual({
			quantity: null,
			unit: null,
			name: 'salt',
			estimatedGrams: 100,
		})
	})

	test('strips parenthetical notes', () => {
		const result = parseIngredient('100g butter (softened)')
		expect(result).toEqual({
			quantity: 100,
			unit: 'g',
			name: 'butter',
			estimatedGrams: 100,
		})
	})

	test('parses tablespoon unit', () => {
		const result = parseIngredient('2 tbsp olive oil')
		expect(result).toEqual({
			quantity: 2,
			unit: 'tbsp',
			name: 'olive oil',
			estimatedGrams: 30,
		})
	})

	test('parses ounces', () => {
		const result = parseIngredient('8 oz cream cheese')
		expect(result.unit).toBe('oz')
		expect(result.estimatedGrams).toBe(227)
	})
})

describe('estimateRecipeNutrition', () => {
	test('sums nutrition per serving and returns null for missing macros', async () => {
		usdaApi.batchSearch.mockResolvedValue([
			{
				caloriesPer100g: 200,
				proteinPer100g: 20,
				carbsPer100g: null,
				fatPer100g: 10,
			},
			{
				caloriesPer100g: 100,
				proteinPer100g: 5,
				carbsPer100g: null,
				fatPer100g: 3,
			},
		])

		const result = await estimateRecipeNutrition(
			['200g chicken', '100g rice'],
			'2',
		)

		expect(result.caloriesPerServing).toBeGreaterThan(0)
		expect(result.proteinPerServing).toBeGreaterThan(0)
		expect(result.carbsPerServing).toBeNull()
		expect(result.fatPerServing).toBeGreaterThan(0)
		// 200g chicken + 100g rice = 300g total / 2 servings = 150g per serving
		expect(result.gramsPerServing).toBe(150)
		expect(result.servingSize).toBe('1/2 of recipe')
	})

	test('throws when no ingredients match', async () => {
		usdaApi.batchSearch.mockResolvedValue([null])

		await expect(
			estimateRecipeNutrition(['unknown thing'], '1'),
		).rejects.toThrow('No ingredients could be matched in the food database')
	})

	test('throws when batch search fails', async () => {
		usdaApi.batchSearch.mockRejectedValue(new Error('network error'))

		await expect(
			estimateRecipeNutrition(['chicken'], '1'),
		).rejects.toThrow('No ingredients could be matched in the food database')
	})
})
