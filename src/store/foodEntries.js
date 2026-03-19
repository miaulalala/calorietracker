/**
 * SPDX-FileCopyrightText: 2026 Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import api from '../services/FoodEntryApi.js'
import { toLocalDateString } from '../utils/date.js'

const today = () => toLocalDateString()

export default {
	namespaced: true,
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

		macroTotals(state, getters) {
			if (state.entries.length === 0) return null
			let proteinG = 0
			let carbsG = 0
			let fatG = 0
			for (const e of state.entries) {
				if (e.proteinPer100g != null) proteinG += Math.round(e.proteinPer100g * e.amountGrams / 100)
				if (e.carbsPer100g != null) carbsG += Math.round(e.carbsPer100g * e.amountGrams / 100)
				if (e.fatPer100g != null) fatG += Math.round(e.fatPer100g * e.amountGrams / 100)
			}
			const totalKcal = getters.totalCalories || 1
			return {
				protein: { grams: proteinG, kcal: proteinG * 4, pct: Math.round(proteinG * 4 / totalKcal * 100) },
				carbs:   { grams: carbsG,   kcal: carbsG * 4,   pct: Math.round(carbsG * 4 / totalKcal * 100) },
				fat:     { grams: fatG,     kcal: fatG * 9,     pct: Math.round(fatG * 9 / totalKcal * 100) },
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

	mutations: {
		SET_ENTRIES(state, entries) {
			state.entries = entries
		},

		ADD_ENTRY(state, entry) {
			state.entries.push(entry)
		},

		UPDATE_ENTRY(state, updated) {
			const index = state.entries.findIndex(e => e.id === updated.id)
			if (index !== -1) {
				state.entries.splice(index, 1, updated)
			}
		},

		REMOVE_ENTRY(state, id) {
			state.entries = state.entries.filter(e => e.id !== id)
		},

		SET_DATE(state, date) {
			state.currentDate = date
		},

		OPEN_ADD_MODAL(state, entry = null) {
			state.editingEntry = entry
			state.addModalOpen = true
		},

		CLOSE_ADD_MODAL(state) {
			state.addModalOpen = false
			state.editingEntry = null
		},

		SET_DAY_SUMMARIES(state, summaries) {
			const map = {}
			for (const s of summaries) {
				map[s.date] = s
			}
			state.daySummaries = map
		},
	},

	actions: {
		async fetchEntries({ commit, state }) {
			const entries = await api.getEntries(state.currentDate)
			commit('SET_ENTRIES', entries)
		},

		async addEntry({ commit, dispatch }, payload) {
			const entry = await api.createEntry(payload)
			commit('ADD_ENTRY', entry)
			dispatch('fetchSummaries')
		},

		async updateEntry({ commit, dispatch }, { id, ...payload }) {
			const entry = await api.updateEntry(id, payload)
			commit('UPDATE_ENTRY', entry)
			dispatch('fetchSummaries')
		},

		async deleteEntry({ commit, dispatch }, id) {
			await api.deleteEntry(id)
			commit('REMOVE_ENTRY', id)
			dispatch('fetchSummaries')
		},

		setDate({ commit, dispatch }, date) {
			commit('SET_DATE', date)
			dispatch('fetchEntries')
		},

		async fetchSummaries({ commit }) {
			const today = new Date()
			const to = toLocalDateString(today)
			const from = toLocalDateString(new Date(today.getFullYear(), today.getMonth(), today.getDate() - 29))
			const summaries = await api.getSummaries(from, to)
			commit('SET_DAY_SUMMARIES', summaries)
		},

		openAddModal({ commit }, entry = null) {
			commit('OPEN_ADD_MODAL', entry)
		},

		closeAddModal({ commit }) {
			commit('CLOSE_ADD_MODAL')
		},
	},
}
