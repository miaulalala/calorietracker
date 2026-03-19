<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppNavigation>
		<template #default>
			<NcButton class="day-sidebar__new-button" type="primary" @click="$store.dispatch('foodEntries/openAddModal')">
				+ {{ t('calorietracker', 'Add new entry') }}
			</NcButton>
		</template>
		<template #footer>
			<NcAppNavigationSettings :name="t('calorietracker', 'Daily goals')" @click="$router.push('/settings')" />
		</template>
		<template #list>
			<NcAppNavigationItem
				v-for="week in weeks"
				:key="week.key"
				:name="week.label"
				:allow-collapse="true"
				:open="week.open">
				<template #icon>
					<svg xmlns="http://www.w3.org/2000/svg" class="day-sidebar__week-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<rect x="3" y="4" width="18" height="17" rx="2" />
						<line x1="3" y1="9" x2="21" y2="9" />
						<line x1="8" y1="2" x2="8" y2="6" />
						<line x1="16" y1="2" x2="16" y2="6" />
					</svg>
				</template>
				<template #default>
					<ul class="day-sidebar__day-list">
						<li
							v-for="day in week.days"
							:key="day.date"
							class="day-sidebar__day-item"
							:class="{ 'day-sidebar__day-item--active': day.date === currentDate }"
							@click="selectDay(day.date)">
							<span class="day-sidebar__day-name">{{ day.label }}</span>
							<span v-if="day.summary" class="day-sidebar__day-stats">
								{{ day.summary.totalKcal }} kcal &middot; {{ day.summary.totalKj }} kJ &middot; {{ day.summary.itemCount }} {{ n('calorietracker', 'item', 'items', day.summary.itemCount) }}
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

<script>
import NcAppNavigation from '@nextcloud/vue/dist/Components/NcAppNavigation.js'
import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'
import NcAppNavigationSettings from '@nextcloud/vue/dist/Components/NcAppNavigationSettings.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import { mapState } from 'vuex'
import { toLocalDateString } from '../utils/date.js'

export default {
	name: 'DaySidebar',

	components: { NcAppNavigation, NcAppNavigationItem, NcAppNavigationSettings, NcButton },

	computed: {
		...mapState('foodEntries', ['currentDate', 'daySummaries']),

		weeks() {
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

				const raw = this.daySummaries[date]
				const summary = raw
					? { ...raw, totalKj: Math.round(raw.totalKcal * 4.184) }
					: null

				// ISO week: Monday = day 1
				const dow = (d.getDay() + 6) % 7 // 0=Mon … 6=Sun
				const monday = new Date(d.getFullYear(), d.getMonth(), d.getDate() - dow)
				const weekKey = toLocalDateString(monday)
				const weekLabel = monday.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
					+ ' – '
					+ new Date(monday.getFullYear(), monday.getMonth(), monday.getDate() + 6)
						.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })

				days.push({ date, label, summary, weekKey, weekLabel })
			}

			// Group into weeks preserving order (most recent first)
			const weekMap = new Map()
			for (const day of days) {
				if (!weekMap.has(day.weekKey)) {
					// Week is open if it contains the selected date or today
					const open = days
						.filter(d => d.weekKey === day.weekKey)
						.some(d => d.date === this.currentDate || d.date === todayStr)
					weekMap.set(day.weekKey, { key: day.weekKey, label: day.weekLabel, open, days: [] })
				}
				weekMap.get(day.weekKey).days.push(day)
			}

			return [...weekMap.values()]
		},
	},

	created() {
		this.$store.dispatch('foodEntries/fetchSummaries')
		this.$store.dispatch('settings/fetchSettings')
	},

	methods: {
		selectDay(date) {
			this.$store.dispatch('foodEntries/setDate', date)
		},
	},
}
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
</style>
