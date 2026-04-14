/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import api from '../services/WeightLogApi.js'

export const useWeightLogStore = defineStore('weightLog', {
	state: () => ({
		latestWeight: null,
		logModalOpen: false,
	}),

	actions: {
		async fetchLatest() {
			this.latestWeight = await api.getLatest()
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
	},
})
