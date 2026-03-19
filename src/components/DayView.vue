<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="day-view">
		<!-- Date navigation -->
		<div class="day-view__header">
			<NcButton type="tertiary" :aria-label="t('calorietracker', 'Previous day')" @click="changeDay(-1)">
				‹
			</NcButton>
			<h2 class="day-view__date">
				{{ formattedDate }}
			</h2>
			<NcButton type="tertiary" :aria-label="t('calorietracker', 'Next day')" @click="changeDay(1)">
				›
			</NcButton>
			<NcButton v-if="!isToday" type="tertiary" @click="goToToday">
				{{ t('calorietracker', 'Today') }}
			</NcButton>
		</div>

		<!-- Calorie summary -->
		<div class="day-view__summary">
			{{ t('calorietracker', 'Total: {kcal} kcal', { kcal: totalCalories }) }}
		</div>

		<!-- Macro breakdown -->
		<div v-if="macroTotals" class="day-view__macros">
			<div class="day-view__macro">
				<span class="day-view__macro-label">{{ t('calorietracker', 'Protein') }}</span>
				<NcProgressBar class="day-view__macro-bar day-view__macro-bar--protein" :value="macroTotals.protein.pct" />
				<span class="day-view__macro-value">{{ macroTotals.protein.grams }}g &middot; {{ macroTotals.protein.kcal }} kcal &middot; {{ macroTotals.protein.pct }}%</span>
			</div>
			<div class="day-view__macro">
				<span class="day-view__macro-label">{{ t('calorietracker', 'Carbs') }}</span>
				<NcProgressBar class="day-view__macro-bar day-view__macro-bar--carbs" :value="macroTotals.carbs.pct" />
				<span class="day-view__macro-value">{{ macroTotals.carbs.grams }}g &middot; {{ macroTotals.carbs.kcal }} kcal &middot; {{ macroTotals.carbs.pct }}%</span>
			</div>
			<div class="day-view__macro">
				<span class="day-view__macro-label">{{ t('calorietracker', 'Fat') }}</span>
				<NcProgressBar class="day-view__macro-bar day-view__macro-bar--fat" :value="macroTotals.fat.pct" />
				<span class="day-view__macro-value">{{ macroTotals.fat.grams }}g &middot; {{ macroTotals.fat.kcal }} kcal &middot; {{ macroTotals.fat.pct }}%</span>
			</div>
		</div>

		<!-- Entry list -->
		<FoodEntryList :groups="entriesByMealType" />
	</div>
</template>

<script>
import { mapGetters, mapState } from 'vuex'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcProgressBar from '@nextcloud/vue/dist/Components/NcProgressBar.js'
import FoodEntryList from './FoodEntryList.vue'
import { toLocalDateString } from '../utils/date.js'

export default {
	name: 'DayView',

	components: {
		NcButton,
		NcProgressBar,
		FoodEntryList,
	},

	computed: {
		...mapState('foodEntries', ['currentDate']),
		...mapGetters('foodEntries', ['totalCalories', 'entriesByMealType', 'macroTotals']),

		formattedDate() {
			const [year, month, day] = this.currentDate.split('-')
			return new Date(year, month - 1, day).toLocaleDateString(undefined, {
				weekday: 'long',
				year: 'numeric',
				month: 'long',
				day: 'numeric',
			})
		},

		isToday() {
			return this.currentDate === toLocalDateString()
		},
	},

	created() {
		this.$store.dispatch('foodEntries/fetchEntries')
	},

	methods: {
		changeDay(delta) {
			const [year, month, day] = this.currentDate.split('-').map(Number)
			const date = new Date(year, month - 1, day)
			date.setDate(date.getDate() + delta)
			this.$store.dispatch('foodEntries/setDate', toLocalDateString(date))
		},

		goToToday() {
			this.$store.dispatch('foodEntries/setDate', toLocalDateString())
		},

	},
}
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

.day-view__macro-bar--protein :deep(.progress-bar) { background: #4caf50; }
.day-view__macro-bar--carbs   :deep(.progress-bar) { background: #ff9800; }
.day-view__macro-bar--fat     :deep(.progress-bar) { background: #f44336; }

.day-view__macro-value {
	font-size: 0.85em;
	color: var(--color-text-maxcontrast);
	white-space: nowrap;
}
</style>
