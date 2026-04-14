<!--
  - SPDX-FileCopyrightText: 2026 Anna Larch
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcModal size="small"
		:name="t('calorietracker', 'Log weight')"
		@close="$emit('close')">
		<form class="weight-log-form" @submit.prevent="submit">
			<h2 class="weight-log-form__title">
				{{ t('calorietracker', 'Log weight') }}
			</h2>

			<div class="weight-log-form__fields">
				<NcInputField ref="weightField"
					v-model.number="form.weight"
					type="number"
					:label="t('calorietracker', 'Weight ({unit})', { unit: bodyWeightLabel })"
					:min="isImperial ? 44 : 20"
					:max="isImperial ? 1100 : 500"
					step="0.1"
					required />

				<div class="weight-log-form__field-wrap">
					<label for="weight-log-date" class="weight-log-form__label">{{ t('calorietracker', 'Date') }}</label>
					<NcDateTimePickerNative id="weight-log-date"
						v-model="form.date"
						type="date"
						hide-label
						required />
				</div>

				<NcInputField v-model="form.note"
					type="text"
					:label="t('calorietracker', 'Note (optional)')"
					:placeholder="t('calorietracker', 'e.g. Morning, post-workout…')"
					maxlength="255" />
			</div>

			<div v-if="error" class="weight-log-form__error">
				{{ error }}
			</div>

			<div class="weight-log-form__actions">
				<NcButton variant="primary" native-type="submit" :disabled="saving">
					{{ t('calorietracker', 'Save') }}
				</NcButton>
				<NcButton variant="secondary" native-type="button" @click="$emit('close')">
					{{ t('calorietracker', 'Cancel') }}
				</NcButton>
			</div>
		</form>
	</NcModal>
</template>

<script setup>
import { ref, reactive, onMounted, nextTick } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcDateTimePickerNative from '@nextcloud/vue/components/NcDateTimePickerNative'
import { useUnits } from '../composables/useUnits.js'
import { useWeightLogStore } from '../stores/weightLog.js'
import { toLocalDateString } from '../utils/date.js'

defineEmits(['close'])

const store = useWeightLogStore()
const { displayBodyWeight, toKg, bodyWeightLabel, isImperial } = useUnits()

const weightField = ref(null)
const saving = ref(false)
const error = ref('')

const form = reactive({
	weight: '',
	date: toLocalDateString(),
	note: '',
})

onMounted(() => {
	// Pre-fill with latest weight if available
	if (store.latestWeight) {
		form.weight = displayBodyWeight(store.latestWeight.weightKg)
	}
	nextTick(() => {
		weightField.value?.$el?.querySelector('input')?.focus()
	})
})

/**
 *
 */
async function submit() {
	error.value = ''
	saving.value = true
	try {
		const weightKg = toKg(Number(form.weight))
		await store.logWeight({
			weightKg,
			loggedAt: form.date,
			note: form.note || null,
		})
	} catch (e) {
		const msg = e.response?.data?.error
		error.value = msg || t('calorietracker', 'Failed to save weight.')
	} finally {
		saving.value = false
	}
}
</script>

<style scoped>
.weight-log-form {
	display: flex;
	flex-direction: column;
	gap: 16px;
	padding: 20px;
}

.weight-log-form__title {
	margin: 0;
	font-size: 1.2em;
}

.weight-log-form__fields {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.weight-log-form__field-wrap {
	display: flex;
	flex-direction: column;
	gap: 2px;
}

.weight-log-form__label {
	font-size: 0.9em;
	font-weight: 600;
	color: var(--color-main-text);
}

.weight-log-form__error {
	color: var(--color-error);
	font-size: 0.9em;
}

.weight-log-form__actions {
	display: flex;
	gap: 8px;
}
</style>
