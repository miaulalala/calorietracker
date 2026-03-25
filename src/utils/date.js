/**
 * SPDX-FileCopyrightText: 2026 Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Format a Date as a local YYYY-MM-DD string (not UTC).
 * @param {Date} d - the date to format
 * @return {string}
 */
export function toLocalDateString(d = new Date()) {
	return [
		d.getFullYear(),
		String(d.getMonth() + 1).padStart(2, '0'),
		String(d.getDate()).padStart(2, '0'),
	].join('-')
}
