/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, test, vi } from 'vitest'
import { useWeightLogStore } from './weightLog.js'

vi.mock('../services/WeightLogApi.js', () => ({
	default: {
		getLatest: vi.fn(),
		getWeightLogs: vi.fn(),
		logWeight: vi.fn(),
		deleteWeight: vi.fn(),
	},
}))

describe('weightLog store', () => {
	let store
	let api

	beforeEach(async () => {
		store = useWeightLogStore()
		const module = await import('../services/WeightLogApi.js')
		api = module.default
		vi.clearAllMocks()
		store.$reset()
	})

	// ─── fetchLatest ───────────────────────────────────────────────────────────

	describe('fetchLatest', () => {
		test('fetches latest and updates latestWeight', async () => {
			const entry = { id: 1, weightKg: 72.5, loggedAt: '2026-05-01', note: null }
			api.getLatest.mockResolvedValue(entry)
			await store.fetchLatest()
			expect(api.getLatest).toHaveBeenCalledOnce()
			expect(store.latestWeight).toEqual(entry)
		})

		test('sets latestWeight to null when no entry', async () => {
			api.getLatest.mockResolvedValue(null)
			await store.fetchLatest()
			expect(store.latestWeight).toBeNull()
		})
	})

	// ─── fetchHistory ──────────────────────────────────────────────────────────

	describe('fetchHistory', () => {
		test('fetches history with default 90 days and updates store', async () => {
			const logs = [{ id: 1, weightKg: 72.5, loggedAt: '2026-04-01', note: null }]
			api.getWeightLogs.mockResolvedValue(logs)
			await store.fetchHistory()
			expect(api.getWeightLogs).toHaveBeenCalledOnce()
			expect(store.history).toEqual(logs)
		})

		test('passes correct date range for given days', async () => {
			api.getWeightLogs.mockResolvedValue([])
			await store.fetchHistory(30)
			const [from, to] = api.getWeightLogs.mock.calls[0]
			expect(to).toMatch(/^\d{4}-\d{2}-\d{2}$/)
			expect(from).toMatch(/^\d{4}-\d{2}-\d{2}$/)
			const diffDays = (new Date(to) - new Date(from)) / (1000 * 60 * 60 * 24)
			expect(diffDays).toBe(29)
		})
	})

	// ─── logWeight ─────────────────────────────────────────────────────────────

	describe('logWeight', () => {
		test('calls api, updates latestWeight, and closes modal', async () => {
			const entry = { id: 2, weightKg: 73.0, loggedAt: '2026-05-05', note: null }
			api.logWeight.mockResolvedValue(entry)
			store.logModalOpen = true

			const result = await store.logWeight({ weightKg: 73.0, loggedAt: '2026-05-05' })

			expect(api.logWeight).toHaveBeenCalledWith({ weightKg: 73.0, loggedAt: '2026-05-05' })
			expect(store.latestWeight).toEqual(entry)
			expect(store.logModalOpen).toBe(false)
			expect(result).toEqual(entry)
		})
	})

	// ─── deleteWeight ──────────────────────────────────────────────────────────

	describe('deleteWeight', () => {
		test('deletes entry and re-fetches latest', async () => {
			api.deleteWeight.mockResolvedValue(null)
			const latest = { id: 1, weightKg: 70.0, loggedAt: '2026-05-01', note: null }
			api.getLatest.mockResolvedValue(latest)

			await store.deleteWeight(3)

			expect(api.deleteWeight).toHaveBeenCalledWith(3)
			expect(api.getLatest).toHaveBeenCalledOnce()
			expect(store.latestWeight).toEqual(latest)
		})
	})

	// ─── modal open/close ──────────────────────────────────────────────────────

	describe('log modal', () => {
		test('openLogModal sets logModalOpen to true', () => {
			store.openLogModal()
			expect(store.logModalOpen).toBe(true)
		})

		test('closeLogModal sets logModalOpen to false', () => {
			store.logModalOpen = true
			store.closeLogModal()
			expect(store.logModalOpen).toBe(false)
		})
	})

	describe('graph modal', () => {
		test('openGraphModal sets graphModalOpen to true', () => {
			store.openGraphModal()
			expect(store.graphModalOpen).toBe(true)
		})

		test('closeGraphModal sets graphModalOpen to false', () => {
			store.graphModalOpen = true
			store.closeGraphModal()
			expect(store.graphModalOpen).toBe(false)
		})
	})
})
