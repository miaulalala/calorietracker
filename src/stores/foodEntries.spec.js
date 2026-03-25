/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, test, vi } from 'vitest'
import { useFoodEntriesStore } from './foodEntries.js'

vi.mock('../services/FoodEntryApi.js', () => ({
	default: {
		getEntries: vi.fn(),
		createEntry: vi.fn(),
		updateEntry: vi.fn(),
		deleteEntry: vi.fn(),
		getSummaries: vi.fn(),
	},
}))

describe('foodEntries store', () => {
	let store
	let api

	beforeEach(async () => {
		store = useFoodEntriesStore()
		const module = await import('../services/FoodEntryApi.js')
		api = module.default
		vi.clearAllMocks()
		api.getSummaries.mockResolvedValue([])
	})

	// ─── Getters ───────────────────────────────────────────────────────────────

	describe('totalCalories', () => {
		test('returns 0 with no entries', () => {
			expect(store.totalCalories).toBe(0)
		})

		test('sums calories across all entries', () => {
			store.entries = [
				{ caloriesPer100g: 100, amountGrams: 200 }, // 200 kcal
				{ caloriesPer100g: 50, amountGrams: 100 }, // 50 kcal
			]
			expect(store.totalCalories).toBe(250)
		})

		test('rounds each entry to the nearest integer', () => {
			store.entries = [{ caloriesPer100g: 333, amountGrams: 100 }] // 333 kcal
			expect(store.totalCalories).toBe(333)
		})
	})

	describe('macroTotals', () => {
		test('returns null when there are no entries', () => {
			expect(store.macroTotals).toBeNull()
		})

		test('calculates grams and kcal for all macros', () => {
			store.entries = [{
				proteinPer100g: 20,
				carbsPer100g: 50,
				fatPer100g: 10,
				amountGrams: 100,
				caloriesPer100g: 370,
			}]
			const result = store.macroTotals
			expect(result.protein.grams).toBe(20)
			expect(result.protein.kcal).toBe(80) // 20g × 4
			expect(result.carbs.grams).toBe(50)
			expect(result.carbs.kcal).toBe(200) // 50g × 4
			expect(result.fat.grams).toBe(10)
			expect(result.fat.kcal).toBe(90) // 10g × 9
		})

		test('calculates percentage of total calories', () => {
			// 25g protein = 100 kcal, total = 100 kcal → 100%
			store.entries = [{
				proteinPer100g: 25,
				carbsPer100g: 0,
				fatPer100g: 0,
				amountGrams: 100,
				caloriesPer100g: 100,
			}]
			const result = store.macroTotals
			expect(result.protein.pct).toBe(100)
			expect(result.carbs.pct).toBe(0)
			expect(result.fat.pct).toBe(0)
		})

		test('treats null macro values as 0', () => {
			store.entries = [{
				proteinPer100g: null,
				carbsPer100g: null,
				fatPer100g: null,
				amountGrams: 100,
				caloriesPer100g: 100,
			}]
			const result = store.macroTotals
			expect(result.protein.grams).toBe(0)
			expect(result.carbs.grams).toBe(0)
			expect(result.fat.grams).toBe(0)
		})
	})

	describe('entriesByMealType', () => {
		test('always returns all four meal type keys in order', () => {
			const groups = store.entriesByMealType
			expect(Object.keys(groups)).toEqual(['breakfast', 'lunch', 'dinner', 'snack'])
		})

		test('groups entries into the correct meal type bucket', () => {
			store.entries = [
				{ id: 1, mealType: 'breakfast' },
				{ id: 2, mealType: 'lunch' },
				{ id: 3, mealType: 'breakfast' },
			]
			const groups = store.entriesByMealType
			expect(groups.breakfast).toHaveLength(2)
			expect(groups.lunch).toHaveLength(1)
			expect(groups.dinner).toHaveLength(0)
			expect(groups.snack).toHaveLength(0)
		})
	})

	// ─── Actions ───────────────────────────────────────────────────────────────

	describe('actions', () => {
		test('fetchEntries fetches for currentDate and sets entries', async () => {
			const entries = [{ id: 1 }]
			api.getEntries.mockResolvedValue(entries)
			store.currentDate = '2026-03-25'
			await store.fetchEntries()
			expect(api.getEntries).toHaveBeenCalledWith('2026-03-25')
			expect(store.entries).toEqual(entries)
		})

		test('addEntry appends the new entry and refreshes summaries', async () => {
			const entry = { id: 1, foodName: 'Apple' }
			api.createEntry.mockResolvedValue(entry)
			await store.addEntry({ foodName: 'Apple' })
			expect(store.entries).toContainEqual(entry)
			expect(api.getSummaries).toHaveBeenCalledOnce()
		})

		test('updateEntry replaces the matching entry by id', async () => {
			store.entries = [{ id: 7, foodName: 'Banana' }]
			const updated = { id: 7, foodName: 'Mango' }
			api.updateEntry.mockResolvedValue(updated)
			await store.updateEntry({ id: 7, foodName: 'Mango' })
			expect(api.updateEntry).toHaveBeenCalledWith(7, { foodName: 'Mango' })
			expect(store.entries[0].foodName).toBe('Mango')
			expect(api.getSummaries).toHaveBeenCalledOnce()
		})

		test('deleteEntry removes the entry and refreshes summaries', async () => {
			store.entries = [{ id: 1 }, { id: 2 }, { id: 3 }]
			api.deleteEntry.mockResolvedValue(undefined)
			await store.deleteEntry(2)
			expect(api.deleteEntry).toHaveBeenCalledWith(2)
			expect(store.entries.map(e => e.id)).toEqual([1, 3])
			expect(api.getSummaries).toHaveBeenCalledOnce()
		})

		test('setDate updates currentDate and fetches entries', async () => {
			api.getEntries.mockResolvedValue([])
			await store.setDate('2026-03-25')
			expect(store.currentDate).toBe('2026-03-25')
			expect(api.getEntries).toHaveBeenCalledWith('2026-03-25')
		})

		test('fetchSummaries indexes summaries by date', async () => {
			api.getSummaries.mockResolvedValue([
				{ date: '2026-03-24', totalKcal: 1800, itemCount: 3 },
				{ date: '2026-03-25', totalKcal: 2000, itemCount: 4 },
			])
			await store.fetchSummaries()
			expect(store.daySummaries['2026-03-24'].totalKcal).toBe(1800)
			expect(store.daySummaries['2026-03-25'].totalKcal).toBe(2000)
		})

		test('openAddModal sets editingEntry and addModalOpen', () => {
			store.openAddModal({ id: 5 })
			expect(store.addModalOpen).toBe(true)
			expect(store.editingEntry).toEqual({ id: 5 })
		})

		test('openAddModal defaults editingEntry to null', () => {
			store.openAddModal()
			expect(store.addModalOpen).toBe(true)
			expect(store.editingEntry).toBeNull()
		})

		test('closeAddModal resets modal state', () => {
			store.addModalOpen = true
			store.editingEntry = { id: 1 }
			store.closeAddModal()
			expect(store.addModalOpen).toBe(false)
			expect(store.editingEntry).toBeNull()
		})
	})
})
