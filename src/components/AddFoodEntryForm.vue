<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<form class="food-entry-form" @submit.prevent="submit">
		<h3>{{ editingEntry ? t('calorietracker', 'Edit entry') : t('calorietracker', 'Add food') }}</h3>

		<!-- Search — hidden in edit mode -->
		<div v-if="!editingEntry && !showManual" class="food-entry-form__search">
			<div class="food-entry-form__search-input-row">
				<input
					v-model="searchQuery"
					class="food-entry-form__search-input"
					type="search"
					:placeholder="t('calorietracker', 'Search food database…')"
					autocomplete="off"
					@input="onSearchInput"
					@keydown.down.prevent="highlightNext"
					@keydown.up.prevent="highlightPrev"
					@keydown.enter.prevent="selectHighlighted"
					@keydown.esc="closeSearch"
					@blur="onSearchBlur">
				<span v-if="searchLoading" class="food-entry-form__search-spinner" />
			</div>
			<ul v-if="searchResults.length > 0" class="food-entry-form__search-results">
				<li
					v-for="(result, i) in searchResults"
					:key="i"
					class="food-entry-form__search-result"
					:class="{ 'food-entry-form__search-result--active': i === highlightedIndex }"
					@mousedown.prevent="selectResult(result)">
					<span class="food-entry-form__search-result-name">{{ result.name }}</span>
					<span class="food-entry-form__search-result-kcal">{{ result.caloriesPer100g }} kcal/100g</span>
				</li>
			</ul>
			<div v-else-if="searchError" class="food-entry-form__search-feedback">
				<p class="food-entry-form__search-empty food-entry-form__search-empty--error">
					{{ t('calorietracker', 'Could not reach food database.') }}
				</p>
				<NcButton type="tertiary" @click="showManual = true">
					{{ t('calorietracker', 'Add food manually') }}
				</NcButton>
			</div>
			<div v-else-if="searchDone && searchQuery.length >= 2" class="food-entry-form__search-feedback">
				<p class="food-entry-form__search-empty">
					{{ t('calorietracker', 'No results found.') }}
				</p>
				<NcButton type="tertiary" @click="showManual = true">
					{{ t('calorietracker', 'Add food manually') }}
				</NcButton>
			</div>
		</div>

		<!-- Manual fields — shown after selecting a result, clicking "Add manually", or in edit mode -->
		<template v-if="showManual || editingEntry">
			<div class="food-entry-form__fields">
				<NcInputField
					v-model="form.foodName"
					type="text"
					:label="t('calorietracker', 'Food name')"
					:placeholder="t('calorietracker', 'e.g. Oatmeal')"
					required />

				<NcInputField
					v-model.number="form.caloriesPer100g"
					type="number"
					:label="t('calorietracker', 'kcal per 100g')"
					min="1"
					required />

				<NcInputField
					v-model.number="form.amountGrams"
					type="number"
					:label="t('calorietracker', 'Amount (g)')"
					min="1"
					required />

				<div class="food-entry-form__field-wrap">
					<label class="food-entry-form__select-label">{{ t('calorietracker', 'Meal') }}</label>
					<NcSelect
						v-model="mealTypeOption"
						:options="mealTypeOptions"
						:clearable="false"
						label="label" />
				</div>

				<div class="food-entry-form__field-wrap">
					<label class="food-entry-form__select-label">{{ t('calorietracker', 'Date') }}</label>
					<NcDateTimePickerNative
						v-model="form.eatenAt"
						type="date"
						:label="t('calorietracker', 'Date')"
						required />
				</div>

				<NcInputField
					v-model.number="form.proteinPer100g"
					type="number"
					:label="t('calorietracker', 'Protein (g/100g)')"
					:placeholder="t('calorietracker', 'optional')"
					min="0" />

				<NcInputField
					v-model.number="form.carbsPer100g"
					type="number"
					:label="t('calorietracker', 'Carbs (g/100g)')"
					:placeholder="t('calorietracker', 'optional')"
					min="0" />

				<NcInputField
					v-model.number="form.fatPer100g"
					type="number"
					:label="t('calorietracker', 'Fat (g/100g)')"
					:placeholder="t('calorietracker', 'optional')"
					min="0" />
			</div>

			<div v-if="form.caloriesPer100g > 0 && form.amountGrams > 0" class="food-entry-form__preview">
				≈ {{ calculatedCalories }} kcal
			</div>
		</template>

		<div class="food-entry-form__actions">
			<NcButton type="tertiary" @click="$store.dispatch('foodEntries/closeAddModal')">
				{{ t('calorietracker', 'Cancel') }}
			</NcButton>
			<NcButton v-if="showManual || editingEntry" native-type="submit" type="primary" :disabled="loading">
				{{ editingEntry ? t('calorietracker', 'Save') : t('calorietracker', 'Add') }}
			</NcButton>
		</div>
	</form>
</template>

<script>
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcInputField from '@nextcloud/vue/dist/Components/NcInputField.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'
import NcDateTimePickerNative from '@nextcloud/vue/dist/Components/NcDateTimePickerNative.js'
import { mapState } from 'vuex'
import { toLocalDateString } from '../utils/date.js'
import offApi from '../services/OpenFoodFactsApi.js'

export default {
	name: 'AddFoodEntryForm',

	components: { NcButton, NcInputField, NcSelect, NcDateTimePickerNative },

	data() {
		return {
			loading: false,
			showManual: false,
			form: this.defaultForm(),
			searchQuery: '',
			searchResults: [],
			searchLoading: false,
			searchDone: false,
			highlightedIndex: -1,
			searchDebounce: null,
			searchError: false,
		}
	},

	computed: {
		...mapState('foodEntries', ['currentDate', 'editingEntry']),

		calculatedCalories() {
			return Math.round(this.form.caloriesPer100g * this.form.amountGrams / 100)
		},

		mealTypeOptions() {
			return [
				{ value: 'breakfast', label: t('calorietracker', 'Breakfast') },
				{ value: 'lunch',     label: t('calorietracker', 'Lunch') },
				{ value: 'dinner',    label: t('calorietracker', 'Dinner') },
				{ value: 'snack',     label: t('calorietracker', 'Snack') },
			]
		},

		mealTypeOption: {
			get() {
				return this.mealTypeOptions.find(o => o.value === this.form.mealType) ?? this.mealTypeOptions[0]
			},
			set(option) {
				this.form.mealType = option.value
			},
		},
	},

	watch: {
		editingEntry(entry) {
			this.form = entry ? this.entryToForm(entry) : this.defaultForm()
			this.showManual = false
		},
	},

	methods: {
		defaultForm() {
			return {
				foodName: '',
				caloriesPer100g: null,
				amountGrams: null,
				mealType: 'breakfast',
				eatenAt: this.currentDate ?? toLocalDateString(),
				proteinPer100g: null,
				carbsPer100g: null,
				fatPer100g: null,
			}
		},

		entryToForm(entry) {
			return {
				foodName: entry.foodName,
				caloriesPer100g: entry.caloriesPer100g,
				amountGrams: entry.amountGrams,
				mealType: entry.mealType,
				eatenAt: entry.eatenAt,
				proteinPer100g: entry.proteinPer100g ?? null,
				carbsPer100g: entry.carbsPer100g ?? null,
				fatPer100g: entry.fatPer100g ?? null,
			}
		},

		onSearchInput() {
			this.highlightedIndex = -1
			this.searchError = false
			clearTimeout(this.searchDebounce)
			if (this.searchQuery.length < 2) {
				this.searchResults = []
				this.searchDone = false
				return
			}
			this.searchDebounce = setTimeout(() => this.runSearch(), 600)
		},

		async runSearch() {
			this.searchLoading = true
			this.searchDone = false
			this.searchError = false
			try {
				this.searchResults = await offApi.search(this.searchQuery)
				this.searchDone = true
			} catch (e) {
				this.searchResults = []
				this.searchError = true
			} finally {
				this.searchLoading = false
			}
		},

		selectResult(result) {
			this.form.foodName = result.name
			this.form.caloriesPer100g = result.caloriesPer100g
			this.form.proteinPer100g = result.proteinPer100g ?? null
			this.form.carbsPer100g = result.carbsPer100g ?? null
			this.form.fatPer100g = result.fatPer100g ?? null
			this.showManual = true
			this.closeSearch()
		},

		closeSearch() {
			this.searchQuery = ''
			this.searchResults = []
			this.searchDone = false
			this.searchError = false
			this.highlightedIndex = -1
		},

		onSearchBlur() {
			setTimeout(() => this.closeSearch(), 200)
		},

		highlightNext() {
			if (this.searchResults.length === 0) return
			this.highlightedIndex = (this.highlightedIndex + 1) % this.searchResults.length
		},

		highlightPrev() {
			if (this.searchResults.length === 0) return
			this.highlightedIndex = (this.highlightedIndex - 1 + this.searchResults.length) % this.searchResults.length
		},

		selectHighlighted() {
			if (this.highlightedIndex >= 0 && this.searchResults[this.highlightedIndex]) {
				this.selectResult(this.searchResults[this.highlightedIndex])
			}
		},

		async submit() {
			this.loading = true
			try {
				if (this.editingEntry) {
					await this.$store.dispatch('foodEntries/updateEntry', {
						id: this.editingEntry.id,
						...this.form,
					})
				} else {
					await this.$store.dispatch('foodEntries/addEntry', { ...this.form })
				}
				this.$store.dispatch('foodEntries/closeAddModal')
			} finally {
				this.loading = false
			}
		},
	},
}
</script>

<style scoped>
.food-entry-form {
	padding: 16px;
	background: var(--color-main-background);
}

.food-entry-form h3 {
	margin-top: 0;
}

.food-entry-form__fields {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
	gap: 12px;
	margin-bottom: 12px;
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
