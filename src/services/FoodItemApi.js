/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const url = (path) => generateUrl('/apps/calorietracker' + path)

export default {
	/**
	 * Fetch frequently used food items.
	 * @param {number} limit Maximum number of items to return
	 * @return {Promise<Array>} List of food items
	 */
	getFrequent(limit = 8) {
		return axios.get(url('/food-items'), {
			params: { sort: 'frequent', limit },
		}).then(r => r.data)
	},
}
