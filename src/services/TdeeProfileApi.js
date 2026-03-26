/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const url = generateUrl('/apps/calorietracker/tdee-profile')

export default {
	get() {
		return axios.get(url).then(r => r.data)
	},
	save(profile) {
		return axios.put(url, profile).then(r => r.data)
	},
}
