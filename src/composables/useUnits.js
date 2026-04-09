/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { computed } from 'vue'
import { storeToRefs } from 'pinia'
import { useSettingsStore } from '../stores/settings.js'

// Conversion constants
const KCAL_TO_KJ = 4.184
const GRAMS_PER_OZ = 28.3495
const KG_TO_LBS = 2.20462
const CM_PER_INCH = 2.54

/**
 * Composable providing reactive unit conversion helpers.
 *
 * All data is stored internally in metric (kcal, grams, cm, kg).
 * These helpers convert for display and input.
 */
export function useUnits() {
	const store = useSettingsStore()
	const { energyUnit, measurementSystem } = storeToRefs(store)

	const isImperial = computed(() => measurementSystem.value === 'imperial')
	const isKj = computed(() => energyUnit.value === 'kj')

	// ── Energy ──────────────────────────────────────────────────────

	const energyLabel = computed(() => isKj.value ? 'kJ' : 'kcal')

	/**
	 * Convert stored kcal to display energy value
	 * @param {number} kcal Energy in kilocalories
	 */
	function displayEnergy(kcal) {
		if (kcal == null) return null
		return isKj.value ? Math.round(kcal * KCAL_TO_KJ) : Math.round(kcal)
	}

	/**
	 * Convert user-entered energy back to kcal for storage
	 * @param {number} displayValue Energy in user's display unit
	 */
	function toKcal(displayValue) {
		if (displayValue == null) return null
		return isKj.value ? Math.round(displayValue / KCAL_TO_KJ) : Math.round(displayValue)
	}

	// ── Weight (food amounts) ───────────────────────────────────────

	const weightLabel = computed(() => isImperial.value ? 'oz' : 'g')
	const perWeightLabel = computed(() => isImperial.value ? 'per oz' : 'per 100g')

	/**
	 * Convert stored grams to display weight
	 * @param {number} grams Weight in grams
	 */
	function displayWeight(grams) {
		if (grams == null) return null
		return isImperial.value ? Math.round(grams / GRAMS_PER_OZ * 10) / 10 : Math.round(grams)
	}

	/**
	 * Convert user-entered weight back to grams for storage
	 * @param {number} displayValue Weight in user's display unit
	 */
	function toGrams(displayValue) {
		if (displayValue == null) return null
		return isImperial.value ? Math.round(displayValue * GRAMS_PER_OZ) : Math.round(displayValue)
	}

	/**
	 * Convert a "per 100g" nutritional value to display units.
	 * In metric: value per 100g (unchanged).
	 * In imperial: value per oz (= value_per_100g * 28.3495 / 100).
	 * @param {number} valuePer100g Nutritional value per 100g
	 */
	function displayPer100g(valuePer100g) {
		if (valuePer100g == null) return null
		return isImperial.value
			? Math.round(valuePer100g * GRAMS_PER_OZ / 100)
			: Math.round(valuePer100g)
	}

	/**
	 * Convert a user-entered "per oz" or "per 100g" value back to per-100g for storage.
	 * @param {number} displayValue Value in user's per-unit display format
	 */
	function toPer100g(displayValue) {
		if (displayValue == null) return null
		return isImperial.value
			? Math.round(displayValue * 100 / GRAMS_PER_OZ)
			: Math.round(displayValue)
	}

	// ── Body measurements (TDEE) ────────────────────────────────────

	const heightLabel = computed(() => isImperial.value ? 'ft/in' : 'cm')
	const bodyWeightLabel = computed(() => isImperial.value ? 'lbs' : 'kg')

	/**
	 * Convert stored cm to display height
	 * @param {number} cm Height in centimeters
	 */
	function displayHeight(cm) {
		if (cm == null) return null
		if (!isImperial.value) return cm
		const totalInches = Math.round(cm / CM_PER_INCH)
		const feet = Math.floor(totalInches / 12)
		const inches = totalInches % 12
		return { feet, inches }
	}

	/**
	 * Convert ft/in or cm input back to cm for storage
	 * @param {number|{feet: number, inches: number}} value Height in user's unit
	 */
	function toCm(value) {
		if (value == null) return null
		if (!isImperial.value) return value
		// value is { feet, inches }
		return Math.round(((value.feet || 0) * 12 + (value.inches || 0)) * CM_PER_INCH)
	}

	/**
	 * Convert stored kg to display body weight
	 * @param {number} kg Weight in kilograms
	 */
	function displayBodyWeight(kg) {
		if (kg == null) return null
		return isImperial.value ? Math.round(kg * KG_TO_LBS * 10) / 10 : kg
	}

	/**
	 * Convert display body weight back to kg for storage
	 * @param {number} displayValue Weight in user's display unit
	 */
	function toKg(displayValue) {
		if (displayValue == null) return null
		return isImperial.value ? Math.round(displayValue / KG_TO_LBS * 10) / 10 : displayValue
	}

	/**
	 * Format an entry's calculated energy: (energyPer100g * amountGrams / 100) in display units.
	 * @param {number} energyPer100g Energy per 100g in kcal
	 * @param {number} amountGrams Amount consumed in grams
	 */
	function entryEnergy(energyPer100g, amountGrams) {
		const kcal = Math.round(energyPer100g * amountGrams / 100)
		return displayEnergy(kcal)
	}

	/**
	 * Format an entry's macro value in display weight units.
	 * Macros are stored as "per 100g", so actual = macroPer100g * amountGrams / 100.
	 * @param {number} macroPer100g Macro nutrient per 100g in grams
	 * @param {number} amountGrams Amount consumed in grams
	 */
	function entryMacroGrams(macroPer100g, amountGrams) {
		const grams = Math.round(macroPer100g * amountGrams / 100)
		return isImperial.value ? Math.round(grams / GRAMS_PER_OZ * 10) / 10 : grams
	}

	return {
		isImperial,
		isKj,
		energyLabel,
		weightLabel,
		perWeightLabel,
		heightLabel,
		bodyWeightLabel,
		displayEnergy,
		toKcal,
		displayWeight,
		toGrams,
		displayPer100g,
		toPer100g,
		displayHeight,
		toCm,
		displayBodyWeight,
		toKg,
		entryEnergy,
		entryMacroGrams,
	}
}
