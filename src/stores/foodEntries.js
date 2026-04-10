/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import api from '../services/FoodEntryApi.js'
import { toLocalDateString } from '../utils/date.js'

const today = () => toLocalDateString()

export const useFoodEntriesStore = defineStore('foodEntries', {
	state: () => ({
		entries: [],
		currentDate: today(),
		addModalOpen: false,
		editingEntry: null,
		daySummaries: {},
	}),

	getters: {
		totalCalories(state) {
			return state.entries.reduce((sum, e) => {
				return sum + Math.round(e.caloriesPer100g * e.amountGrams / 100)
			}, 0)
		},

		macroTotals(state) {
			if (state.entries.length === 0) return null
			let proteinG = 0
			let carbsG = 0
			let fatG = 0
			for (const e of state.entries) {
				if (e.proteinPer100g != null) proteinG += Math.round(e.proteinPer100g * e.amountGrams / 100)
				if (e.carbsPer100g != null) carbsG += Math.round(e.carbsPer100g * e.amountGrams / 100)
				if (e.fatPer100g != null) fatG += Math.round(e.fatPer100g * e.amountGrams / 100)
			}
			const totalKcal = this.totalCalories || 1
			return {
				protein: { grams: proteinG, kcal: proteinG * 4, pct: Math.round(proteinG * 4 / totalKcal * 100) },
				carbs: { grams: carbsG, kcal: carbsG * 4, pct: Math.round(carbsG * 4 / totalKcal * 100) },
				fat: { grams: fatG, kcal: fatG * 9, pct: Math.round(fatG * 9 / totalKcal * 100) },
			}
		},

		entriesByMealType(state) {
			const order = ['breakfast', 'lunch', 'dinner', 'snack']
			const groups = {}
			for (const type of order) {
				groups[type] = []
			}
			for (const entry of state.entries) {
				if (groups[entry.mealType]) {
					groups[entry.mealType].push(entry)
				}
			}
			return groups
		},
	},

	actions: {
		async fetchEntries() {
			this.entries = await api.getEntries(this.currentDate)
		},

		async addEntry(payload) {
			const entry = await api.createEntry(payload)
			this.entries.push(entry)
			await this.fetchSummaries()
			return entry
		},

		async updateEntry({ id, ...payload }) {
			const entry = await api.updateEntry(id, payload)
			const index = this.entries.findIndex(e => e.id === id)
			if (index !== -1) this.entries.splice(index, 1, entry)
			await this.fetchSummaries()
			return entry
		},

		async deleteEntry(id) {
			await api.deleteEntry(id)
			this.entries = this.entries.filter(e => e.id !== id)
			await this.fetchSummaries()
		},

		async setDate(date) {
			this.currentDate = date
			await this.fetchEntries()
		},

		async fetchSummaries(from = null, to = null) {
			const now = new Date()
			const resolvedTo = to ?? toLocalDateString(now)
			const resolvedFrom = from ?? toLocalDateString(new Date(now.getFullYear(), now.getMonth(), now.getDate() - 29))
			const summaries = await api.getSummaries(resolvedFrom, resolvedTo)
			const incoming = {}
			for (const s of summaries) {
				incoming[s.date] = s
			}

			// Drop existing keys within the fetched range before merging so that
			// days that became empty (omitted by the API) don't leave stale data.
			const updated = { ...this.daySummaries }
			for (const date of Object.keys(updated)) {
				if (date >= resolvedFrom && date <= resolvedTo) {
					delete updated[date]
				}
			}

			// Prune dates older than 90 days to prevent unbounded growth in
			// long-lived sessions where a user browses many past weeks.
			const cutoff = toLocalDateString(new Date(now.getFullYear(), now.getMonth(), now.getDate() - 89))
			for (const date of Object.keys(updated)) {
				if (date < cutoff) {
					delete updated[date]
				}
			}

			this.daySummaries = { ...updated, ...incoming }
		},

		openAddModal(entry = null) {
			this.editingEntry = entry
			this.addModalOpen = true
		},

		closeAddModal() {
			this.addModalOpen = false
			this.editingEntry = null
		},
	},
})
