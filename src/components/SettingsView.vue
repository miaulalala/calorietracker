<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="settings-view">
		<h2 class="settings-view__title">
			{{ t('calorietracker', 'Daily goals') }}
		</h2>

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

export default {
	name: 'SettingsView',

	components: { NcButton, NcInputField, NcNoteCard },

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
		}
	},

	computed: {
		...mapState('settings', ['dailyCalorieGoal', 'dailyProteinGoal', 'dailyCarbsGoal', 'dailyFatGoal']),
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
		async save() {
			this.saving = true
			this.saved = false
			try {
				await this.$store.dispatch('settings/saveSettings', this.form)
				this.saved = true
				setTimeout(() => { this.saved = false }, 3000)
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

.settings-view__title {
	margin: 0 0 28px;
	font-size: 1.4em;
	font-weight: bold;
}

.settings-view__section {
	margin-bottom: 28px;
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

.settings-view__row {
	display: grid;
	gap: 16px;
	grid-template-columns: 1fr;
}

.settings-view__row--three {
	grid-template-columns: 1fr 1fr 1fr;
}

.settings-view__feedback {
	margin-bottom: 20px;
}

.settings-view__actions {
	display: flex;
	justify-content: flex-end;
}
</style>
