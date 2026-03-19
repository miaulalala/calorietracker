<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="food-entry-list">
		<template v-for="mealType in mealOrder">
			<div v-if="groups[mealType].length > 0" :key="mealType" class="food-entry-list__group">
				<h4 class="food-entry-list__meal-heading">
					{{ mealLabel(mealType) }}
					<span class="food-entry-list__meal-total">
						{{ mealTotal(groups[mealType]) }} kcal
					</span>
				</h4>

				<ul class="food-entry-list__items">
					<li v-for="entry in groups[mealType]" :key="entry.id" class="food-entry-list__item">
						<span class="food-entry-list__item-name">{{ entry.foodName }}</span>
						<span class="food-entry-list__item-detail">
							{{ entry.amountGrams }}g &middot; {{ entryCalories(entry) }} kcal
						</span>
						<div class="food-entry-list__item-actions">
							<NcButton type="tertiary" :aria-label="t('calorietracker', 'Edit')" @click="$store.dispatch('foodEntries/openAddModal', entry)">
								✏️
							</NcButton>
							<NcButton type="tertiary" :aria-label="t('calorietracker', 'Delete')" @click="confirmDelete(entry)">
								🗑️
							</NcButton>
						</div>
					</li>
				</ul>
			</div>
		</template>

		<p v-if="isEmpty" class="food-entry-list__empty">
			{{ t('calorietracker', 'No entries for this day yet.') }}
		</p>
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

export default {
	name: 'FoodEntryList',

	components: { NcButton },

	props: {
		groups: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			mealOrder: ['breakfast', 'lunch', 'dinner', 'snack'],
		}
	},

	computed: {
		isEmpty() {
			return this.mealOrder.every(type => this.groups[type].length === 0)
		},
	},

	methods: {
		mealLabel(type) {
			const labels = {
				breakfast: this.t('calorietracker', 'Breakfast'),
				lunch: this.t('calorietracker', 'Lunch'),
				dinner: this.t('calorietracker', 'Dinner'),
				snack: this.t('calorietracker', 'Snack'),
			}
			return labels[type] ?? type
		},

		entryCalories(entry) {
			return Math.round(entry.caloriesPer100g * entry.amountGrams / 100)
		},

		mealTotal(entries) {
			return entries.reduce((sum, e) => sum + this.entryCalories(e), 0)
		},

		async confirmDelete(entry) {
			if (!confirm(t('calorietracker', 'Delete "{name}"?', { name: entry.foodName }))) {
				return
			}
			await this.$store.dispatch('foodEntries/deleteEntry', entry.id)
		},
	},
}
</script>

<style scoped>
.food-entry-list__group {
	margin-bottom: 20px;
}

.food-entry-list__meal-heading {
	display: flex;
	justify-content: space-between;
	align-items: baseline;
	margin: 0 0 6px;
	padding-bottom: 4px;
	border-bottom: 1px solid var(--color-border);
	font-size: 1em;
	text-transform: capitalize;
}

.food-entry-list__meal-total {
	font-size: 0.85em;
	color: var(--color-text-maxcontrast);
}

.food-entry-list__items {
	list-style: none;
	margin: 0;
	padding: 0;
}

.food-entry-list__item {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 4px 0;
}

.food-entry-list__item-name {
	flex: 1;
}

.food-entry-list__item-detail {
	color: var(--color-text-maxcontrast);
	font-size: 0.9em;
	white-space: nowrap;
}

.food-entry-list__item-actions {
	display: flex;
	gap: 2px;
}

.food-entry-list__empty {
	color: var(--color-text-maxcontrast);
	text-align: center;
	margin-top: 32px;
}
</style>
