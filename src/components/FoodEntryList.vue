<!--
  - SPDX-FileCopyrightText: 2026 Anna Larch
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="food-entry-list">
		<div v-if="!isEmpty" class="food-entry-list__add">
			<NcButton variant="primary" @click="store.openAddModal()">
				<template #icon>
					<NcIconSvgWrapper :svg="iconPlus" />
				</template>
				{{ t('calorietracker', 'Add food') }}
			</NcButton>
		</div>

		<template v-for="mealType in mealOrder">
			<div v-if="groups[mealType].length > 0" :key="mealType" class="food-entry-list__group">
				<h4 class="food-entry-list__meal-heading-wrapper">
					<button class="food-entry-list__meal-heading"
						type="button"
						:aria-expanded="!collapsed[mealType]"
						@click="collapsed[mealType] = !collapsed[mealType]">
						<span class="food-entry-list__chevron"
							:class="{ 'food-entry-list__chevron--collapsed': collapsed[mealType] }">
							<NcIconSvgWrapper :svg="iconChevronDown" />
						</span>
						{{ mealLabel(mealType) }}
						<span class="food-entry-list__meal-count">{{ groups[mealType].length }}</span>
						<span class="food-entry-list__meal-total">
							{{ mealTotal(groups[mealType]) }} {{ energyLabel }}
						</span>
					</button>
				</h4>
				<hr class="food-entry-list__separator">

				<NcFormBox v-show="!collapsed[mealType]">
					<NcFormBoxButton v-for="entry in groups[mealType]"
						:key="entry.id"
						:label="entry.foodName"
						@click="store.openAddModal(entry)">
						<template #description>
							<span class="food-entry-list__details">
								<span class="food-entry-list__detail">{{ displayWeight(entry.amountGrams) }}{{ weightLabel }}</span>
								<span class="food-entry-list__detail food-entry-list__detail--energy">{{ entryEnergy(entry.caloriesPer100g, entry.amountGrams) }} {{ energyLabel }}</span>
								<span v-if="entry.proteinPer100g != null" class="food-entry-list__detail food-entry-list__detail--macro">P {{ entryMacroGrams(entry.proteinPer100g, entry.amountGrams) }}{{ weightLabel }}</span>
								<span v-if="entry.carbsPer100g != null" class="food-entry-list__detail food-entry-list__detail--macro">C {{ entryMacroGrams(entry.carbsPer100g, entry.amountGrams) }}{{ weightLabel }}</span>
								<span v-if="entry.fatPer100g != null" class="food-entry-list__detail food-entry-list__detail--macro">F {{ entryMacroGrams(entry.fatPer100g, entry.amountGrams) }}{{ weightLabel }}</span>
							</span>
						</template>
						<template #icon>
							<NcButton variant="tertiary"
								:aria-label="t('calorietracker', 'Edit')"
								@click.stop="store.openAddModal(entry)">
								<template #icon>
									<NcIconSvgWrapper :svg="iconPencil" />
								</template>
							</NcButton>
							<NcButton variant="tertiary"
								:aria-label="t('calorietracker', 'Delete')"
								@click.stop="confirmDelete(entry)">
								<template #icon>
									<NcIconSvgWrapper :svg="iconTrash" />
								</template>
							</NcButton>
						</template>
					</NcFormBoxButton>
				</NcFormBox>
			</div>
		</template>

		<NcEmptyContent v-if="isEmpty"
			:name="t('calorietracker', 'No entries yet')"
			:description="t('calorietracker', 'Track your first meal for this day.')">
			<template #icon>
				<NcIconSvgWrapper :svg="iconFood" />
			</template>
			<template #action>
				<NcButton variant="primary" @click="store.openAddModal()">
					<template #icon>
						<NcIconSvgWrapper :svg="iconPlus" />
					</template>
					{{ t('calorietracker', 'Add food') }}
				</NcButton>
			</template>
		</NcEmptyContent>

		<NcDialog v-if="deleteTarget"
			:name="t('calorietracker', 'Delete entry')"
			:message="deleteMessage"
			:buttons="deleteDialogButtons"
			content-classes="delete-dialog-content"
			@closing="deleteTarget = null" />
	</div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import NcFormBox from '@nextcloud/vue/components/NcFormBox'
import NcFormBoxButton from '@nextcloud/vue/components/NcFormBoxButton'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import { useFoodEntriesStore } from '../stores/foodEntries.js'
import { useUnits } from '../composables/useUnits.js'

const props = defineProps({
	groups: {
		type: Object,
		required: true,
	},
})

const store = useFoodEntriesStore()
const { displayEnergy, displayWeight, energyLabel, weightLabel, entryEnergy, entryMacroGrams } = useUnits()

const iconPencil = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M20.71 7.04c.39-.39.39-1.04 0-1.41l-2.34-2.34c-.37-.39-1.02-.39-1.41 0l-1.84 1.83 3.75 3.75M3 17.25V21h3.75L17.81 9.93l-3.75-3.75L3 17.25z"/></svg>'
const iconTrash = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M19 4h-3.5l-1-1h-5l-1 1H5v2h14M6 19a2 2 0 002 2h8a2 2 0 002-2V7H6v12z"/></svg>'
const iconFood = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="13" r="8"/><line x1="9" y1="3" x2="9" y2="7"/><line x1="7" y1="3" x2="7" y2="7"/><path d="M8 7 Q8 9 8 10 L8 13"/><path d="M15 3 L15 7 Q17 8 15 10 L15 13"/></svg>'
const iconPlus = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>'
const iconChevronDown = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M7.41 8.58L12 13.17l4.59-4.59L18 10l-6 6-6-6 1.41-1.42z"/></svg>'

const mealOrder = ['breakfast', 'lunch', 'dinner', 'snack']
const collapsed = reactive({
	breakfast: false,
	lunch: false,
	dinner: false,
	snack: false,
})
const deleteTarget = ref(null)

const isEmpty = computed(() => mealOrder.every(type => props.groups[type].length === 0))

const deleteMessage = computed(() => {
	return deleteTarget.value
		? t('calorietracker', 'Delete "{name}"?', { name: deleteTarget.value.foodName })
		: ''
})

const deleteDialogButtons = computed(() => [
	{
		label: t('calorietracker', 'Cancel'),
		type: 'secondary',
		callback: () => { deleteTarget.value = null },
	},
	{
		label: t('calorietracker', 'Delete'),
		type: 'error',
		callback: () => doDelete(),
	},
])

/**
 * Get the translated label for a meal type.
 * @param {string} type Meal type identifier
 */
function mealLabel(type) {
	const labels = {
		breakfast: t('calorietracker', 'Breakfast'),
		lunch: t('calorietracker', 'Lunch'),
		dinner: t('calorietracker', 'Dinner'),
		snack: t('calorietracker', 'Snack'),
	}
	return labels[type] ?? type
}

/**
 * Calculate total energy for a list of entries.
 * @param {Array} entries List of food entries
 */
function mealTotal(entries) {
	const kcal = entries.reduce((sum, e) => sum + Math.round(e.caloriesPer100g * e.amountGrams / 100), 0)
	return displayEnergy(kcal)
}

/**
 * Set the entry to be deleted and show confirmation.
 * @param {object} entry Food entry to delete
 */
function confirmDelete(entry) {
	deleteTarget.value = entry
}

/**
 *
 */
async function doDelete() {
	await store.deleteEntry(deleteTarget.value.id)
	deleteTarget.value = null
}
</script>

<style scoped>
.food-entry-list__add {
	display: flex;
	justify-content: flex-end;
	margin-bottom: 16px;
}

.food-entry-list__group {
	margin-bottom: 20px;
}

.food-entry-list__meal-heading-wrapper {
	margin: 0;
	font-size: 1em;
	font-weight: bold;
}

.food-entry-list__meal-heading {
	display: flex;
	align-items: center;
	gap: 4px;
	width: 100%;
	margin: 0;
	padding: 0;
	border: none;
	background: none;
	font: inherit;
	text-transform: capitalize;
	color: var(--color-main-text);
	cursor: pointer;
}

.food-entry-list__meal-heading:hover,
.food-entry-list__meal-heading:active {
	color: var(--color-main-text);
	background: none;
}

.food-entry-list__meal-heading:focus:not(:focus-visible) {
	outline: none;
}

.food-entry-list__meal-heading:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 2px;
	border-radius: var(--border-radius);
}

.food-entry-list__separator {
	border: none;
	border-top: 1px solid var(--color-border);
	margin: 4px 0 6px;
}

.food-entry-list__chevron {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
	width: 20px;
	height: 20px;
	transition: transform 0.2s ease;
}

.food-entry-list__chevron--collapsed {
	transform: rotate(-90deg);
}

.food-entry-list__meal-count {
	font-size: 0.8em;
	font-weight: normal;
	color: var(--color-text-maxcontrast);
	background: var(--color-background-hover);
	border-radius: var(--border-radius-pill);
	padding: 0 6px;
	min-width: 1.4em;
	text-align: center;
}

.food-entry-list__meal-total {
	margin-left: auto;
	font-size: 0.85em;
	font-weight: normal;
	color: var(--color-text-maxcontrast);
}

.food-entry-list__details {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 4px 12px;
}

.food-entry-list__detail {
	display: inline-block;
	white-space: nowrap;
	min-width: 4em;
}

.food-entry-list__detail--energy {
	min-width: 5.5em;
	font-weight: 500;
}

.food-entry-list__detail--macro {
	min-width: 4.5em;
}

.food-entry-list__group :deep(.form-box) {
	width: 100%;
}
</style>

<style>
.delete-dialog-content .dialog__text {
	padding: 16px 20px;
}
</style>
