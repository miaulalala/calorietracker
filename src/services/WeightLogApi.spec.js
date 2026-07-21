/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, test, vi } from 'vitest'

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: vi.fn(),
		post: vi.fn(),
		delete: vi.fn(),
	},
}))

vi.mock('@nextcloud/router', () => ({
	generateUrl: vi.fn((path) => `/nc${path}`),
}))

const { default: axios } = await import('@nextcloud/axios')
const { default: api } = await import('./WeightLogApi.js')

describe('WeightLogApi', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	test('getLatest GETs /weight-logs/latest and returns data', async () => {
		const entry = { id: 1, weightKg: 72.5, loggedAt: '2026-05-01', note: null }
		axios.get.mockResolvedValue({ data: entry })
		const result = await api.getLatest()
		expect(axios.get).toHaveBeenCalledWith('/nc/apps/calorietracker/weight-logs/latest')
		expect(result).toEqual(entry)
	})

	test('getLatest returns null when no entry exists', async () => {
		axios.get.mockResolvedValue({ data: null })
		const result = await api.getLatest()
		expect(result).toBeNull()
	})

	test('getWeightLogs GETs /weight-logs with from/to params', async () => {
		axios.get.mockResolvedValue({ data: [] })
		await api.getWeightLogs('2026-02-04', '2026-05-05')
		expect(axios.get).toHaveBeenCalledWith(
			'/nc/apps/calorietracker/weight-logs',
			{ params: { from: '2026-02-04', to: '2026-05-05' } },
		)
	})

	test('getWeightLogs returns the response data', async () => {
		const logs = [{ id: 1, weightKg: 72.5, loggedAt: '2026-05-01', note: null }]
		axios.get.mockResolvedValue({ data: logs })
		const result = await api.getWeightLogs('2026-04-01', '2026-05-01')
		expect(result).toEqual(logs)
	})

	test('logWeight POSTs /weight-logs with payload and returns entry', async () => {
		const payload = { weightKg: 73.0, loggedAt: '2026-05-05', note: 'morning' }
		const created = { id: 5, ...payload }
		axios.post.mockResolvedValue({ data: created })
		const result = await api.logWeight(payload)
		expect(axios.post).toHaveBeenCalledWith('/nc/apps/calorietracker/weight-logs', payload)
		expect(result).toEqual(created)
	})

	test('logWeight sends note as null when omitted', async () => {
		const payload = { weightKg: 70.0, loggedAt: '2026-05-05', note: undefined }
		axios.post.mockResolvedValue({ data: { id: 6, weightKg: 70.0, loggedAt: '2026-05-05', note: null } })
		await api.logWeight(payload)
		expect(axios.post).toHaveBeenCalledWith(
			'/nc/apps/calorietracker/weight-logs',
			{ weightKg: 70.0, loggedAt: '2026-05-05', note: undefined },
		)
	})

	test('deleteWeight DELETEs /weight-logs/:id', async () => {
		axios.delete.mockResolvedValue({ data: null })
		await api.deleteWeight(3)
		expect(axios.delete).toHaveBeenCalledWith('/nc/apps/calorietracker/weight-logs/3')
	})
})
