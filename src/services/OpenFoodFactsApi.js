/**
 * SPDX-FileCopyrightText: 2026 Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export default {
	search(query) {
		return axios.get(generateUrl('/apps/calorietracker/off/search'), {
			params: { query },
		}).then(r => r.data)
	},
}
