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
					<NcListItem
						v-for="entry in groups[mealType]"
						:key="entry.id"
						:name="entry.foodName"
						:subname="entry.amountGrams + 'g · ' + entryCalories(entry) + ' kcal'"
						:compact="true">
						<template #actions>
							<NcActionButton @click="$store.dispatch('foodEntries/openAddModal', entry)">
								<template #icon>
									<NcIconSvgWrapper :svg="iconPencil" />
								</template>
								{{ t('calorietracker', 'Edit') }}
							</NcActionButton>
							<NcActionButton @click="confirmDelete(entry)">
								<template #icon>
									<NcIconSvgWrapper :svg="iconTrash" />
								</template>
								{{ t('calorietracker', 'Delete') }}
							</NcActionButton>
						</template>
					</NcListItem>
				</ul>
			</div>
		</template>

		<NcEmptyContent
			v-if="isEmpty"
			:name="t('calorietracker', 'No entries yet')"
			:description="t('calorietracker', 'Track your first meal for this day.')">
			<template #icon>
				<NcIconSvgWrapper :svg="iconFood" />
			</template>
			<template #action>
				<NcButton type="primary" @click="$store.dispatch('foodEntries/openAddModal')">
					{{ t('calorietracker', 'Add food') }}
				</NcButton>
			</template>
		</NcEmptyContent>

		<NcDialog
			v-if="deleteTarget"
			:name="t('calorietracker', 'Delete entry')"
			:message="deleteMessage"
			:buttons="deleteDialogButtons"
			@closing="deleteTarget = null" />
	</div>
</template>

<script>
import NcListItem from '@nextcloud/vue/dist/Components/NcListItem.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcIconSvgWrapper from '@nextcloud/vue/dist/Components/NcIconSvgWrapper.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'

const iconPencil = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M20.71 7.04c.39-.39.39-1.04 0-1.41l-2.34-2.34c-.37-.39-1.02-.39-1.41 0l-1.84 1.83 3.75 3.75M3 17.25V21h3.75L17.81 9.93l-3.75-3.75L3 17.25z"/></svg>'
const iconTrash = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M19 4h-3.5l-1-1h-5l-1 1H5v2h14M6 19a2 2 0 002 2h8a2 2 0 002-2V7H6v12z"/></svg>'
const iconFood = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="13" r="8"/><line x1="9" y1="3" x2="9" y2="7"/><line x1="7" y1="3" x2="7" y2="7"/><path d="M8 7 Q8 9 8 10 L8 13"/><path d="M15 3 L15 7 Q17 8 15 10 L15 13"/></svg>'

export default {
	name: 'FoodEntryList',

	components: { NcListItem, NcActionButton, NcButton, NcIconSvgWrapper, NcEmptyContent, NcDialog },

	props: {
		groups: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			mealOrder: ['breakfast', 'lunch', 'dinner', 'snack'],
			deleteTarget: null,
			iconPencil,
			iconTrash,
			iconFood,
		}
	},

	computed: {
		isEmpty() {
			return this.mealOrder.every(type => this.groups[type].length === 0)
		},

		deleteMessage() {
			return this.deleteTarget
				? t('calorietracker', 'Delete "{name}"?', { name: this.deleteTarget.foodName })
				: ''
		},

		deleteDialogButtons() {
			return [
				{
					label: t('calorietracker', 'Cancel'),
					type: 'tertiary',
					callback: () => { this.deleteTarget = null },
				},
				{
					label: t('calorietracker', 'Delete'),
					type: 'error',
					callback: () => this.doDelete(),
				},
			]
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

		confirmDelete(entry) {
			this.deleteTarget = entry
		},

		async doDelete() {
			await this.$store.dispatch('foodEntries/deleteEntry', this.deleteTarget.id)
			this.deleteTarget = null
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
</style>
