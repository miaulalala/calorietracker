<!--
  - SPDX-FileCopyrightText: 2026 Anna Larch
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<form class="food-entry-form" @submit.prevent="submit">
		<h2 class="food-entry-form__title">
			{{ editingEntry ? t('calorietracker', 'Edit entry') : t('calorietracker', 'Add food') }}
		</h2>

		<!-- Search — hidden in edit mode -->
		<div v-if="!editingEntry && !showManual" class="food-entry-form__search">
			<div class="food-entry-form__search-input-row">
				<input v-model="searchQuery"
					class="food-entry-form__search-input"
					type="search"
					role="combobox"
					:aria-label="t('calorietracker', 'Search food database')"
					:aria-expanded="searchResults.length > 0"
					aria-autocomplete="list"
					aria-controls="food-search-results"
					autocomplete="off"
					@input="onSearchInput"
					@keydown.down.prevent="highlightNext"
					@keydown.up.prevent="highlightPrev"
					@keydown.enter.prevent="selectHighlighted"
					@keydown.esc="closeSearch"
					@blur="onSearchBlur">
				<span v-if="searchLoading" class="food-entry-form__search-spinner" aria-hidden="true" />
			</div>
			<ul v-if="searchResults.length > 0"
				id="food-search-results"
				class="food-entry-form__search-results"
				role="listbox"
				:aria-label="t('calorietracker', 'Search results')">
				<li v-for="(result, i) in searchResults"
					:key="i"
					class="food-entry-form__search-result"
					:class="{ 'food-entry-form__search-result--active': i === highlightedIndex }"
					role="option"
					:aria-selected="i === highlightedIndex"
					@mousedown.prevent="selectResult(result)">
					<span class="food-entry-form__search-result-name">{{ result.name }}</span>
					<span class="food-entry-form__search-result-kcal">{{ result.caloriesPer100g }} kcal/100g</span>
				</li>
			</ul>
			<div v-else-if="searchError" class="food-entry-form__search-feedback">
				<p class="food-entry-form__search-empty food-entry-form__search-empty--error">
					{{ t('calorietracker', 'Could not reach food database.') }}
				</p>
			</div>
			<div v-else-if="searchDone && searchQuery.length >= 2" class="food-entry-form__search-feedback">
				<p class="food-entry-form__search-empty">
					{{ t('calorietracker', 'No results found.') }}
				</p>
			</div>
			<div class="food-entry-form__search-manual">
				<NcButton native-type="button" type="tertiary" @click="showManual = true">
					{{ t('calorietracker', 'Add food manually') }}
				</NcButton>
			</div>
		</div>

		<!-- Manual fields — shown after selecting a result, clicking "Add manually", or in edit mode -->
		<template v-if="showManual || editingEntry">
			<!-- Food name: full width -->
			<div class="food-entry-form__fields food-entry-form__fields--single">
				<NcInputField v-model="form.foodName"
					type="text"
					:label="t('calorietracker', 'Food name')"
					:placeholder="t('calorietracker', 'e.g. Oatmeal')"
					required />
			</div>

			<!-- kcal + amount side by side -->
			<div class="food-entry-form__fields food-entry-form__fields--two">
				<NcInputField v-model.number="form.caloriesPer100g"
					type="number"
					:label="t('calorietracker', 'kcal per 100g')"
					min="1"
					required />

				<NcInputField ref="amountField"
					v-model.number="form.amountGrams"
					type="number"
					:label="t('calorietracker', 'Amount (g)')"
					min="1"
					required />
			</div>

			<!-- Meal + date side by side -->
			<div class="food-entry-form__fields food-entry-form__fields--two">
				<div class="food-entry-form__field-wrap">
					<label class="food-entry-form__select-label">{{ t('calorietracker', 'Meal') }}</label>
					<NcSelect v-model="mealTypeOption"
						:options="mealTypeOptions"
						:clearable="false"
						label="label" />
				</div>

				<NcDateTimePickerNative v-model="eatenAtDate"
					type="date"
					:label="t('calorietracker', 'Date')"
					required />
			</div>

			<!-- Calorie preview -->
			<div v-if="form.caloriesPer100g > 0 && form.amountGrams > 0" class="food-entry-form__preview">
				≈ {{ calculatedCalories }} kcal
			</div>

			<!-- Macros: 3 columns -->
			<p class="food-entry-form__section-label">
				{{ t('calorietracker', 'Macros per 100g (optional)') }}
			</p>
			<div class="food-entry-form__fields food-entry-form__fields--three">
				<NcInputField v-model.number="form.proteinPer100g"
					type="number"
					:label="t('calorietracker', 'Protein (g)')"
					min="0" />

				<NcInputField v-model.number="form.carbsPer100g"
					type="number"
					:label="t('calorietracker', 'Carbs (g)')"
					min="0" />

				<NcInputField v-model.number="form.fatPer100g"
					type="number"
					:label="t('calorietracker', 'Fat (g)')"
					min="0" />
			</div>
		</template>

		<div class="food-entry-form__actions">
			<NcButton native-type="button" type="tertiary" @click="store.closeAddModal()">
				{{ t('calorietracker', 'Cancel') }}
			</NcButton>
			<NcButton v-if="showManual || editingEntry"
				native-type="submit"
				type="primary"
				:disabled="loading">
				{{ editingEntry ? t('calorietracker', 'Save') : t('calorietracker', 'Add') }}
			</NcButton>
		</div>
	</form>
</template>

<script setup>
import { ref, reactive, computed, watch, nextTick } from 'vue'
import { storeToRefs } from 'pinia'
import { translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcDateTimePickerNative from '@nextcloud/vue/components/NcDateTimePickerNative'
import { useFoodEntriesStore } from '../stores/foodEntries.js'
import { toLocalDateString } from '../utils/date.js'
import offApi from '../services/OpenFoodFactsApi.js'

const store = useFoodEntriesStore()
const { currentDate, editingEntry } = storeToRefs(store)

// Template ref
const amountField = ref(null)

// Form state
const loading = ref(false)
const showManual = ref(false)
const selectedSource = ref(null)
const selectedExternalId = ref(null)

// Search state
const searchQuery = ref('')
const searchResults = ref([])
const searchLoading = ref(false)
const searchDone = ref(false)
const highlightedIndex = ref(-1)
const searchDebounce = ref(null)
const searchError = ref(false)

/**
 *
 */
function defaultForm() {
	return {
		foodName: '',
		caloriesPer100g: '',
		amountGrams: '',
		mealType: 'breakfast',
		eatenAt: currentDate.value ?? toLocalDateString(),
		proteinPer100g: '',
		carbsPer100g: '',
		fatPer100g: '',
	}
}

/**
 *
 * @param entry
 */
function entryToForm(entry) {
	return {
		foodName: entry.foodName,
		caloriesPer100g: entry.caloriesPer100g ?? '',
		amountGrams: entry.amountGrams ?? '',
		mealType: entry.mealType,
		eatenAt: entry.eatenAt,
		proteinPer100g: entry.proteinPer100g ?? '',
		carbsPer100g: entry.carbsPer100g ?? '',
		fatPer100g: entry.fatPer100g ?? '',
	}
}

const form = reactive(defaultForm())

watch(editingEntry, (entry) => {
	Object.assign(form, entry ? entryToForm(entry) : defaultForm())
	showManual.value = false
}, { immediate: true })

const calculatedCalories = computed(() => Math.round(form.caloriesPer100g * form.amountGrams / 100))

const mealTypeOptions = computed(() => [
	{ value: 'breakfast', label: t('calorietracker', 'Breakfast') },
	{ value: 'lunch', label: t('calorietracker', 'Lunch') },
	{ value: 'dinner', label: t('calorietracker', 'Dinner') },
	{ value: 'snack', label: t('calorietracker', 'Snack') },
])

const mealTypeOption = computed({
	get() {
		return mealTypeOptions.value.find(o => o.value === form.mealType) ?? mealTypeOptions.value[0]
	},
	set(option) {
		form.mealType = option.value
	},
})

const eatenAtDate = computed({
	get() {
		const [y, m, d] = form.eatenAt.split('-').map(Number)
		return new Date(y, m - 1, d)
	},
	set(value) {
		form.eatenAt = toLocalDateString(value instanceof Date ? value : new Date(value))
	},
})

/**
 *
 */
function onSearchInput() {
	highlightedIndex.value = -1
	searchError.value = false
	clearTimeout(searchDebounce.value)
	if (searchQuery.value.length < 2) {
		searchResults.value = []
		searchDone.value = false
		return
	}
	searchDebounce.value = setTimeout(() => runSearch(), 600)
}

/**
 *
 */
async function runSearch() {
	searchLoading.value = true
	searchDone.value = false
	searchError.value = false
	try {
		searchResults.value = await offApi.search(searchQuery.value)
		searchDone.value = true
	} catch (e) {
		searchResults.value = []
		searchError.value = true
	} finally {
		searchLoading.value = false
	}
}

/**
 *
 * @param result
 */
function selectResult(result) {
	form.foodName = result.name
	form.caloriesPer100g = result.caloriesPer100g
	form.proteinPer100g = result.proteinPer100g ?? ''
	form.carbsPer100g = result.carbsPer100g ?? ''
	form.fatPer100g = result.fatPer100g ?? ''
	selectedSource.value = result.source ?? null
	selectedExternalId.value = result.externalId ?? null
	showManual.value = true
	closeSearch()
	nextTick(() => {
		amountField.value?.$el?.querySelector('input')?.focus()
	})
}

/**
 *
 */
function closeSearch() {
	searchQuery.value = ''
	searchResults.value = []
	searchDone.value = false
	searchError.value = false
	highlightedIndex.value = -1
}

/**
 *
 */
function onSearchBlur() {
	setTimeout(() => closeSearch(), 200)
}

/**
 *
 */
function highlightNext() {
	if (searchResults.value.length === 0) return
	highlightedIndex.value = (highlightedIndex.value + 1) % searchResults.value.length
}

/**
 *
 */
function highlightPrev() {
	if (searchResults.value.length === 0) return
	highlightedIndex.value = (highlightedIndex.value - 1 + searchResults.value.length) % searchResults.value.length
}

/**
 *
 */
function selectHighlighted() {
	if (highlightedIndex.value >= 0 && searchResults.value[highlightedIndex.value]) {
		selectResult(searchResults.value[highlightedIndex.value])
	}
}

/**
 *
 */
function toPayload() {
	const nullIfEmpty = (v) => v === '' ? null : v
	return {
		...form,
		caloriesPer100g: Number(form.caloriesPer100g),
		amountGrams: Number(form.amountGrams),
		proteinPer100g: nullIfEmpty(form.proteinPer100g) !== null ? Number(form.proteinPer100g) : null,
		carbsPer100g: nullIfEmpty(form.carbsPer100g) !== null ? Number(form.carbsPer100g) : null,
		fatPer100g: nullIfEmpty(form.fatPer100g) !== null ? Number(form.fatPer100g) : null,
	}
}

/**
 *
 */
async function submit() {
	loading.value = true
	try {
		if (editingEntry.value) {
			await store.updateEntry({
				id: editingEntry.value.id,
				...toPayload(),
			})
		} else {
			await store.addEntry({
				...toPayload(),
				source: selectedSource.value,
				externalId: selectedExternalId.value,
			})
		}
		store.closeAddModal()
	} finally {
		loading.value = false
	}
}
</script>

<style scoped>
.food-entry-form {
	max-width: 560px;
	margin: 0 auto;
	padding: 40px 24px 24px;
}

.food-entry-form__title {
	margin: 0 0 28px;
	font-size: 1.4em;
	font-weight: bold;
	text-align: center;
}

.food-entry-form__fields {
	display: grid;
	gap: 16px;
	margin-bottom: 16px;
}

.food-entry-form__fields--single {
	grid-template-columns: 1fr;
}

.food-entry-form__fields--two {
	grid-template-columns: 1fr 1fr;
}

.food-entry-form__fields--three {
	grid-template-columns: 1fr 1fr 1fr;
}

.food-entry-form__field-wrap {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.food-entry-form__select-label {
	font-size: 0.9em;
	color: var(--color-text-maxcontrast);
}

.food-entry-form__section-label {
	margin: 4px 0 8px;
	font-size: 0.85em;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.05em;
	color: var(--color-text-maxcontrast);
}

.food-entry-form__search {
	margin-bottom: 16px;
}

.food-entry-form__search-input-row {
	position: relative;
}

.food-entry-form__search-input {
	width: 100%;
	height: 34px;
	padding: 0 8px;
	border: 1px solid var(--color-border-dark);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	color: var(--color-main-text);
	font-size: 1em;
	box-sizing: border-box;
}

.food-entry-form__search-spinner {
	position: absolute;
	right: 10px;
	top: 9px;
	width: 16px;
	height: 16px;
	border: 2px solid var(--color-border);
	border-top-color: var(--color-primary-element);
	border-radius: 50%;
	animation: spin 0.6s linear infinite;
}

@keyframes spin {
	to { transform: rotate(360deg); }
}

.food-entry-form__search-results {
	margin: 4px 0 0;
	padding: 4px 0;
	list-style: none;
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
}

.food-entry-form__search-result {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 8px 12px;
	cursor: pointer;
	gap: 8px;
}

.food-entry-form__search-result:hover,
.food-entry-form__search-result--active {
	background: var(--color-background-hover);
}

.food-entry-form__search-result-name {
	flex: 1;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.food-entry-form__search-result-kcal {
	font-size: 0.85em;
	color: var(--color-text-maxcontrast);
	white-space: nowrap;
}

.food-entry-form__search-feedback {
	display: flex;
	align-items: center;
	gap: 8px;
	margin-top: 8px;
}

.food-entry-form__search-empty {
	font-size: 0.9em;
	color: var(--color-text-maxcontrast);
	margin: 0;
}

.food-entry-form__search-empty--error {
	color: var(--color-error);
}

.food-entry-form__search-manual {
	margin-top: 8px;
}

.food-entry-form__preview {
	margin-bottom: 12px;
	font-weight: bold;
	color: var(--color-primary-element);
}

.food-entry-form__actions {
	display: flex;
	gap: 8px;
	justify-content: flex-end;
	margin-top: 16px;
}
</style>
