/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { loadState } from '@nextcloud/initial-state'

const cookbookAvailable = loadState('calorietracker', 'cookbook-available', false)

export function useCookbook() {
	return {
		cookbookAvailable,
	}
}
