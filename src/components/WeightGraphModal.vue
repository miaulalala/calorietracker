<!--
  - SPDX-FileCopyrightText: 2026 Anna Larch
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcModal size="normal"
		:name="t('calorietracker', 'Weight history')"
		@close="$emit('close')">
		<div class="weight-graph">
			<h2 class="weight-graph__title">
				{{ t('calorietracker', 'Weight history') }}
			</h2>

			<div class="weight-graph__periods">
				<NcButton v-for="p in periods"
					:key="p.days"
					:variant="activePeriod === p.days ? 'primary' : 'tertiary'"
					size="small"
					@click="setPeriod(p.days)">
					{{ p.label }}
				</NcButton>
			</div>

			<div v-if="loading" class="weight-graph__status">
				<NcLoadingIcon :size="32" />
			</div>

			<p v-else-if="points.length === 0" class="weight-graph__status weight-graph__status--empty">
				{{ t('calorietracker', 'No weight data for this period.') }}
			</p>

			<template v-else>
				<svg class="weight-graph__chart"
					:viewBox="`0 0 ${VW} ${VH}`"
					role="img"
					:aria-label="t('calorietracker', 'Weight over time chart')">
					<!-- Y-axis gridlines and labels -->
					<g class="weight-graph__y-axis">
						<line v-for="tick in yTicks"
							:key="tick.value"
							:x1="PAD_LEFT"
							:y1="yScale(tick.value)"
							:x2="VW - PAD_RIGHT"
							:y2="yScale(tick.value)"
							class="weight-graph__gridline" />
						<text v-for="tick in yTicks"
							:key="'l' + tick.value"
							:x="PAD_LEFT - 6"
							:y="yScale(tick.value)"
							class="weight-graph__axis-label weight-graph__axis-label--y">
							{{ tick.label }}
						</text>
					</g>

					<!-- X-axis labels -->
					<g class="weight-graph__x-axis">
						<text v-for="tick in xTicks"
							:key="tick.ts"
							:x="xScale(tick.ts)"
							:y="VH - PAD_BOTTOM + 14"
							class="weight-graph__axis-label weight-graph__axis-label--x">
							{{ tick.label }}
						</text>
					</g>

					<!-- Line -->
					<polyline
						:points="polylinePoints"
						class="weight-graph__line"
						fill="none" />

					<!-- Data points -->
					<circle v-for="p in points"
						:key="p.ts"
						:cx="xScale(p.ts)"
						:cy="yScale(p.weight)"
						r="3"
						class="weight-graph__dot">
						<title>{{ p.dateLabel }}: {{ p.displayWeight }}</title>
					</circle>
				</svg>

				<p class="weight-graph__latest">
					{{ t('calorietracker', 'Latest: {weight} {unit}', { weight: points[points.length - 1].displayWeight, unit: bodyWeightLabel }) }}
				</p>
			</template>
		</div>
	</NcModal>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { useWeightLogStore } from '../stores/weightLog.js'
import { useUnits } from '../composables/useUnits.js'

defineEmits(['close'])

const store = useWeightLogStore()
const { displayBodyWeight, bodyWeightLabel } = useUnits()

// SVG layout constants
const VW = 480
const VH = 200
const PAD_LEFT = 46
const PAD_RIGHT = 12
const PAD_TOP = 16
const PAD_BOTTOM = 28
const PLOT_W = VW - PAD_LEFT - PAD_RIGHT
const PLOT_H = VH - PAD_TOP - PAD_BOTTOM

const loading = ref(false)
const activePeriod = ref(90)

const periods = [
	{ days: 30, label: t('calorietracker', '30 days') },
	{ days: 90, label: t('calorietracker', '90 days') },
	{ days: 365, label: t('calorietracker', '1 year') },
]

const points = computed(() => {
	return store.history
		.map(entry => {
			const weight = displayBodyWeight(entry.weightKg)
			const ts = new Date(entry.loggedAt).getTime()
			const dateLabel = new Date(entry.loggedAt).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
			return { ts, weight, displayWeight: weight, dateLabel }
		})
		.filter(p => !isNaN(p.ts) && p.weight > 0)
		.sort((a, b) => a.ts - b.ts)
})

const yMin = computed(() => {
	if (points.value.length === 0) return 0
	const min = Math.min(...points.value.map(p => p.weight))
	return Math.floor(min - Math.max(1, (min * 0.02)))
})

const yMax = computed(() => {
	if (points.value.length === 0) return 1
	const max = Math.max(...points.value.map(p => p.weight))
	return Math.ceil(max + Math.max(1, (max * 0.02)))
})

const xMin = computed(() => points.value[0]?.ts ?? 0)
const xMax = computed(() => points.value[points.value.length - 1]?.ts ?? 1)

/**
 * @param {number} ts Unix timestamp
 */
function xScale(ts) {
	if (xMax.value === xMin.value) return PAD_LEFT + PLOT_W / 2
	return PAD_LEFT + (ts - xMin.value) / (xMax.value - xMin.value) * PLOT_W
}

/**
 * @param {number} w Weight value
 */
function yScale(w) {
	return PAD_TOP + PLOT_H - (w - yMin.value) / (yMax.value - yMin.value) * PLOT_H
}

const polylinePoints = computed(() =>
	points.value.map(p => `${xScale(p.ts)},${yScale(p.weight)}`).join(' '),
)

const yTicks = computed(() => {
	const range = yMax.value - yMin.value
	const step = range <= 5 ? 1 : range <= 20 ? Math.ceil(range / 4) : Math.ceil(range / 4 / 5) * 5
	const ticks = []
	for (let v = yMin.value; v <= yMax.value; v += step) {
		ticks.push({ value: v, label: String(v) })
	}
	return ticks
})

const xTicks = computed(() => {
	if (points.value.length === 0) return []
	const count = Math.min(5, points.value.length)
	const step = Math.floor(points.value.length / count)
	return points.value
		.filter((_, i) => i % step === 0 || i === points.value.length - 1)
		.slice(0, 6)
		.map(p => ({ ts: p.ts, label: p.dateLabel }))
})

/**
 * @param {number} days Number of days to fetch
 */
async function setPeriod(days) {
	activePeriod.value = days
	loading.value = true
	try {
		await store.fetchHistory(days)
	} finally {
		loading.value = false
	}
}

watch(() => store.graphModalOpen, (open) => {
	if (open) setPeriod(activePeriod.value)
}, { immediate: true })
</script>

<style scoped>
.weight-graph {
	display: flex;
	flex-direction: column;
	gap: 16px;
	padding: 20px;
}

.weight-graph__title {
	margin: 0;
	font-size: 1.2em;
}

.weight-graph__periods {
	display: flex;
	gap: 6px;
}

.weight-graph__status {
	display: flex;
	justify-content: center;
	align-items: center;
	min-height: 120px;
}

.weight-graph__status--empty {
	color: var(--color-text-maxcontrast);
	font-size: 0.9em;
}

.weight-graph__chart {
	width: 100%;
	overflow: visible;
}

.weight-graph__gridline {
	stroke: var(--color-border);
	stroke-width: 1;
}

.weight-graph__line {
	stroke: var(--color-primary-element);
	stroke-width: 2;
	stroke-linejoin: round;
	stroke-linecap: round;
}

.weight-graph__dot {
	fill: var(--color-primary-element);
}

.weight-graph__axis-label {
	font-size: 11px;
	fill: var(--color-text-maxcontrast);
}

.weight-graph__axis-label--y {
	text-anchor: end;
	dominant-baseline: middle;
}

.weight-graph__axis-label--x {
	text-anchor: middle;
}

.weight-graph__latest {
	margin: 0;
	font-size: 0.85em;
	color: var(--color-text-maxcontrast);
	text-align: right;
}
</style>
