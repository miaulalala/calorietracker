/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, test } from 'vitest'
import { toLocalDateString } from './date.js'

describe('toLocalDateString', () => {
	test('formats a date as YYYY-MM-DD', () => {
		expect(toLocalDateString(new Date(2026, 2, 25))).toBe('2026-03-25')
	})

	test('pads month and day with leading zeros', () => {
		expect(toLocalDateString(new Date(2026, 0, 5))).toBe('2026-01-05')
	})

	test('handles year boundaries correctly', () => {
		expect(toLocalDateString(new Date(2025, 11, 31))).toBe('2025-12-31')
		expect(toLocalDateString(new Date(2026, 0, 1))).toBe('2026-01-01')
	})

	test('defaults to today when called without arguments', () => {
		const result = toLocalDateString()
		expect(result).toMatch(/^\d{4}-\d{2}-\d{2}$/)
		const today = new Date()
		const expected = [
			today.getFullYear(),
			String(today.getMonth() + 1).padStart(2, '0'),
			String(today.getDate()).padStart(2, '0'),
		].join('-')
		expect(result).toBe(expected)
	})
})
