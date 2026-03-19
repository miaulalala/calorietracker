<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppSettingsDialog
		:open="open"
		:show-navigation="true"
		:name="t('calorietracker', 'Daily goals')"
		@update:open="onOpenUpdate">

		<!-- ── Goals section ─────────────────────────────────────────── -->
		<NcAppSettingsSection id="goals" :name="t('calorietracker', 'Goals')">
			<NcNoteCard v-if="saved" type="success" class="settings-section__feedback">
				{{ t('calorietracker', 'Settings saved.') }}
			</NcNoteCard>

			<h3 class="settings-section__subtitle">
				{{ t('calorietracker', 'Calories') }}
			</h3>
			<div class="settings-section__row">
				<NcInputField
					v-model.number="form.dailyCalorieGoal"
					type="number"
					min="0"
					:label="t('calorietracker', 'Daily calorie goal (kcal)')"
					:placeholder="t('calorietracker', 'e.g. 2000')" />
			</div>

			<h3 class="settings-section__subtitle">
				{{ t('calorietracker', 'Macros') }}
			</h3>
			<p class="settings-section__hint">
				{{ t('calorietracker', 'Set to 0 to disable a goal.') }}
			</p>
			<div class="settings-section__row settings-section__row--three">
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

			<div class="settings-section__actions">
				<NcButton type="primary" :disabled="saving" @click="save">
					{{ t('calorietracker', 'Save') }}
				</NcButton>
			</div>
		</NcAppSettingsSection>

		<!-- ── Calculate section ─────────────────────────────────────── -->
		<NcAppSettingsSection id="calculate" :name="t('calorietracker', 'Calculate calorie goal')">
			<p class="settings-section__hint settings-section__hint--top">
				{{ t('calorietracker', 'Estimate your Total Daily Energy Expenditure (TDEE) using the Mifflin St-Jeor equation, then apply it as your calorie goal.') }}
			</p>

			<h3 class="settings-section__subtitle">
				{{ t('calorietracker', 'Biological sex') }}
			</h3>
			<div class="settings-section__radio-group settings-section__radio-group--inline">
				<NcCheckboxRadioSwitch
					v-for="opt in sexOptions"
					:key="opt.id"
					v-model="tdee.sex"
					:value="opt.id"
					name="tdee-sex"
					type="radio">
					{{ opt.label }}
				</NcCheckboxRadioSwitch>
			</div>

			<h3 class="settings-section__subtitle">
				{{ t('calorietracker', 'Body measurements') }}
			</h3>
			<div class="settings-section__row settings-section__row--three">
				<NcInputField
					v-model.number="tdee.age"
					type="number"
					min="10"
					max="120"
					:label="t('calorietracker', 'Age (years)')"
					:placeholder="t('calorietracker', 'e.g. 30')" />
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

			<h3 class="settings-section__subtitle">
				{{ t('calorietracker', 'Activity level') }}
			</h3>
			<div class="settings-section__radio-group">
				<NcCheckboxRadioSwitch
					v-for="opt in activityOptions"
					:key="opt.id"
					v-model="tdee.activity"
					:value="opt.id"
					name="tdee-activity"
					type="radio">
					{{ opt.label }}
				</NcCheckboxRadioSwitch>
			</div>

			<h3 class="settings-section__subtitle">
				{{ t('calorietracker', 'Goal') }}
			</h3>
			<div class="settings-section__radio-group">
				<NcCheckboxRadioSwitch
					v-for="opt in goalOptions"
					:key="opt.id"
					v-model="tdee.goal"
					:value="opt.id"
					name="tdee-goal"
					type="radio">
					{{ opt.label }}
				</NcCheckboxRadioSwitch>
			</div>

			<div class="settings-section__tdee-result" :class="{ 'settings-section__tdee-result--visible': tdeeResult !== null }">
				<template v-if="tdeeResult !== null">
					<span class="settings-section__tdee-value">{{ tdeeResult }} kcal/day</span>
					<span class="settings-section__tdee-breakdown">
						BMR {{ tdeeBMR }} × {{ selectedActivity.factor }} activity{{ selectedGoal.adjustment !== 0 ? (selectedGoal.adjustment > 0 ? ' + ' : ' − ') + Math.abs(selectedGoal.adjustment) + ' kcal adjustment' : '' }}
					</span>
				</template>
			</div>

			<div class="settings-section__actions settings-section__actions--gap">
				<NcButton @click="calculateTDEE">
					{{ t('calorietracker', 'Calculate') }}
				</NcButton>
				<NcButton v-if="tdeeResult !== null" type="primary" @click="applyTDEE">
					{{ t('calorietracker', 'Apply as calorie goal') }}
				</NcButton>
			</div>
		</NcAppSettingsSection>

	</NcAppSettingsDialog>
</template>

<script>
import { mapState } from 'vuex'
import NcAppSettingsDialog from '@nextcloud/vue/dist/Components/NcAppSettingsDialog.js'
import NcAppSettingsSection from '@nextcloud/vue/dist/Components/NcAppSettingsSection.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcInputField from '@nextcloud/vue/dist/Components/NcInputField.js'
import NcNoteCard from '@nextcloud/vue/dist/Components/NcNoteCard.js'

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

	components: { NcAppSettingsDialog, NcAppSettingsSection, NcButton, NcCheckboxRadioSwitch, NcInputField, NcNoteCard },

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
				sex: 'male',
				age: null,
				height: null,
				weight: null,
				activity: 'light',
				goal: 'maintain',
			},
			tdeeBMR: null,
			tdeeResult: null,
		}
	},

	computed: {
		...mapState('settings', ['dailyCalorieGoal', 'dailyProteinGoal', 'dailyCarbsGoal', 'dailyFatGoal']),

		open() {
			return this.$store.state.settings.open
		},

		sexOptions: () => SEX_OPTIONS,
		activityOptions: () => ACTIVITY_OPTIONS,
		goalOptions: () => GOAL_OPTIONS,

		selectedActivity() {
			return ACTIVITY_OPTIONS.find(o => o.id === this.tdee.activity)
		},

		selectedGoal() {
			return GOAL_OPTIONS.find(o => o.id === this.tdee.goal)
		},
	},

	watch: {
		dailyCalorieGoal(v) { this.form.dailyCalorieGoal = v },
		dailyProteinGoal(v) { this.form.dailyProteinGoal = v },
		dailyCarbsGoal(v) { this.form.dailyCarbsGoal = v },
		dailyFatGoal(v) { this.form.dailyFatGoal = v },

		open(isOpen) {
			if (isOpen) {
				this.$store.dispatch('settings/fetchSettings').then(() => {
					this.form = {
						dailyCalorieGoal: this.dailyCalorieGoal,
						dailyProteinGoal: this.dailyProteinGoal,
						dailyCarbsGoal: this.dailyCarbsGoal,
						dailyFatGoal: this.dailyFatGoal,
					}
				})
			}
		},
	},

	methods: {
		onOpenUpdate(open) {
			if (!open) {
				this.$store.dispatch('settings/closeSettings')
			}
		},

		calculateTDEE() {
			const { age, height, weight, sex } = this.tdee
			if (!age || !height || !weight) return
			const sexOpt = SEX_OPTIONS.find(o => o.id === sex)
			const bmr = Math.round(10 * weight + 6.25 * height - 5 * age + sexOpt.offset)
			const tdee = Math.round(bmr * this.selectedActivity.factor) + this.selectedGoal.adjustment
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
.settings-section__subtitle {
	margin: 20px 0 10px;
	font-size: 1em;
	font-weight: 600;
}

.settings-section__hint {
	margin: -4px 0 12px;
	font-size: 0.85em;
	color: var(--color-text-maxcontrast);
}

.settings-section__hint--top {
	margin: 0 0 16px;
}

.settings-section__radio-group {
	display: flex;
	flex-direction: column;
	gap: 4px;
	margin-bottom: 16px;
}

.settings-section__radio-group--inline {
	flex-direction: row;
	flex-wrap: wrap;
	gap: 8px;
}

.settings-section__row {
	display: grid;
	gap: 16px;
	grid-template-columns: 1fr;
	margin-bottom: 16px;
}

.settings-section__row--two {
	grid-template-columns: 1fr 1fr;
}

.settings-section__row--three {
	grid-template-columns: 1fr 1fr 1fr;
}

.settings-section__tdee-result {
	min-height: 56px;
	margin: 0 0 16px;
	padding: 12px 16px;
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
	visibility: hidden;
}

.settings-section__tdee-result--visible {
	visibility: visible;
}

.settings-section__tdee-value {
	display: block;
	font-size: 1.4em;
	font-weight: 700;
	color: var(--color-main-text);
}

.settings-section__tdee-breakdown {
	display: block;
	margin-top: 2px;
	font-size: 0.8em;
	color: var(--color-text-maxcontrast);
}

.settings-section__feedback {
	margin-bottom: 16px;
}

.settings-section__actions {
	display: flex;
	justify-content: flex-end;
	margin-top: 8px;
}

.settings-section__actions--gap {
	justify-content: flex-start;
	gap: 8px;
}
</style>
