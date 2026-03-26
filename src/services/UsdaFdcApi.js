/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const url = (path) => generateUrl('/apps/calorietracker' + path)

export default {
	search(query) {
		return axios.get(url('/usda/search'), {
			params: { query },
		}).then(r => r.data)
	},
}
