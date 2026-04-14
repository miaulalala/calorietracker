<!--
  - SPDX-FileCopyrightText: 2026 Anna Larch
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppSettingsDialog :open="open"
		:show-navigation="true"
		:name="t('calorietracker', 'App settings')"
		@update:open="onOpenUpdate">
		<!-- ── Units section ─────────────────────────────────────────── -->
		<NcAppSettingsSection id="units" :name="t('calorietracker', 'Units')">
			<NcFormGroup :label="t('calorietracker', 'Energy unit')">
				<NcFormBox row>
					<NcButton :pressed="form.energyUnit === 'kcal'"
						@click="form.energyUnit = 'kcal'">
						kcal
					</NcButton>
					<NcButton :pressed="form.energyUnit === 'kj'"
						@click="form.energyUnit = 'kj'">
						kJ
					</NcButton>
				</NcFormBox>
			</NcFormGroup>

			<NcFormGroup :label="t('calorietracker', 'Measurement system')">
				<NcFormBox row>
					<NcButton :pressed="form.measurementSystem === 'metric'"
						@click="form.measurementSystem = 'metric'">
						{{ t('calorietracker', 'Metric (g, cm, kg)') }}
					</NcButton>
					<NcButton :pressed="form.measurementSystem === 'imperial'"
						@click="form.measurementSystem = 'imperial'">
						{{ t('calorietracker', 'Imperial (oz, ft/in, lbs)') }}
					</NcButton>
				</NcFormBox>
			</NcFormGroup>

			<NcFormGroup :label="t('calorietracker', 'Day view')">
				<NcCheckboxRadioSwitch v-model="form.showWeightOnDayView"
					type="switch">
					{{ t('calorietracker', 'Show current weight on day view') }}
				</NcCheckboxRadioSwitch>
			</NcFormGroup>
		</NcAppSettingsSection>

		<!-- ── Goals section ─────────────────────────────────────────── -->
		<NcAppSettingsSection id="goals" :name="t('calorietracker', 'Goals')">
			<NcNoteCard v-if="saved" type="success">
				{{ t('calorietracker', 'Settings saved.') }}
			</NcNoteCard>

			<div class="app-settings-subsection">
				<h4 class="app-settings-section__subtitle">
					{{ t('calorietracker', 'Daily calorie goal') }}
				</h4>
				<div class="settings-field-row">
					<NcInputField v-model.number="form.dailyCalorieGoal"
						type="number"
						min="0"
						:label="t('calorietracker', 'Calorie goal ({unit})', { unit: energyLabel })"
						:placeholder="t('calorietracker', 'e.g. 2000')" />
				</div>
			</div>

			<div class="app-settings-subsection">
				<h4 class="app-settings-section__subtitle">
					{{ t('calorietracker', 'Daily macro goals') }}
				</h4>
				<p class="app-settings-section__hint">
					{{ t('calorietracker', 'Set to 0 to disable a goal. Percentages are based on your calorie goal.') }}
				</p>
				<div class="settings-field-row settings-field-row--three">
					<NcInputField v-model.number="form.dailyProteinPct"
						type="number"
						min="0"
						max="100"
						:disabled="!form.dailyCalorieGoal"
						:label="t('calorietracker', 'Protein (%)')"
						:placeholder="t('calorietracker', 'e.g. 30')" />
					<NcInputField v-model.number="form.dailyCarbsPct"
						type="number"
						min="0"
						max="100"
						:disabled="!form.dailyCalorieGoal"
						:label="t('calorietracker', 'Carbs (%)')"
						:placeholder="t('calorietracker', 'e.g. 45')" />
					<NcInputField v-model.number="form.dailyFatPct"
						type="number"
						min="0"
						max="100"
						:disabled="!form.dailyCalorieGoal"
						:label="t('calorietracker', 'Fat (%)')"
						:placeholder="t('calorietracker', 'e.g. 25')" />
				</div>
				<p v-if="macroPreview" class="app-settings-section__hint">
					{{ t('calorietracker', '≈ {protein}{unit} protein · {carbs}{unit} carbs · {fat}{unit} fat', { protein: macroPreview.protein, carbs: macroPreview.carbs, fat: macroPreview.fat, unit: weightLabel }) }}
				</p>
				<p v-else class="app-settings-section__hint">
					{{ t('calorietracker', 'Set a calorie goal above to enable macro targets.') }}
				</p>
			</div>

			<div class="settings-actions">
				<NcButton variant="primary" :disabled="saving" @click="save">
					{{ t('calorietracker', 'Save') }}
				</NcButton>
			</div>
		</NcAppSettingsSection>

		<!-- ── TDEE Calculator section ───────────────────────────────── -->
		<NcAppSettingsSection id="calculate" :name="t('calorietracker', 'TDEE Calculator')">
			<p class="app-settings-section__hint">
				{{ t('calorietracker', 'Estimate your Total Daily Energy Expenditure (TDEE) using the Mifflin-St Jeor equation, then apply it as your calorie goal. Your inputs are encrypted at rest and not stored in plaintext.') }}
			</p>

			<div class="app-settings-subsection">
				<h4 class="app-settings-section__subtitle">
					{{ t('calorietracker', 'Sex assigned at birth') }}
				</h4>
				<p class="app-settings-section__hint">
					{{ t('calorietracker', 'The TDEE formula uses sex assigned at birth to estimate metabolism. "Prefer not to say" uses an average of both, which may be less accurate by up to ~80 kcal/day.') }}
				</p>
				<div class="settings-radio-group">
					<NcCheckboxRadioSwitch v-for="opt in SEX_OPTIONS"
						:key="opt.id"
						v-model="tdee.sex"
						:value="opt.id"
						name="tdee-sex"
						type="radio">
						{{ opt.label }}
					</NcCheckboxRadioSwitch>
				</div>
			</div>

			<div class="app-settings-subsection">
				<h4 class="app-settings-section__subtitle">
					{{ t('calorietracker', 'Body measurements') }}
				</h4>
				<div v-if="isImperial" class="settings-field-row settings-field-row--four">
					<NcInputField v-model.number="tdee.age"
						type="number"
						min="10"
						max="120"
						:label="t('calorietracker', 'Age (years)')" />
					<NcInputField v-model.number="tdee.heightFeet"
						type="number"
						min="3"
						max="8"
						:label="t('calorietracker', 'Height (ft)')" />
					<NcInputField v-model.number="tdee.heightInches"
						type="number"
						min="0"
						max="11"
						:label="t('calorietracker', 'Height (in)')" />
					<NcInputField v-model.number="tdee.weight"
						type="number"
						min="44"
						max="1100"
						:label="t('calorietracker', 'Weight (lbs)')" />
				</div>
				<div v-else class="settings-field-row settings-field-row--three">
					<NcInputField v-model.number="tdee.age"
						type="number"
						min="10"
						max="120"
						:label="t('calorietracker', 'Age (years)')" />
					<NcInputField v-model.number="tdee.height"
						type="number"
						min="50"
						max="300"
						:label="t('calorietracker', 'Height (cm)')" />
					<NcInputField v-model.number="tdee.weight"
						type="number"
						min="20"
						max="500"
						:label="t('calorietracker', 'Weight (kg)')" />
				</div>
			</div>

			<div class="app-settings-subsection">
				<h4 class="app-settings-section__subtitle">
					{{ t('calorietracker', 'Activity level') }}
				</h4>
				<div class="settings-radio-group">
					<NcCheckboxRadioSwitch v-for="opt in ACTIVITY_OPTIONS"
						:key="opt.id"
						v-model="tdee.activity"
						:value="opt.id"
						name="tdee-activity"
						type="radio">
						{{ opt.label }}
					</NcCheckboxRadioSwitch>
				</div>
			</div>

			<div class="app-settings-subsection">
				<h4 class="app-settings-section__subtitle">
					{{ t('calorietracker', 'Weight goal') }}
				</h4>
				<div class="settings-radio-group">
					<NcCheckboxRadioSwitch v-for="opt in goalOptions"
						:key="opt.id"
						v-model="tdee.goal"
						:value="opt.id"
						name="tdee-goal"
						type="radio">
						{{ opt.label }}
					</NcCheckboxRadioSwitch>
				</div>
			</div>

			<div class="settings-tdee-result"
				:class="{ 'settings-tdee-result--visible': tdeeResult !== null }"
				aria-live="polite"
				:aria-hidden="String(tdeeResult === null)">
				<template v-if="tdeeResult !== null">
					<span class="settings-tdee-result__value">{{ displayEnergy(tdeeResult) }} {{ energyLabel }}/day</span>
					<span class="settings-tdee-result__breakdown">
						BMR {{ displayEnergy(tdeeBMR) }} {{ energyLabel }} &times; {{ selectedActivity.factor }} activity{{ selectedGoal.adjustment !== 0 ? (selectedGoal.adjustment > 0 ? ' + ' : ' − ') + displayEnergy(Math.abs(selectedGoal.adjustment)) + ' ' + energyLabel + ' adjustment' : '' }}
					</span>
				</template>
			</div>

			<div class="settings-actions settings-actions--start">
				<NcButton @click="calculateTDEE">
					{{ t('calorietracker', 'Calculate') }}
				</NcButton>
				<NcButton v-if="tdeeResult !== null" variant="primary" @click="applyTDEE">
					{{ t('calorietracker', 'Apply as calorie goal') }}
				</NcButton>
			</div>
		</NcAppSettingsSection>
	</NcAppSettingsDialog>
</template>

<script setup>
import { ref, reactive, computed, watch, nextTick } from 'vue'
import { storeToRefs } from 'pinia'
import { translate as t } from '@nextcloud/l10n'
import NcAppSettingsDialog from '@nextcloud/vue/components/NcAppSettingsDialog'
import NcAppSettingsSection from '@nextcloud/vue/components/NcAppSettingsSection'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcFormBox from '@nextcloud/vue/components/NcFormBox'
import NcFormGroup from '@nextcloud/vue/components/NcFormGroup'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import { useSettingsStore } from '../stores/settings.js'
import { useUnits } from '../composables/useUnits.js'
import TdeeProfileApi from '../services/TdeeProfileApi.js'

const SEX_OPTIONS = [
	{ id: 'amab', label: 'AMAB', offset: 5 },
	{ id: 'afab', label: 'AFAB', offset: -161 },
	{ id: 'unspecified', label: 'Non-binary / Prefer not to say', offset: -78 },
]

const ACTIVITY_OPTIONS = [
	{ id: 'sedentary', label: 'Sedentary (desk job, little or no exercise)', factor: 1.2 },
	{ id: 'light', label: 'Lightly active (exercise 1–3 days/week)', factor: 1.375 },
	{ id: 'moderate', label: 'Moderately active (exercise 3–5 days/week)', factor: 1.55 },
	{ id: 'very', label: 'Very active (exercise 6–7 days/week)', factor: 1.725 },
	{ id: 'extra', label: 'Extra active (physical job or twice-daily training)', factor: 1.9 },
]

const GOAL_DATA = [
	{ id: 'lose', adjustment: -500 },
	{ id: 'maintain', adjustment: 0 },
	{ id: 'gain', adjustment: 500 },
]

// kcal per gram for each macro
const KCAL_PER_G = { protein: 4, carbs: 4, fat: 9 }

const store = useSettingsStore()
const { open, dailyCalorieGoal, dailyProteinGoal, dailyCarbsGoal, dailyFatGoal, energyUnit, measurementSystem, showWeightOnDayView } = storeToRefs(store)
const { displayEnergy, toKcal, displayWeight, weightLabel, energyLabel, isImperial } = useUnits()

const goalOptions = computed(() => GOAL_DATA.map(g => ({
	...g,
	label: g.adjustment === 0
		? 'Maintain weight'
		: (g.adjustment < 0 ? 'Lose weight' : 'Gain weight')
			+ ` (${g.adjustment > 0 ? '+' : '−'}${displayEnergy(Math.abs(g.adjustment))} ${energyLabel.value}/day)`,
})))

const saving = ref(false)
const saved = ref(false)
const didSave = ref(false)
const initializing = ref(false)
const tdeeBMR = ref(null)
const tdeeResult = ref(null)
const savedUnitSnapshot = ref(null)

const form = reactive({
	dailyCalorieGoal: 0,
	dailyProteinPct: 0,
	dailyCarbsPct: 0,
	dailyFatPct: 0,
	energyUnit: 'kcal',
	measurementSystem: 'metric',
	showWeightOnDayView: false,
})

const tdee = reactive({
	sex: 'amab',
	age: '',
	height: '',
	heightFeet: '',
	heightInches: '',
	weight: '',
	activity: 'light',
	goal: 'maintain',
})

const selectedActivity = computed(() => ACTIVITY_OPTIONS.find(o => o.id === tdee.activity))
const selectedGoal = computed(() => GOAL_DATA.find(o => o.id === tdee.goal))

// Live weight preview shown below the percentage inputs
const macroPreview = computed(() => {
	if (!form.dailyCalorieGoal) return null
	// Always compute grams from kcal — convert display value back if needed
	const cal = toKcal(form.dailyCalorieGoal)
	const proteinG = Math.round(form.dailyProteinPct / 100 * cal / KCAL_PER_G.protein)
	const carbsG = Math.round(form.dailyCarbsPct / 100 * cal / KCAL_PER_G.carbs)
	const fatG = Math.round(form.dailyFatPct / 100 * cal / KCAL_PER_G.fat)
	return {
		protein: displayWeight(proteinG),
		carbs: displayWeight(carbsG),
		fat: displayWeight(fatG),
	}
})

/**
 * Convert stored gram values to percentages for display.
 *
 * @param {number} grams Macro nutrient amount in grams
 * @param {number} kcalPerGram Calories per gram for this macro
 * @param {number} calorieGoal Daily calorie goal in kcal
 */
function gramsToPercent(grams, kcalPerGram, calorieGoal) {
	if (!calorieGoal) return 0
	return Math.round(grams * kcalPerGram / calorieGoal * 100)
}

// Sync unit preferences to the store immediately so the whole UI reacts,
// and convert the calorie goal value in-place so the number matches the new unit.
watch(() => form.energyUnit, (newUnit, oldUnit) => {
	store.energyUnit = newUnit
	// Skip conversion during form initialization — the value is already in the correct unit.
	if (initializing.value) return
	if (form.dailyCalorieGoal && oldUnit) {
		const KCAL_TO_KJ = 4.184
		if (oldUnit === 'kcal' && newUnit === 'kj') {
			form.dailyCalorieGoal = Math.round(form.dailyCalorieGoal * KCAL_TO_KJ)
		} else if (oldUnit === 'kj' && newUnit === 'kcal') {
			form.dailyCalorieGoal = Math.round(form.dailyCalorieGoal / KCAL_TO_KJ)
		}
	}
})
watch(() => form.measurementSystem, (newVal) => {
	store.measurementSystem = newVal
})
watch(() => form.showWeightOnDayView, (newVal) => {
	store.showWeightOnDayView = newVal
})

watch(open, async (isOpen) => {
	if (isOpen) {
		didSave.value = false
		await store.fetchSettings()
		savedUnitSnapshot.value = {
			energyUnit: energyUnit.value,
			measurementSystem: measurementSystem.value,
			showWeightOnDayView: showWeightOnDayView.value,
		}
		const cal = dailyCalorieGoal.value
		// Guard against the energyUnit watcher double-converting during init.
		initializing.value = true
		Object.assign(form, {
			energyUnit: energyUnit.value,
			measurementSystem: measurementSystem.value,
			showWeightOnDayView: showWeightOnDayView.value,
			dailyCalorieGoal: displayEnergy(cal),
			dailyProteinPct: gramsToPercent(dailyProteinGoal.value, KCAL_PER_G.protein, cal),
			dailyCarbsPct: gramsToPercent(dailyCarbsGoal.value, KCAL_PER_G.carbs, cal),
			dailyFatPct: gramsToPercent(dailyFatGoal.value, KCAL_PER_G.fat, cal),
		})
		await nextTick()
		initializing.value = false
		try {
			const profile = await TdeeProfileApi.get()
			if (profile && typeof profile === 'object') {
				const sanitized = {}
				if (SEX_OPTIONS.find(o => o.id === profile.sex)) {
					sanitized.sex = profile.sex
				}
				if (ACTIVITY_OPTIONS.find(o => o.id === profile.activity)) {
					sanitized.activity = profile.activity
				}
				if (GOAL_DATA.find(o => o.id === profile.goal)) {
					sanitized.goal = profile.goal
				}
				if (typeof profile.age === 'number') sanitized.age = profile.age
				if (typeof profile.height === 'number') sanitized.height = profile.height
				if (typeof profile.weight === 'number') sanitized.weight = profile.weight
				if (typeof profile.heightFeet === 'number') sanitized.heightFeet = profile.heightFeet
				if (typeof profile.heightInches === 'number') sanitized.heightInches = profile.heightInches
				Object.assign(tdee, sanitized)
			}
		} catch (error) {
			console.error('Failed to load TDEE profile:', error)
		}
	}
})

/**
 * Handle settings dialog open/close state changes.
 * @param {boolean} isOpen Whether the dialog is open
 */
function onOpenUpdate(isOpen) {
	if (!isOpen) {
		if (!didSave.value && savedUnitSnapshot.value) {
			store.energyUnit = savedUnitSnapshot.value.energyUnit
			store.measurementSystem = savedUnitSnapshot.value.measurementSystem
			store.showWeightOnDayView = savedUnitSnapshot.value.showWeightOnDayView
		}
		store.closeSettings()
	}
}

/**
 * Calculate TDEE using the Mifflin-St Jeor equation with the user's profile inputs.
 */
function calculateTDEE() {
	const { age, sex } = tdee
	// Convert to metric for Mifflin-St Jeor (always uses cm/kg)
	let heightCm, weightKg
	if (isImperial.value) {
		const { heightFeet, heightInches, weight } = tdee
		if (!age || (!heightFeet && !heightInches) || !weight) return
		heightCm = ((heightFeet || 0) * 12 + (heightInches || 0)) * 2.54
		weightKg = weight / 2.20462
	} else {
		const { height, weight } = tdee
		if (!age || !height || !weight) return
		heightCm = height
		weightKg = weight
	}
	const sexOpt = SEX_OPTIONS.find(o => o.id === sex)
	const bmr = Math.round(10 * weightKg + 6.25 * heightCm - 5 * age + sexOpt.offset)
	const result = Math.round(bmr * selectedActivity.value.factor) + selectedGoal.value.adjustment
	tdeeBMR.value = bmr
	tdeeResult.value = Math.max(1200, result)
}

/**
 * Apply the calculated TDEE result to the nutrition goal form and persist the TDEE profile.
 */
async function applyTDEE() {
	form.dailyCalorieGoal = displayEnergy(tdeeResult.value)
	form.dailyProteinPct = 30
	form.dailyCarbsPct = 40
	form.dailyFatPct = 30
	try {
		await TdeeProfileApi.save({ ...tdee })
	} catch (error) {
		console.error('Failed to save TDEE profile:', error)
	}
}

/**
 * Persist all settings (goals, unit preferences) to the server.
 */
async function save() {
	saving.value = true
	saved.value = false
	const cal = toKcal(form.dailyCalorieGoal) || 0
	try {
		const payload = {
			dailyCalorieGoal: cal,
			energyUnit: form.energyUnit,
			measurementSystem: form.measurementSystem,
			showWeightOnDayView: form.showWeightOnDayView,
		}
		if (cal) {
			payload.dailyProteinGoal = Math.round(form.dailyProteinPct / 100 * cal / KCAL_PER_G.protein)
			payload.dailyCarbsGoal = Math.round(form.dailyCarbsPct / 100 * cal / KCAL_PER_G.carbs)
			payload.dailyFatGoal = Math.round(form.dailyFatPct / 100 * cal / KCAL_PER_G.fat)
		} else {
			payload.dailyProteinGoal = 0
			payload.dailyCarbsGoal = 0
			payload.dailyFatGoal = 0
		}
		await store.saveSettings(payload)
		didSave.value = true
		saved.value = true
		setTimeout(() => {
			saved.value = false
			store.closeSettings()
		}, 1000)
	} finally {
		saving.value = false
	}
}
</script>

<style scoped>
/* ── Subsection spacing (matches spreed convention) ──────────── */
:deep(.app-settings-section__subtitle) {
	font-weight: bold;
	font-size: var(--default-font-size);
	margin: calc(var(--default-grid-baseline) * 4) 0 var(--default-grid-baseline) 0;
}

:deep(.app-settings-section__hint) {
	color: var(--color-text-maxcontrast);
	padding: 8px 0;
}

:deep(.app-settings-subsection:not(:first-child)) {
	margin-top: calc(var(--default-grid-baseline) * 4);
}

/* ── Radio groups ────────────────────────────────────────────── */
.settings-radio-group {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.settings-radio-group--inline {
	flex-direction: row;
	flex-wrap: wrap;
	gap: 8px;
}

/* ── Field rows (grid layouts) ───────────────────────────────── */
.settings-field-row {
	display: grid;
	gap: 12px;
	grid-template-columns: 1fr;
}

.settings-field-row--three {
	grid-template-columns: 1fr 1fr 1fr;
}

.settings-field-row--four {
	grid-template-columns: 1fr 1fr 1fr 1fr;
}

/* ── TDEE result card ────────────────────────────────────────── */
.settings-tdee-result {
	min-height: 56px;
	margin: calc(var(--default-grid-baseline) * 4) 0;
	padding: 12px 16px;
	border-radius: var(--border-radius-large);
	background: var(--color-primary-element-light);
	visibility: hidden;
}

.settings-tdee-result--visible {
	visibility: visible;
}

.settings-tdee-result__value {
	display: block;
	font-size: 1.4em;
	font-weight: 700;
	color: var(--color-main-text);
}

.settings-tdee-result__breakdown {
	display: block;
	margin-top: 2px;
	font-size: 0.8em;
	color: var(--color-text-maxcontrast);
}

/* ── Action buttons ──────────────────────────────────────────── */
.settings-actions {
	display: flex;
	justify-content: flex-end;
	margin-top: calc(var(--default-grid-baseline) * 4);
}

.settings-actions--start {
	justify-content: flex-start;
	gap: 8px;
}
</style>
