/**
 * SPDX-FileCopyrightText: 2026 Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const url = (path) => generateUrl('/apps/calorietracker' + path)

export default {
	getEntries(date) {
		return axios.get(url('/entries'), { params: { date } }).then(r => r.data)
	},

	createEntry(data) {
		return axios.post(url('/entries'), data).then(r => r.data)
	},

	updateEntry(id, data) {
		return axios.put(url('/entries/' + id), data).then(r => r.data)
	},

	deleteEntry(id) {
		return axios.delete(url('/entries/' + id)).then(r => r.data)
	},

	getSummaries(from, to) {
		return axios.get(url('/entries/summary'), { params: { from, to } }).then(r => r.data)
	},
}
