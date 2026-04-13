<!--
  - SPDX-FileCopyrightText: 2026 Anna Larch
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppNavigation :aria-label="t('calorietracker', 'Calorie tracker navigation')">
		<template #default>
			<NcButton class="day-sidebar__new-button" variant="primary" @click="foodEntriesStore.openAddModal()">
				+ {{ t('calorietracker', 'Add new entry') }}
			</NcButton>
		</template>
		<template #footer>
			<div class="day-sidebar__settings-container">
				<NcButton variant="tertiary" wide @click="settingsStore.openSettings()">
					<template #icon>
						<svg xmlns="http://www.w3.org/2000/svg"
							class="day-sidebar__week-icon"
							viewBox="0 0 24 24"
							fill="none"
							stroke="currentColor"
							stroke-width="2"
							stroke-linecap="round"
							stroke-linejoin="round"
							aria-hidden="true">
							<circle cx="12" cy="12" r="3" />
							<path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" />
						</svg>
					</template>
					{{ t('calorietracker', 'App settings') }}
				</NcButton>
			</div>
		</template>
		<template #list>
			<NcAppNavigationItem v-for="week in weeks"
				:key="week.key"
				:name="week.label"
				:allow-collapse="true"
				:open="week.open"
				:to="{ path: '/week/' + week.key }">
				<template #icon>
					<svg xmlns="http://www.w3.org/2000/svg"
						class="day-sidebar__week-icon"
						viewBox="0 0 24 24"
						fill="none"
						stroke="currentColor"
						stroke-width="2"
						stroke-linecap="round"
						stroke-linejoin="round"
						aria-hidden="true">
						<rect x="3"
							y="4"
							width="18"
							height="17"
							rx="2" />
						<line x1="3"
							y1="9"
							x2="21"
							y2="9" />
						<line x1="8"
							y1="2"
							x2="8"
							y2="6" />
						<line x1="16"
							y1="2"
							x2="16"
							y2="6" />
					</svg>
				</template>
				<template #default>
					<ul class="day-sidebar__day-list">
						<li v-for="day in week.days"
							:key="day.date"
							class="day-sidebar__day-item"
							:class="{ 'day-sidebar__day-item--active': day.date === currentDate }"
							role="button"
							tabindex="0"
							:aria-label="t('calorietracker', 'View {day}', { day: day.label })"
							:aria-current="day.date === currentDate ? 'date' : undefined"
							@click="selectDay(day.date)"
							@keydown.enter.prevent="selectDay(day.date)"
							@keydown.space.prevent="selectDay(day.date)">
							<span class="day-sidebar__day-name">{{ day.label }}</span>
							<span v-if="day.summary" class="day-sidebar__day-stats">
								{{ displayEnergy(day.summary.totalKcal) }} {{ energyLabel }} &middot; {{ day.summary.itemCount }} {{ n('calorietracker', 'item', 'items', day.summary.itemCount) }}
							</span>
							<span v-else class="day-sidebar__day-stats day-sidebar__day-stats--empty">
								{{ t('calorietracker', 'No entries') }}
							</span>
						</li>
					</ul>
				</template>
			</NcAppNavigationItem>
		</template>
	</NcAppNavigation>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcButton from '@nextcloud/vue/components/NcButton'
import { useFoodEntriesStore } from '../stores/foodEntries.js'
import { useSettingsStore } from '../stores/settings.js'
import { useUnits } from '../composables/useUnits.js'
import { toLocalDateString } from '../utils/date.js'

const route = useRoute()
const router = useRouter()
const foodEntriesStore = useFoodEntriesStore()
const settingsStore = useSettingsStore()
const { displayEnergy, energyLabel } = useUnits()
const { currentDate, daySummaries } = storeToRefs(foodEntriesStore)

const weeks = computed(() => {
	const today = new Date()
	const todayStr = toLocalDateString(today)
	const days = []
	for (let i = 0; i < 30; i++) {
		const d = new Date(today.getFullYear(), today.getMonth(), today.getDate() - i)
		const date = toLocalDateString(d)
		const label = i === 0
			? t('calorietracker', 'Today')
			: i === 1
				? t('calorietracker', 'Yesterday')
				: d.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' })

		const summary = daySummaries.value[date] ?? null

		const dow = (d.getDay() + 6) % 7
		const monday = new Date(d.getFullYear(), d.getMonth(), d.getDate() - dow)
		const weekKey = toLocalDateString(monday)
		const weekLabel = monday.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
			+ ' – '
			+ new Date(monday.getFullYear(), monday.getMonth(), monday.getDate() + 6)
				.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })

		days.push({ date, label, summary, weekKey, weekLabel })
	}

	const weekMap = new Map()
	for (const day of days) {
		if (!weekMap.has(day.weekKey)) {
			const open = days
				.filter(d => d.weekKey === day.weekKey)
				.some(d => d.date === currentDate.value || d.date === todayStr)
			weekMap.set(day.weekKey, { key: day.weekKey, label: day.weekLabel, open, days: [] })
		}
		weekMap.get(day.weekKey).days.push(day)
	}

	return [...weekMap.values()]
})

/**
 * Navigate to the selected day.
 * @param {string} date Date string in YYYY-MM-DD format
 */
function selectDay(date) {
	// When already on the day view, use setDate which also fetches entries.
	if (route.path === '/') {
		foodEntriesStore.setDate(date)
		return
	}
	// When navigating from a different view (e.g. the weekly overview), only
	// update the date here and let DayView fetch entries once on mount.
	foodEntriesStore.currentDate = date
	router.push('/')
}

foodEntriesStore.fetchSummaries()
settingsStore.fetchSettings()
</script>

<style scoped>
.day-sidebar__week-icon {
	width: 20px;
	height: 20px;
}

.day-sidebar__new-button {
	width: calc(100% - 16px);
	margin: 8px;
}

.day-sidebar__day-list {
	list-style: none;
	margin: 0;
	padding: 0;
}

.day-sidebar__day-item {
	display: flex;
	flex-direction: column;
	padding: 6px 16px 6px 32px;
	cursor: pointer;
	border-radius: var(--border-radius);
	line-height: 1.3;
}

.day-sidebar__day-item:hover {
	background: var(--color-background-hover);
}

.day-sidebar__day-item:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: -2px;
}

.day-sidebar__day-item--active {
	background: var(--color-primary-element-light);
	font-weight: bold;
}

.day-sidebar__day-name {
	font-size: 0.9em;
	color: var(--color-main-text);
}

.day-sidebar__day-stats {
	font-size: 0.78em;
	color: var(--color-text-maxcontrast);
}

.day-sidebar__day-stats--empty {
	font-style: italic;
}

.day-sidebar__settings-container {
	display: flex;
	flex-direction: column;
	padding: calc(2 * var(--default-grid-baseline));
}
</style>
