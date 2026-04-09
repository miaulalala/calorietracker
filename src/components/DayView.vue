<!--
  - SPDX-FileCopyrightText: 2026 Anna Larch
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="day-view">
		<!-- Date navigation -->
		<div class="day-view__header">
			<NcButton variant="tertiary" :aria-label="t('calorietracker', 'Previous day')" @click="changeDay(-1)">
				<template #icon>
					‹
				</template>
			</NcButton>
			<h2 class="day-view__date">
				{{ formattedDate }}
			</h2>
			<NcButton variant="tertiary" :aria-label="t('calorietracker', 'Next day')" @click="changeDay(1)">
				<template #icon>
					›
				</template>
			</NcButton>
			<NcButton v-if="!isToday" variant="tertiary" @click="goToToday">
				{{ t('calorietracker', 'Today') }}
			</NcButton>
		</div>

		<!-- Calorie summary -->
		<div class="day-view__summary">
			<template v-if="calorieGoal > 0">
				<div class="day-view__summary-line">
					<span>{{ t('calorietracker', '{energy} / {goal} {unit}', { energy: totalCalories, goal: displayCalorieGoal, unit: energyLabel }) }}</span>
					<span class="day-view__summary-pct">{{ caloriePct }}%</span>
				</div>
				<NcProgressBar class="day-view__calorie-bar" :value="Math.min(caloriePct, 100)" aria-hidden="true" />
			</template>
			<template v-else>
				{{ t('calorietracker', 'Total: {energy} {unit}', { energy: totalCalories, unit: energyLabel }) }}
			</template>
		</div>

		<!-- Macro breakdown -->
		<div v-if="macroTotalsWithGoals" class="day-view__macros">
			<div class="day-view__macro">
				<span class="day-view__macro-label">{{ t('calorietracker', 'Protein') }}</span>
				<NcProgressBar class="day-view__macro-bar day-view__macro-bar--protein" :value="macroTotalsWithGoals.protein.barPct" aria-hidden="true" />
				<span class="day-view__macro-value">{{ displayWeight(macroTotalsWithGoals.protein.grams) }}{{ weightLabel }}<template v-if="proteinGoal > 0"> / {{ displayGoal(proteinGoal) }}{{ weightLabel }}</template></span>
			</div>
			<div class="day-view__macro">
				<span class="day-view__macro-label">{{ t('calorietracker', 'Carbs') }}</span>
				<NcProgressBar class="day-view__macro-bar day-view__macro-bar--carbs" :value="macroTotalsWithGoals.carbs.barPct" aria-hidden="true" />
				<span class="day-view__macro-value">{{ displayWeight(macroTotalsWithGoals.carbs.grams) }}{{ weightLabel }}<template v-if="carbsGoal > 0"> / {{ displayGoal(carbsGoal) }}{{ weightLabel }}</template></span>
			</div>
			<div class="day-view__macro">
				<span class="day-view__macro-label">{{ t('calorietracker', 'Fat') }}</span>
				<NcProgressBar class="day-view__macro-bar day-view__macro-bar--fat" :value="macroTotalsWithGoals.fat.barPct" aria-hidden="true" />
				<span class="day-view__macro-value">{{ displayWeight(macroTotalsWithGoals.fat.grams) }}{{ weightLabel }}<template v-if="fatGoal > 0"> / {{ displayGoal(fatGoal) }}{{ weightLabel }}</template></span>
			</div>
		</div>

		<!-- Entry list -->
		<FoodEntryList :groups="entriesByMealType" />
	</div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcProgressBar from '@nextcloud/vue/components/NcProgressBar'
import FoodEntryList from './FoodEntryList.vue'
import { useFoodEntriesStore } from '../stores/foodEntries.js'
import { useSettingsStore } from '../stores/settings.js'
import { useUnits } from '../composables/useUnits.js'
import { toLocalDateString } from '../utils/date.js'

const foodEntriesStore = useFoodEntriesStore()
const settingsStore = useSettingsStore()
const { displayEnergy, displayWeight, energyLabel, weightLabel } = useUnits()

const { currentDate } = storeToRefs(foodEntriesStore)
const { dailyCalorieGoal: calorieGoal, dailyProteinGoal: proteinGoal, dailyCarbsGoal: carbsGoal, dailyFatGoal: fatGoal } = storeToRefs(settingsStore)

const totalCalories = computed(() => displayEnergy(foodEntriesStore.totalCalories))
const displayCalorieGoal = computed(() => displayEnergy(calorieGoal.value))
const displayGoal = (goalGrams) => displayWeight(goalGrams)
const entriesByMealType = computed(() => foodEntriesStore.entriesByMealType)
const macroTotals = computed(() => foodEntriesStore.macroTotals)

const caloriePct = computed(() => {
	if (!calorieGoal.value) return 0
	// Use raw kcal values for both sides so the percentage is unit-independent.
	return Math.round(foodEntriesStore.totalCalories / calorieGoal.value * 100)
})

const macroTotalsWithGoals = computed(() => {
	if (!macroTotals.value) return null
	const withBar = (macro, goal) => ({
		...macro,
		barPct: goal > 0
			? Math.min(Math.round(macro.grams / goal * 100), 100)
			: macro.pct,
	})
	return {
		protein: withBar(macroTotals.value.protein, proteinGoal.value),
		carbs: withBar(macroTotals.value.carbs, carbsGoal.value),
		fat: withBar(macroTotals.value.fat, fatGoal.value),
	}
})

const formattedDate = computed(() => {
	const [year, month, day] = currentDate.value.split('-')
	return new Date(year, month - 1, day).toLocaleDateString(undefined, {
		weekday: 'long',
		year: 'numeric',
		month: 'long',
		day: 'numeric',
	})
})

const isToday = computed(() => currentDate.value === toLocalDateString())

/**
 * Navigate forward or backward by a number of days.
 * @param {number} delta Number of days to shift
 */
function changeDay(delta) {
	const [year, month, day] = currentDate.value.split('-').map(Number)
	const date = new Date(year, month - 1, day)
	date.setDate(date.getDate() + delta)
	foodEntriesStore.setDate(toLocalDateString(date))
}

/**
 *
 */
function goToToday() {
	foodEntriesStore.setDate(toLocalDateString())
}

onMounted(() => {
	foodEntriesStore.fetchEntries()
})
</script>

<style scoped>
.day-view {
	max-width: 720px;
	margin: 0 auto;
	padding: 24px 16px;
	display: flex;
	flex-direction: column;
	gap: 20px;
}

.day-view__header {
	display: flex;
	align-items: center;
	gap: 8px;
}

.day-view__date {
	flex: 1;
	margin: 0;
	font-size: 1.2em;
	text-align: center;
}

.day-view__summary {
	font-size: 1.1em;
	font-weight: bold;
	text-align: center;
	color: var(--color-primary-element);
}

.day-view__summary-line {
	display: flex;
	justify-content: center;
	align-items: baseline;
	gap: 8px;
	margin-bottom: 8px;
}

.day-view__summary-pct {
	font-size: 0.85em;
	color: var(--color-text-maxcontrast);
}

.day-view__calorie-bar {
	width: 100%;
}

.day-view__macros {
	display: flex;
	flex-direction: column;
	gap: 8px;
	background: var(--color-background-soft);
	border-radius: var(--border-radius-large);
	padding: 16px;
}

.day-view__macro {
	display: grid;
	grid-template-columns: 80px 1fr auto;
	align-items: center;
	gap: 10px;
}

.day-view__macro-label {
	font-size: 0.9em;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
	text-transform: uppercase;
	letter-spacing: 0.04em;
}

.day-view__macro-bar {
	flex: 1;
}

.day-view__macro-bar--protein :deep(.progress-bar),
.day-view__macro-bar--carbs   :deep(.progress-bar),
.day-view__macro-bar--fat     :deep(.progress-bar) { background: var(--color-primary-element-light); }

.day-view__macro-value {
	font-size: 0.85em;
	color: var(--color-text-maxcontrast);
	white-space: nowrap;
}
</style>
