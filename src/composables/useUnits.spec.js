/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useUnits } from './useUnits.js'
import { useSettingsStore } from '../stores/settings.js'

describe('useUnits', () => {
	let store

	beforeEach(() => {
		setActivePinia(createPinia())
		store = useSettingsStore()
	})

	describe('energy', () => {
		it('displays kcal unchanged when energyUnit is kcal', () => {
			store.energyUnit = 'kcal'
			const { displayEnergy, energyLabel } = useUnits()
			expect(displayEnergy(500)).toBe(500)
			expect(energyLabel.value).toBe('kcal')
		})

		it('converts kcal to kJ when energyUnit is kj', () => {
			store.energyUnit = 'kj'
			const { displayEnergy, energyLabel, toKcal } = useUnits()
			expect(displayEnergy(100)).toBe(Math.round(100 * 4.184))
			expect(energyLabel.value).toBe('kJ')
			// Round-trip
			expect(toKcal(displayEnergy(100))).toBe(100)
		})

		it('handles null energy', () => {
			const { displayEnergy, toKcal } = useUnits()
			expect(displayEnergy(null)).toBeNull()
			expect(toKcal(null)).toBeNull()
		})
	})

	describe('weight (food amounts)', () => {
		it('displays grams unchanged in metric', () => {
			store.measurementSystem = 'metric'
			const { displayWeight, weightLabel } = useUnits()
			expect(displayWeight(100)).toBe(100)
			expect(weightLabel.value).toBe('g')
		})

		it('converts grams to oz in imperial', () => {
			store.measurementSystem = 'imperial'
			const { displayWeight, toGrams, weightLabel } = useUnits()
			expect(weightLabel.value).toBe('oz')
			// 28.3495g = 1 oz
			expect(displayWeight(28.3495)).toBeCloseTo(1.0, 0)
			// Round-trip: 100g → oz → grams (small rounding error expected)
			const oz = displayWeight(100)
			expect(toGrams(oz)).toBeCloseTo(100, -1)
		})
	})

	describe('per 100g / per oz', () => {
		it('passes through in metric', () => {
			store.measurementSystem = 'metric'
			const { displayPer100g, toPer100g, perWeightLabel } = useUnits()
			expect(displayPer100g(89)).toBe(89)
			expect(toPer100g(89)).toBe(89)
			expect(perWeightLabel.value).toBe('per 100g')
		})

		it('converts per-100g to per-oz in imperial', () => {
			store.measurementSystem = 'imperial'
			const { displayPer100g, toPer100g, perWeightLabel } = useUnits()
			expect(perWeightLabel.value).toBe('per oz')
			// 89 kcal/100g → ~25 kcal/oz
			const perOz = displayPer100g(89)
			expect(perOz).toBe(Math.round(89 * 28.3495 / 100))
			// Round-trip (small rounding error expected with integer conversion)
			expect(toPer100g(perOz)).toBeCloseTo(89, -1)
		})
	})

	describe('body measurements', () => {
		it('passes cm/kg through in metric', () => {
			store.measurementSystem = 'metric'
			const { displayHeight, displayBodyWeight, heightLabel, bodyWeightLabel } = useUnits()
			expect(displayHeight(180)).toBe(180)
			expect(displayBodyWeight(75)).toBe(75)
			expect(heightLabel.value).toBe('cm')
			expect(bodyWeightLabel.value).toBe('kg')
		})

		it('converts cm to ft/in in imperial', () => {
			store.measurementSystem = 'imperial'
			const { displayHeight, toCm, heightLabel } = useUnits()
			expect(heightLabel.value).toBe('ft/in')
			const result = displayHeight(180)
			expect(result.feet).toBe(5)
			expect(result.inches).toBe(11)
			// Round-trip
			expect(toCm(result)).toBeCloseTo(180, 0)
		})

		it('converts kg to lbs in imperial', () => {
			store.measurementSystem = 'imperial'
			const { displayBodyWeight, toKg, bodyWeightLabel } = useUnits()
			expect(bodyWeightLabel.value).toBe('lbs')
			const lbs = displayBodyWeight(75)
			expect(lbs).toBeCloseTo(165.3, 0)
			expect(toKg(lbs)).toBeCloseTo(75, 0)
		})
	})

	describe('entry helpers', () => {
		it('calculates entry energy in display units', () => {
			store.energyUnit = 'kj'
			const { entryEnergy } = useUnits()
			// 89 kcal/100g * 120g = 106.8 → 107 kcal → 448 kJ
			expect(entryEnergy(89, 120)).toBe(Math.round(107 * 4.184))
		})

		it('calculates entry macro grams in metric', () => {
			store.measurementSystem = 'metric'
			const { entryMacroGrams } = useUnits()
			// 12g protein/100g * 250g = 30g
			expect(entryMacroGrams(12, 250)).toBe(30)
		})

		it('converts entry macro grams to oz in imperial with rounding', () => {
			store.measurementSystem = 'imperial'
			const { entryMacroGrams } = useUnits()
			// 12g/100g * 250g = 30g → 30 / 28.3495 ≈ 1.058 → rounded to 1.1
			expect(entryMacroGrams(12, 250)).toBeCloseTo(1.1, 1)
		})
	})
})
