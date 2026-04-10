/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const url = (path) => generateUrl('/apps/calorietracker' + path)

export default {
	search(query) {
		return axios.get(url('/cookbook/search'), {
			params: { query },
		}).then(r => r.data)
	},

	getRecipe(id) {
		return axios.get(url(`/cookbook/recipes/${id}`)).then(r => r.data)
	},

	updateNutrition(id, { calories, protein, carbs, fat, servingSize }) {
		return axios.put(url(`/cookbook/recipes/${id}/nutrition`), {
			calories,
			protein,
			carbs,
			fat,
			servingSize,
		}).then(r => r.data)
	},
}
