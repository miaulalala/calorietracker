/**
 * SPDX-FileCopyrightText: 2026 Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const url = (path) => generateUrl('/apps/calorietracker' + path)

export default {
	getSettings() {
		return axios.get(url('/settings')).then(r => r.data)
	},

	saveSettings(data) {
		return axios.put(url('/settings'), data).then(r => r.data)
	},
}
