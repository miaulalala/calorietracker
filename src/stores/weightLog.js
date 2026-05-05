/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import api from '../services/WeightLogApi.js'
import { toLocalDateString } from '../utils/date.js'

export const useWeightLogStore = defineStore('weightLog', {
	state: () => ({
		latestWeight: null,
		history: [],
		logModalOpen: false,
		graphModalOpen: false,
	}),

	actions: {
		async fetchLatest() {
			this.latestWeight = await api.getLatest()
		},

		async fetchHistory(days = 90) {
			const to = new Date()
			const from = new Date(to.getFullYear(), to.getMonth(), to.getDate() - days + 1)
			this.history = await api.getWeightLogs(toLocalDateString(from), toLocalDateString(to))
		},

		async logWeight(payload) {
			const entry = await api.logWeight(payload)
			this.latestWeight = entry
			this.logModalOpen = false
			return entry
		},

		async deleteWeight(id) {
			await api.deleteWeight(id)
			await this.fetchLatest()
		},

		openLogModal() {
			this.logModalOpen = true
		},

		closeLogModal() {
			this.logModalOpen = false
		},

		openGraphModal() {
			this.graphModalOpen = true
		},

		closeGraphModal() {
			this.graphModalOpen = false
		},
	},
})
