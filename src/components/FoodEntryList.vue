<!--
  - SPDX-FileCopyrightText: 2026 Anna Larch
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="food-entry-list">
		<div v-if="!isEmpty" class="food-entry-list__add">
			<NcButton variant="primary" @click="store.openAddModal()">
				{{ t('calorietracker', 'Add food') }}
			</NcButton>
		</div>

		<template v-for="mealType in mealOrder">
			<div v-if="groups[mealType].length > 0" :key="mealType" class="food-entry-list__group">
				<h4 class="food-entry-list__meal-heading">
					{{ mealLabel(mealType) }}
					<span class="food-entry-list__meal-total">
						{{ mealTotal(groups[mealType]) }} {{ energyLabel }}
					</span>
				</h4>

				<ul class="food-entry-list__items">
					<NcListItem v-for="entry in groups[mealType]"
						:key="entry.id"
						:name="entry.foodName"
						:subname="entrySubname(entry)"
						:compact="true">
						<template #actions>
							<NcActionButton @click="store.openAddModal(entry)">
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

		<NcEmptyContent v-if="isEmpty"
			:name="t('calorietracker', 'No entries yet')"
			:description="t('calorietracker', 'Track your first meal for this day.')">
			<template #icon>
				<NcIconSvgWrapper :svg="iconFood" />
			</template>
			<template #action>
				<NcButton variant="primary" @click="store.openAddModal()">
					{{ t('calorietracker', 'Add food') }}
				</NcButton>
			</template>
		</NcEmptyContent>

		<NcDialog v-if="deleteTarget"
			:name="t('calorietracker', 'Delete entry')"
			:message="deleteMessage"
			:buttons="deleteDialogButtons"
			@closing="deleteTarget = null" />
	</div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
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

const mealOrder = ['breakfast', 'lunch', 'dinner', 'snack']
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
		type: 'tertiary',
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
 * Build the subtitle string for a food entry.
 * @param {object} entry Food entry object
 */
function entrySubname(entry) {
	const parts = [
		displayWeight(entry.amountGrams) + weightLabel.value,
		entryEnergy(entry.caloriesPer100g, entry.amountGrams) + ' ' + energyLabel.value,
	]
	const wu = weightLabel.value
	const macros = []
	if (entry.proteinPer100g != null) {
		macros.push('P ' + entryMacroGrams(entry.proteinPer100g, entry.amountGrams) + wu)
	}
	if (entry.carbsPer100g != null) {
		macros.push('C ' + entryMacroGrams(entry.carbsPer100g, entry.amountGrams) + wu)
	}
	if (entry.fatPer100g != null) {
		macros.push('F ' + entryMacroGrams(entry.fatPer100g, entry.amountGrams) + wu)
	}
	if (macros.length > 0) {
		parts.push(macros.join('  '))
	}
	return parts.join(' · ')
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
