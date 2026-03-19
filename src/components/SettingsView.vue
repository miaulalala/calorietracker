<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="settings-view">
		<NcNoteCard v-if="saved" type="success" class="settings-view__feedback">
			{{ t('calorietracker', 'Settings saved.') }}
		</NcNoteCard>

		<section class="settings-view__section">
			<h3 class="settings-view__section-title">
				{{ t('calorietracker', 'Calories') }}
			</h3>
			<div class="settings-view__row">
				<NcInputField
					v-model.number="form.dailyCalorieGoal"
					type="number"
					min="0"
					:label="t('calorietracker', 'Daily calorie goal (kcal)')"
					:placeholder="t('calorietracker', 'e.g. 2000')" />
			</div>
		</section>

		<section class="settings-view__section">
			<h3 class="settings-view__section-title">
				{{ t('calorietracker', 'Macros') }}
			</h3>
			<p class="settings-view__hint">
				{{ t('calorietracker', 'Set to 0 to disable a goal.') }}
			</p>
			<div class="settings-view__row settings-view__row--three">
				<NcInputField
					v-model.number="form.dailyProteinGoal"
					type="number"
					min="0"
					:label="t('calorietracker', 'Protein goal (g)')"
					:placeholder="t('calorietracker', 'e.g. 150')" />

				<NcInputField
					v-model.number="form.dailyCarbsGoal"
					type="number"
					min="0"
					:label="t('calorietracker', 'Carbs goal (g)')"
					:placeholder="t('calorietracker', 'e.g. 250')" />

				<NcInputField
					v-model.number="form.dailyFatGoal"
					type="number"
					min="0"
					:label="t('calorietracker', 'Fat goal (g)')"
					:placeholder="t('calorietracker', 'e.g. 70')" />
			</div>
		</section>

		<section class="settings-view__section settings-view__section--tdee">
			<h3 class="settings-view__section-title">
				{{ t('calorietracker', 'Calculate calorie goal') }}
			</h3>
			<p class="settings-view__hint">
				{{ t('calorietracker', 'Estimate your Total Daily Energy Expenditure (TDEE) using the Mifflin St-Jeor equation.') }}
			</p>

			<div class="settings-view__row settings-view__row--two">
				<div>
					<label class="settings-view__select-label">{{ t('calorietracker', 'Biological sex') }}</label>
					<NcSelect
						v-model="tdee.sexOption"
						:options="sexOptions"
						:clearable="false"
						label="label" />
				</div>
				<NcInputField
					v-model.number="tdee.age"
					type="number"
					min="10"
					max="120"
					:label="t('calorietracker', 'Age (years)')"
					:placeholder="t('calorietracker', 'e.g. 30')" />
			</div>

			<div class="settings-view__row settings-view__row--two">
				<NcInputField
					v-model.number="tdee.height"
					type="number"
					min="50"
					max="300"
					:label="t('calorietracker', 'Height (cm)')"
					:placeholder="t('calorietracker', 'e.g. 170')" />
				<NcInputField
					v-model.number="tdee.weight"
					type="number"
					min="20"
					max="500"
					:label="t('calorietracker', 'Weight (kg)')"
					:placeholder="t('calorietracker', 'e.g. 70')" />
			</div>

			<div class="settings-view__row">
				<div>
					<label class="settings-view__select-label">{{ t('calorietracker', 'Activity level') }}</label>
					<NcSelect
						v-model="tdee.activityOption"
						:options="activityOptions"
						:clearable="false"
						label="label" />
				</div>
			</div>

			<div class="settings-view__row">
				<div>
					<label class="settings-view__select-label">{{ t('calorietracker', 'Goal') }}</label>
					<NcSelect
						v-model="tdee.goalOption"
						:options="goalOptions"
						:clearable="false"
						label="label" />
				</div>
			</div>

			<div class="settings-view__tdee-result" :class="{ 'settings-view__tdee-result--visible': tdeeResult !== null }">
				<template v-if="tdeeResult !== null">
					<span class="settings-view__tdee-value">{{ tdeeResult }} kcal/day</span>
					<span class="settings-view__tdee-breakdown">
						BMR {{ tdeeBMR }} × {{ tdee.activityOption.factor }} activity{{ tdee.goalOption.adjustment !== 0 ? (tdee.goalOption.adjustment > 0 ? ' + ' : ' − ') + Math.abs(tdee.goalOption.adjustment) + ' kcal adjustment' : '' }}
					</span>
				</template>
			</div>

			<div class="settings-view__tdee-actions">
				<NcButton @click="calculateTDEE">
					{{ t('calorietracker', 'Calculate') }}
				</NcButton>
				<NcButton
					v-if="tdeeResult !== null"
					type="primary"
					@click="applyTDEE">
					{{ t('calorietracker', 'Apply as calorie goal') }}
				</NcButton>
			</div>
		</section>

		<div class="settings-view__actions">
			<NcButton type="primary" :disabled="saving" @click="save">
				{{ t('calorietracker', 'Save') }}
			</NcButton>
		</div>
	</div>
</template>

<script>
import { mapState } from 'vuex'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcInputField from '@nextcloud/vue/dist/Components/NcInputField.js'
import NcNoteCard from '@nextcloud/vue/dist/Components/NcNoteCard.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'

const SEX_OPTIONS = [
	{ id: 'male', label: 'Male', offset: 5 },
	{ id: 'female', label: 'Female', offset: -161 },
]

const ACTIVITY_OPTIONS = [
	{ id: 'sedentary', label: 'Sedentary (desk job, little or no exercise)', factor: 1.2 },
	{ id: 'light', label: 'Lightly active (exercise 1–3 days/week)', factor: 1.375 },
	{ id: 'moderate', label: 'Moderately active (exercise 3–5 days/week)', factor: 1.55 },
	{ id: 'very', label: 'Very active (exercise 6–7 days/week)', factor: 1.725 },
	{ id: 'extra', label: 'Extra active (physical job or twice-daily training)', factor: 1.9 },
]

const GOAL_OPTIONS = [
	{ id: 'lose', label: 'Lose weight (−500 kcal/day)', adjustment: -500 },
	{ id: 'maintain', label: 'Maintain weight', adjustment: 0 },
	{ id: 'gain', label: 'Gain weight (+500 kcal/day)', adjustment: 500 },
]

export default {
	name: 'SettingsView',

	components: { NcButton, NcInputField, NcNoteCard, NcSelect },

	data() {
		return {
			saving: false,
			saved: false,
			form: {
				dailyCalorieGoal: 0,
				dailyProteinGoal: 0,
				dailyCarbsGoal: 0,
				dailyFatGoal: 0,
			},
			tdee: {
				sexOption: SEX_OPTIONS[0],
				age: null,
				height: null,
				weight: null,
				activityOption: ACTIVITY_OPTIONS[1],
				goalOption: GOAL_OPTIONS[1],
			},
			tdeeBMR: null,
			tdeeResult: null,
		}
	},

	computed: {
		...mapState('settings', ['dailyCalorieGoal', 'dailyProteinGoal', 'dailyCarbsGoal', 'dailyFatGoal']),

		sexOptions: () => SEX_OPTIONS,
		activityOptions: () => ACTIVITY_OPTIONS,
		goalOptions: () => GOAL_OPTIONS,
	},

	watch: {
		dailyCalorieGoal(v) { this.form.dailyCalorieGoal = v },
		dailyProteinGoal(v) { this.form.dailyProteinGoal = v },
		dailyCarbsGoal(v) { this.form.dailyCarbsGoal = v },
		dailyFatGoal(v) { this.form.dailyFatGoal = v },
	},

	created() {
		this.$store.dispatch('settings/fetchSettings').then(() => {
			this.form = {
				dailyCalorieGoal: this.dailyCalorieGoal,
				dailyProteinGoal: this.dailyProteinGoal,
				dailyCarbsGoal: this.dailyCarbsGoal,
				dailyFatGoal: this.dailyFatGoal,
			}
		})
	},

	methods: {
		calculateTDEE() {
			const { age, height, weight, sexOption, activityOption, goalOption } = this.tdee
			if (!age || !height || !weight) return

			// Mifflin St-Jeor: BMR = 10*kg + 6.25*cm - 5*age + offset
			const bmr = Math.round(10 * weight + 6.25 * height - 5 * age + sexOption.offset)
			const tdee = Math.round(bmr * activityOption.factor) + goalOption.adjustment

			this.tdeeBMR = bmr
			this.tdeeResult = Math.max(1200, tdee)
		},

		applyTDEE() {
			this.form.dailyCalorieGoal = this.tdeeResult
		},

		async save() {
			this.saving = true
			this.saved = false
			try {
				await this.$store.dispatch('settings/saveSettings', this.form)
				this.saved = true
				setTimeout(() => {
					this.saved = false
					this.$store.dispatch('settings/closeSettings')
				}, 1000)
			} finally {
				this.saving = false
			}
		},
	},
}
</script>

<style scoped>
.settings-view {
	max-width: 560px;
	margin: 0 auto;
	padding: 40px 24px 24px;
}

.settings-view__section {
	margin-bottom: 28px;
}

.settings-view__section--tdee {
	border-top: 1px solid var(--color-border);
	padding-top: 24px;
}

.settings-view__section-title {
	margin: 0 0 12px;
	font-size: 1em;
	font-weight: 600;
}

.settings-view__hint {
	margin: -6px 0 12px;
	font-size: 0.85em;
	color: var(--color-text-maxcontrast);
}

.settings-view__select-label {
	display: block;
	margin-bottom: 4px;
	font-size: 0.875em;
	font-weight: 500;
	color: var(--color-main-text);
}

.settings-view__row {
	display: grid;
	gap: 16px;
	grid-template-columns: 1fr;
	margin-bottom: 16px;
}

.settings-view__row--two {
	grid-template-columns: 1fr 1fr;
}

.settings-view__row--three {
	grid-template-columns: 1fr 1fr 1fr;
}

.settings-view__tdee-result {
	min-height: 44px;
	margin: 8px 0 12px;
	padding: 12px 16px;
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
	visibility: hidden;
}

.settings-view__tdee-result--visible {
	visibility: visible;
}

.settings-view__tdee-value {
	display: block;
	font-size: 1.4em;
	font-weight: 700;
	color: var(--color-main-text);
}

.settings-view__tdee-breakdown {
	display: block;
	margin-top: 2px;
	font-size: 0.8em;
	color: var(--color-text-maxcontrast);
}

.settings-view__tdee-actions {
	display: flex;
	gap: 8px;
	margin-bottom: 8px;
}

.settings-view__feedback {
	margin-bottom: 20px;
}

.settings-view__actions {
	display: flex;
	justify-content: flex-end;
}
</style>
