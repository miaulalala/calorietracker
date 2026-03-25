/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, test, vi } from 'vitest'

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: vi.fn(),
		post: vi.fn(),
		put: vi.fn(),
		delete: vi.fn(),
	},
}))

vi.mock('@nextcloud/router', () => ({
	generateUrl: vi.fn((path) => `/nc${path}`),
}))

const { default: axios } = await import('@nextcloud/axios')
const { default: api } = await import('./FoodEntryApi.js')

describe('FoodEntryApi', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	test('getEntries GETs /entries with date param', async () => {
		axios.get.mockResolvedValue({ data: [] })
		await api.getEntries('2026-03-25')
		expect(axios.get).toHaveBeenCalledWith(
			'/nc/apps/calorietracker/entries',
			{ params: { date: '2026-03-25' } },
		)
	})

	test('getEntries returns the response data', async () => {
		const entries = [{ id: 1, name: 'Apple' }]
		axios.get.mockResolvedValue({ data: entries })
		const result = await api.getEntries('2026-03-25')
		expect(result).toEqual(entries)
	})

	test('createEntry POSTs /entries and returns the new entry', async () => {
		const payload = { name: 'Banana', mealType: 'snack' }
		const created = { id: 1, ...payload }
		axios.post.mockResolvedValue({ data: created })
		const result = await api.createEntry(payload)
		expect(axios.post).toHaveBeenCalledWith('/nc/apps/calorietracker/entries', payload)
		expect(result).toEqual(created)
	})

	test('updateEntry PUTs /entries/:id', async () => {
		const updated = { id: 7, name: 'Mango' }
		axios.put.mockResolvedValue({ data: updated })
		const result = await api.updateEntry(7, { name: 'Mango' })
		expect(axios.put).toHaveBeenCalledWith('/nc/apps/calorietracker/entries/7', { name: 'Mango' })
		expect(result).toEqual(updated)
	})

	test('deleteEntry DELETEs /entries/:id', async () => {
		axios.delete.mockResolvedValue({ data: null })
		await api.deleteEntry(3)
		expect(axios.delete).toHaveBeenCalledWith('/nc/apps/calorietracker/entries/3')
	})

	test('getSummaries GETs /entries/summary with from/to params', async () => {
		axios.get.mockResolvedValue({ data: [] })
		await api.getSummaries('2026-02-24', '2026-03-25')
		expect(axios.get).toHaveBeenCalledWith(
			'/nc/apps/calorietracker/entries/summary',
			{ params: { from: '2026-02-24', to: '2026-03-25' } },
		)
	})
})
