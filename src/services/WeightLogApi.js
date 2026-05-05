/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const url = (path) => generateUrl('/apps/calorietracker' + path)

export default {
	getLatest() {
		return axios.get(url('/weight-logs/latest')).then(r => r.data)
	},

	getWeightLogs(from, to) {
		return axios.get(url('/weight-logs'), {
			params: { from, to },
		}).then(r => r.data)
	},

	logWeight({ weightKg, loggedAt, note }) {
		return axios.post(url('/weight-logs'), {
			weightKg,
			loggedAt,
			note,
		}).then(r => r.data)
	},

	deleteWeight(id) {
		return axios.delete(url(`/weight-logs/${id}`)).then(r => r.data)
	},
}
