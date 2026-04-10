/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import usdaApi from '../services/UsdaFdcApi.js'

/**
 * Common volume-to-gram conversions for cooking ingredients.
 * Values are approximate grams for water-density items.
 */
const VOLUME_TO_GRAMS = {
	tsp: 5,
	teaspoon: 5,
	teaspoons: 5,
	tbsp: 15,
	tablespoon: 15,
	tablespoons: 15,
	cup: 240,
	cups: 240,
	ml: 1,
	dl: 100,
	l: 1000,
	liter: 1000,
	litre: 1000,
	liters: 1000,
	litres: 1000,
	oz: 28.35,
	ounce: 28.35,
	ounces: 28.35,
	lb: 453.6,
	lbs: 453.6,
	pound: 453.6,
	pounds: 453.6,
	g: 1,
	gram: 1,
	grams: 1,
	kg: 1000,
	kilogram: 1000,
	kilograms: 1000,
}

/**
 * Parse a fraction string like "1/2" or "3/4" to a number.
 * @param {string} str Fraction string
 * @return {number|null} Parsed value or null
 */
function parseFraction(str) {
	str = str.trim()
	// Unicode fractions
	const unicodeFractions = { '½': 0.5, '⅓': 1 / 3, '⅔': 2 / 3, '¼': 0.25, '¾': 0.75, '⅕': 0.2, '⅛': 0.125 }
	if (unicodeFractions[str] !== undefined) return unicodeFractions[str]

	const slashMatch = str.match(/^(\d+)\s*\/\s*(\d+)$/)
	if (slashMatch) {
		const denom = parseInt(slashMatch[2], 10)
		return denom > 0 ? parseInt(slashMatch[1], 10) / denom : null
	}
	return null
}

/**
 * Parse a quantity string that may contain whole numbers, fractions, or mixed numbers.
 * Examples: "2", "1/2", "1 1/2", "2½"
 * @param {string} str Quantity string
 * @return {number|null} Parsed quantity or null
 */
function parseQuantity(str) {
	str = str.trim()
	if (!str) return null

	const num = parseFloat(str)
	if (!isNaN(num) && /^\d+(\.\d+)?$/.test(str)) return num

	// Mixed number: "1 1/2" or "1½"
	const mixedMatch = str.match(/^(\d+)\s+(.+)$/)
	if (mixedMatch) {
		const whole = parseInt(mixedMatch[1], 10)
		const frac = parseFraction(mixedMatch[2])
		if (frac !== null) return whole + frac
	}

	// Plain fraction
	const frac = parseFraction(str)
	if (frac !== null) return frac

	// Single number with trailing text (e.g., "2½")
	const leadMatch = str.match(/^(\d+)(.+)$/)
	if (leadMatch) {
		const whole = parseInt(leadMatch[1], 10)
		const fracPart = parseFraction(leadMatch[2])
		if (fracPart !== null) return whole + fracPart
	}

	return null
}

/**
 * Parse an ingredient string like "200g chicken breast" or "2 cups flour".
 * Returns the estimated grams and the food name for searching.
 * @param {string} ingredient Raw ingredient text from recipe
 * @return {{ quantity: number|null, unit: string|null, name: string, estimatedGrams: number|null }}
 */
export function parseIngredient(ingredient) {
	// Strip parenthetical notes and leading/trailing punctuation
	const text = ingredient
		.replace(/\(.*?\)/g, '')
		.replace(/,\s*$/, '')
		.trim()

	// Try to match: [quantity] [unit] [name]
	// Pattern: optional quantity (number/fraction), optional unit, then the food name
	const match = text.match(
		/^([\d½⅓⅔¼¾⅕⅛\s/.-]+)?\s*(tsp|teaspoons?|tbsp|tablespoons?|cups?|ml|dl|l|liters?|litres?|oz|ounces?|lbs?|pounds?|g|grams?|kg|kilograms?)\.?\s+(.+)$/i,
	)

	if (match) {
		const quantity = parseQuantity(match[1] || '1')
		const unit = match[2].toLowerCase().replace(/\.$/, '')
		const name = match[3].trim()
		const gramsPerUnit = VOLUME_TO_GRAMS[unit] ?? null
		const estimatedGrams = quantity && gramsPerUnit ? Math.round(quantity * gramsPerUnit) : null

		return { quantity, unit, name, estimatedGrams }
	}

	// Try: [quantity] [name] (no unit — assume pieces, use 100g default)
	const simpleMatch = text.match(/^([\d½⅓⅔¼¾⅕⅛\s/.-]+)\s+(.+)$/)
	if (simpleMatch) {
		const quantity = parseQuantity(simpleMatch[1])
		const name = simpleMatch[2].trim()
		// No unit: assume ~100g per piece for whole items
		return { quantity, unit: null, name, estimatedGrams: quantity ? Math.round(quantity * 100) : null }
	}

	// Fallback: just a name, assume 100g
	return { quantity: null, unit: null, name: text, estimatedGrams: 100 }
}

/**
 * Estimate the total nutrition for a recipe given its ingredient list.
 * Parses each ingredient, batch-looks them up in the food database, and sums
 * the total. Returns per-serving values.
 *
 * @param {string[]} ingredients Array of ingredient text strings
 * @param {string|number|null} recipeYield Number of servings
 * @return {Promise<{caloriesPer100g: number, proteinPer100g: number|null, carbsPer100g: number|null, fatPer100g: number|null, servingSize: string}>}
 */
export async function estimateRecipeNutrition(ingredients, recipeYield) {
	const servings = parseInt(String(recipeYield), 10) || 1

	// Parse all ingredients and batch-lookup via a single server request
	const parsed = ingredients.map(parseIngredient)
	const names = parsed.map(p => p.name)

	let lookups
	try {
		lookups = await usdaApi.batchSearch(names)
	} catch {
		throw new Error('No ingredients could be matched in the food database')
	}

	let totalKcal = 0
	let totalProtein = 0
	let totalCarbs = 0
	let totalFat = 0
	let foundCount = 0
	let hasProtein = false
	let hasCarbs = false
	let hasFat = false

	for (let i = 0; i < parsed.length; i++) {
		const nutrition = lookups[i]
		if (!nutrition || nutrition.caloriesPer100g == null) continue
		const grams = parsed[i].estimatedGrams || 100
		const factor = grams / 100

		totalKcal += (nutrition.caloriesPer100g || 0) * factor
		if (nutrition.proteinPer100g != null) {
			totalProtein += nutrition.proteinPer100g * factor
			hasProtein = true
		}
		if (nutrition.carbsPer100g != null) {
			totalCarbs += nutrition.carbsPer100g * factor
			hasCarbs = true
		}
		if (nutrition.fatPer100g != null) {
			totalFat += nutrition.fatPer100g * factor
			hasFat = true
		}
		foundCount++
	}

	if (foundCount === 0) {
		throw new Error('No ingredients could be matched in the food database')
	}

	// Per-serving values (stored as "per serving" not "per 100g" for recipes)
	return {
		caloriesPer100g: Math.round(totalKcal / servings),
		proteinPer100g: hasProtein ? Math.round(totalProtein / servings) : null,
		carbsPer100g: hasCarbs ? Math.round(totalCarbs / servings) : null,
		fatPer100g: hasFat ? Math.round(totalFat / servings) : null,
		servingSize: `1/${servings} of recipe`,
	}
}
