<!--
  - SPDX-FileCopyrightText: 2026 Anna Larch
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="week-view">
		<h2 class="week-view__heading">
			{{ weekHeading }}
		</h2>

		<div class="week-view__table-wrap">
			<table class="week-view__table">
				<thead>
					<tr>
						<th class="week-view__col-day" scope="col">
							{{ t('calorietracker', 'Day') }}
						</th>
						<th class="week-view__col-num" scope="col">
							{{ t('calorietracker', 'Calories (kcal)') }}
						</th>
						<th class="week-view__col-num" scope="col">
							{{ t('calorietracker', 'Protein (g)') }}
						</th>
						<th class="week-view__col-num" scope="col">
							{{ t('calorietracker', 'Carbs (g)') }}
						</th>
						<th class="week-view__col-num" scope="col">
							{{ t('calorietracker', 'Fat (g)') }}
						</th>
						<th class="week-view__col-num" scope="col">
							{{ t('calorietracker', 'Items') }}
						</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="day in days"
						:key="day.date"
						class="week-view__day-row"
						:class="{
							'week-view__day-row--today': day.isToday,
							'week-view__day-row--future': day.isFuture,
							'week-view__day-row--empty': !day.summary,
						}"
						:role="day.isFuture ? undefined : 'button'"
						:tabindex="day.isFuture ? undefined : 0"
						@click="day.isFuture || goToDay(day.date)"
						@keydown.enter.prevent="day.isFuture || goToDay(day.date)"
						@keydown.space.prevent="day.isFuture || goToDay(day.date)">
						<td class="week-view__col-day">
							<span class="week-view__day-name">{{ day.label }}</span>
							<span class="week-view__day-date">{{ day.shortDate }}</span>
						</td>
						<td class="week-view__col-num">
							{{ day.summary ? day.summary.totalKcal : '—' }}
						</td>
						<td class="week-view__col-num">
							{{ day.summary ? day.summary.totalProteinG : '—' }}
						</td>
						<td class="week-view__col-num">
							{{ day.summary ? day.summary.totalCarbsG : '—' }}
						</td>
						<td class="week-view__col-num">
							{{ day.summary ? day.summary.totalFatG : '—' }}
						</td>
						<td class="week-view__col-num">
							{{ day.summary ? day.summary.itemCount : '—' }}
						</td>
					</tr>
				</tbody>
				<tfoot>
					<tr class="week-view__total-row">
						<td>{{ t('calorietracker', 'Total') }}</td>
						<td class="week-view__col-num">
							{{ totals.kcal }}
						</td>
						<td class="week-view__col-num">
							{{ totals.protein }}
						</td>
						<td class="week-view__col-num">
							{{ totals.carbs }}
						</td>
						<td class="week-view__col-num">
							{{ totals.fat }}
						</td>
						<td class="week-view__col-num">
							{{ totals.items }}
						</td>
					</tr>
					<tr v-if="activeDays > 0" class="week-view__avg-row">
						<td>{{ n('calorietracker', 'Avg ({n} day)', 'Avg ({n} days)', activeDays, { n: activeDays }) }}</td>
						<td class="week-view__col-num">
							{{ averages.kcal }}
						</td>
						<td class="week-view__col-num">
							{{ averages.protein }}
						</td>
						<td class="week-view__col-num">
							{{ averages.carbs }}
						</td>
						<td class="week-view__col-num">
							{{ averages.fat }}
						</td>
						<td class="week-view__col-num">
							{{ averages.items }}
						</td>
					</tr>
				</tfoot>
			</table>
		</div>
	</div>
</template>

<script setup>
import { computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import { useFoodEntriesStore } from '../stores/foodEntries.js'
import { toLocalDateString } from '../utils/date.js'

const route = useRoute()
const router = useRouter()
const foodEntriesStore = useFoodEntriesStore()
const { daySummaries } = storeToRefs(foodEntriesStore)

// Validate the weekStart route param before use — a malformed URL like
// /week/2026-99-99 would otherwise cause toLocaleDateString to throw "Invalid time value".
const parsedMonday = computed(() => {
	const raw = route.params.weekStart
	if (typeof raw !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
		return null
	}
	const [year, month, day] = raw.split('-').map(Number)
	const d = new Date(year, month - 1, day)
	// Confirm the date didn't roll over (e.g. month 13 → next year)
	if (d.getFullYear() !== year || d.getMonth() !== month - 1 || d.getDate() !== day) {
		return null
	}
	return d
})

const weekHeading = computed(() => {
	const monday = parsedMonday.value
	if (!monday) {
		return t('calorietracker', 'Invalid week')
	}
	const sunday = new Date(monday)
	sunday.setDate(sunday.getDate() + 6)
	return monday.toLocaleDateString(undefined, { month: 'long', day: 'numeric' })
		+ ' – '
		+ sunday.toLocaleDateString(undefined, { month: 'long', day: 'numeric', year: 'numeric' })
})

const days = computed(() => {
	const monday = parsedMonday.value
	if (!monday) {
		return []
	}
	const todayStr = toLocalDateString()
	return Array.from({ length: 7 }, (_, i) => {
		const d = new Date(monday)
		d.setDate(monday.getDate() + i)
		const date = toLocalDateString(d)
		const isToday = date === todayStr
		const isFuture = date > todayStr
		const label = isToday
			? t('calorietracker', 'Today')
			: d.toLocaleDateString(undefined, { weekday: 'long' })
		const shortDate = d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
		const summary = daySummaries.value[date] ?? null
		return { date, label, shortDate, isToday, isFuture, summary }
	})
})

const totals = computed(() => {
	let kcal = 0; let protein = 0; let carbs = 0; let fat = 0; let items = 0
	for (const day of days.value) {
		if (day.summary) {
			kcal += day.summary.totalKcal
			protein += day.summary.totalProteinG
			carbs += day.summary.totalCarbsG
			fat += day.summary.totalFatG
			items += day.summary.itemCount
		}
	}
	return { kcal, protein, carbs, fat, items }
})

const activeDays = computed(() => days.value.filter(d => d.summary).length)

const averages = computed(() => {
	const nd = activeDays.value
	if (nd === 0) return { kcal: '—', protein: '—', carbs: '—', fat: '—', items: '—' }
	return {
		kcal: Math.round(totals.value.kcal / nd),
		protein: Math.round(totals.value.protein / nd),
		carbs: Math.round(totals.value.carbs / nd),
		fat: Math.round(totals.value.fat / nd),
		items: Math.round(totals.value.items / nd * 10) / 10,
	}
})

// Set currentDate without calling setDate() — DayView fetches entries on its
// own mount, so calling setDate() here would trigger a duplicate request.
function goToDay(date) {
	foodEntriesStore.currentDate = date
	router.push('/')
}

function fetchWeekSummaries() {
	const weekDays = days.value
	if (weekDays.length > 0) {
		// Fetch the specific week range so data is available even when
		// this week falls outside the sidebar's default 30-day window.
		foodEntriesStore.fetchSummaries(weekDays[0].date, weekDays[weekDays.length - 1].date)
	} else {
		foodEntriesStore.fetchSummaries()
	}
}

// Vue Router reuses the same component instance when navigating between
// weeks, so onMounted won't re-run. Watching the param covers both the
// initial load and subsequent week-to-week navigation.
watch(() => route.params.weekStart, fetchWeekSummaries, { immediate: true })
</script>

<style scoped>
.week-view {
	max-width: 720px;
	margin: 0 auto;
	padding: 24px 16px;
	display: flex;
	flex-direction: column;
	gap: 20px;
}

.week-view__heading {
	margin: 0;
	font-size: 1.2em;
	text-align: center;
}

.week-view__table-wrap {
	background: var(--color-background-soft);
	border-radius: var(--border-radius-large);
	overflow: hidden;
}

.week-view__table {
	width: 100%;
	border-collapse: collapse;
	font-size: 0.9em;
}

.week-view__table thead tr {
	border-bottom: 2px solid var(--color-border);
}

.week-view__table th {
	padding: 10px 12px;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
	text-transform: uppercase;
	letter-spacing: 0.04em;
	font-size: 0.8em;
}

.week-view__col-num {
	text-align: right;
}

.week-view__col-day {
	text-align: left;
}

.week-view__day-row {
	cursor: pointer;
	border-bottom: 1px solid var(--color-border-dark);
	transition: background 0.1s;
}

.week-view__day-row:hover {
	background: var(--color-background-hover);
}

.week-view__day-row:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: -2px;
}

.week-view__day-row--today td:first-child {
	border-left: 3px solid var(--color-primary-element);
}

.week-view__day-row--future {
	opacity: 0.45;
	pointer-events: none;
}

.week-view__day-row td {
	padding: 10px 12px;
}

.week-view__day-row--empty td:not(:first-child) {
	color: var(--color-text-maxcontrast);
}

.week-view__day-name {
	display: block;
	color: var(--color-main-text);
}

.week-view__day-date {
	display: block;
	font-size: 0.85em;
	color: var(--color-text-maxcontrast);
}

.week-view__total-row,
.week-view__avg-row {
	border-top: 2px solid var(--color-border);
	font-weight: 600;
}

.week-view__total-row td,
.week-view__avg-row td {
	padding: 10px 12px;
}

.week-view__avg-row {
	font-weight: normal;
	color: var(--color-text-maxcontrast);
	font-size: 0.9em;
}
</style>
