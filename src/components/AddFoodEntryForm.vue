<!--
  - SPDX-FileCopyrightText: 2026 Anna Larch
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<form class="food-entry-form" @submit.prevent="submit">
		<div class="food-entry-form__scroll">
			<h2 class="food-entry-form__title">
				{{ editingEntry ? t('calorietracker', 'Edit entry') : t('calorietracker', 'Add food') }}
			</h2>

			<!-- Search — hidden in edit mode -->
			<div v-if="!editingEntry && !showManual" class="food-entry-form__search">
				<div class="food-entry-form__search-input-row">
					<NcInputField v-model="searchQuery"
						type="search"
						:label="t('calorietracker', 'Search food database')"
						:placeholder="t('calorietracker', 'e.g. Oatmeal, Banana, Chicken breast…')"
						role="combobox"
						:aria-expanded="searchResults.length > 0"
						aria-autocomplete="list"
						aria-controls="food-search-results"
						autocomplete="off"
						:loading="searchLoading"
						@input="onSearchInput"
						@keydown.down.prevent="highlightNext"
						@keydown.up.prevent="highlightPrev"
						@keydown.enter.prevent="selectHighlighted"
						@keydown.esc="closeSearch"
						@blur="onSearchBlur" />
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
						<div class="food-entry-form__search-result-top">
							<span class="food-entry-form__search-result-name">{{ result.name }}</span>
							<span class="food-entry-form__search-result-source"
								:class="`food-entry-form__search-result-source--${result.source}`">{{ result.source === 'off' ? 'OFF' : 'USDA' }}</span>
						</div>
						<div class="food-entry-form__search-result-bottom">
							<span class="food-entry-form__search-result-kcal">{{ displayEnergy(displayPer100g(result.caloriesPer100g)) }} {{ energyLabel }}/{{ isImperial ? 'oz' : '100g' }}</span>
							<span v-if="result.proteinPer100g != null" class="food-entry-form__search-result-macro">P {{ displayPer100g(result.proteinPer100g) }}{{ weightLabel }}</span>
							<span v-if="result.carbsPer100g != null" class="food-entry-form__search-result-macro">C {{ displayPer100g(result.carbsPer100g) }}{{ weightLabel }}</span>
							<span v-if="result.fatPer100g != null" class="food-entry-form__search-result-macro">F {{ displayPer100g(result.fatPer100g) }}{{ weightLabel }}</span>
						</div>
					</li>
				</ul>
				<p v-if="searchWarning" class="food-entry-form__search-warning">
					{{ searchWarning }}
				</p>
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
			</div>

			<!-- Just-added entries — shown in add mode after submitting -->
			<div v-if="!editingEntry && !showManual && addedEntries.length > 0" class="food-entry-form__added">
				<p class="food-entry-form__section-label">
					{{ t('calorietracker', 'Added') }}
				</p>
				<NcFormBox>
					<NcFormBoxButton v-for="entry in addedEntries"
						:key="entry.id"
						:label="entry.foodName"
						@click="editAddedEntry(entry)">
						<template #description>
							<span class="food-entry-form__details">
								<span class="food-entry-form__detail">{{ displayWeight(entry.amountGrams) }}{{ weightLabel }}</span>
								<span class="food-entry-form__detail food-entry-form__detail--energy">{{ displayEnergy(Math.round(entry.caloriesPer100g * entry.amountGrams / 100)) }} {{ energyLabel }}</span>
								<span v-if="entry.proteinPer100g != null" class="food-entry-form__detail food-entry-form__detail--macro">P {{ entryMacroGrams(entry.proteinPer100g, entry.amountGrams) }}{{ weightLabel }}</span>
								<span v-if="entry.carbsPer100g != null" class="food-entry-form__detail food-entry-form__detail--macro">C {{ entryMacroGrams(entry.carbsPer100g, entry.amountGrams) }}{{ weightLabel }}</span>
								<span v-if="entry.fatPer100g != null" class="food-entry-form__detail food-entry-form__detail--macro">F {{ entryMacroGrams(entry.fatPer100g, entry.amountGrams) }}{{ weightLabel }}</span>
							</span>
						</template>
						<template #icon>
							<NcButton variant="tertiary"
								:aria-label="t('calorietracker', 'Edit')"
								@click.stop="editAddedEntry(entry)">
								<template #icon>
									<NcIconSvgWrapper :svg="iconPencil" />
								</template>
							</NcButton>
							<NcButton variant="tertiary"
								:aria-label="t('calorietracker', 'Delete')"
								@click.stop="deleteAddedEntry(entry)">
								<template #icon>
									<NcIconSvgWrapper :svg="iconTrash" />
								</template>
							</NcButton>
						</template>
					</NcFormBoxButton>
				</NcFormBox>
			</div>

			<!-- Frequently used foods — shown below search in add mode -->
			<div v-if="!editingEntry && !showManual && frequentFoods.length > 0" class="food-entry-form__frequent">
				<p class="food-entry-form__section-label">
					{{ t('calorietracker', 'Frequently used') }}
				</p>
				<div class="food-entry-form__frequent-list">
					<button v-for="food in frequentFoods"
						:key="food.id"
						type="button"
						class="food-entry-form__frequent-chip"
						@click="selectResult(food)">
						<span class="food-entry-form__frequent-name">{{ food.name }}</span>
						<span class="food-entry-form__frequent-kcal">{{ displayEnergy(displayPer100g(food.caloriesPer100g)) }} {{ energyLabel }}</span>
					</button>
				</div>
			</div>

			<!-- Manual fields — shown after selecting a result, clicking "Add manually", or in edit mode -->
			<template v-if="showManual || editingEntry || editingAddedEntry">
				<!-- Food name: full width -->
				<div class="food-entry-form__fields food-entry-form__fields--single">
					<NcInputField v-model="form.foodName"
						type="text"
						:label="t('calorietracker', 'Food name')"
						:placeholder="t('calorietracker', 'e.g. Oatmeal')"
						required />
				</div>

				<!-- kcal -->
				<div class="food-entry-form__fields food-entry-form__fields--single">
					<NcInputField v-model.number="form.caloriesPer100g"
						type="number"
						:label="t('calorietracker', '{energy} {per}', { energy: energyLabel, per: perWeightLabel })"
						min="1"
						required />
				</div>

				<!-- Amount + unit side by side -->
				<div class="food-entry-form__fields food-entry-form__fields--two">
					<div class="food-entry-form__field-wrap">
						<label for="food-entry-amount" class="food-entry-form__select-label">{{ t('calorietracker', 'Amount') }}</label>
						<NcInputField ref="amountField"
							v-model.number="form.amount"
							input-id="food-entry-amount"
							type="number"
							:min="selectedUnit?.value === 'g' ? '1' : '0.01'"
							:step="selectedUnit?.value === 'g' ? '1' : 'any'"
							required />
					</div>

					<div class="food-entry-form__field-wrap">
						<label for="food-entry-unit" class="food-entry-form__select-label">{{ t('calorietracker', 'Unit') }}</label>
						<NcSelect v-model="selectedUnit"
							input-id="food-entry-unit"
							:options="unitOptions"
							:clearable="false"
							label="label" />
					</div>
				</div>

				<!-- Meal + date side by side -->
				<div class="food-entry-form__fields food-entry-form__fields--two">
					<div class="food-entry-form__field-wrap">
						<label for="food-entry-meal" class="food-entry-form__select-label">{{ t('calorietracker', 'Meal') }}</label>
						<NcSelect v-model="mealTypeOption"
							input-id="food-entry-meal"
							:options="mealTypeOptions"
							:clearable="false"
							label="label" />
					</div>

					<div class="food-entry-form__field-wrap">
						<label for="food-entry-date" class="food-entry-form__select-label">{{ t('calorietracker', 'Date') }}</label>
						<NcDateTimePickerNative id="food-entry-date"
							v-model="eatenAtDate"
							type="date"
							hide-label
							required />
					</div>
				</div>

				<!-- Calorie preview -->
				<div class="food-entry-form__preview">
					≈ {{ form.caloriesPer100g > 0 && form.amount > 0 ? calculatedCalories : 0 }} {{ energyLabel }}
				</div>

				<!-- Macros: 3 columns -->
				<p class="food-entry-form__section-label">
					{{ t('calorietracker', 'Macros {per} (optional)', { per: perWeightLabel }) }}
				</p>
				<div class="food-entry-form__fields food-entry-form__fields--three">
					<NcInputField v-model.number="form.proteinPer100g"
						type="number"
						:label="t('calorietracker', 'Protein ({unit})', { unit: weightLabel })"
						min="0" />

					<NcInputField v-model.number="form.carbsPer100g"
						type="number"
						:label="t('calorietracker', 'Carbs ({unit})', { unit: weightLabel })"
						min="0" />

					<NcInputField v-model.number="form.fatPer100g"
						type="number"
						:label="t('calorietracker', 'Fat ({unit})', { unit: weightLabel })"
						min="0" />
				</div>
			</template>
		</div>
		<div class="food-entry-form__actions">
			<NcButton variant="secondary"
				native-type="button"
				@click="store.closeAddModal()">
				{{ t('calorietracker', 'Cancel') }}
			</NcButton>
			<NcButton v-if="!showManual && !editingEntry"
				variant="secondary"
				native-type="button"
				@click="showManual = true">
				{{ t('calorietracker', 'Add food manually') }}
			</NcButton>
			<span class="food-entry-form__actions-spacer" />
			<NcButton v-if="addedEntries.length > 0 && !showManual && !editingEntry"
				variant="primary"
				native-type="button"
				@click="store.closeAddModal()">
				{{ t('calorietracker', 'Done') }}
			</NcButton>
			<NcButton v-if="showManual || editingEntry || editingAddedEntry"
				variant="primary"
				native-type="submit"
				:disabled="loading || !canSubmit">
				{{ editingEntry || editingAddedEntry ? t('calorietracker', 'Save') : t('calorietracker', 'Add') }}
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
import NcFormBox from '@nextcloud/vue/components/NcFormBox'
import NcFormBoxButton from '@nextcloud/vue/components/NcFormBoxButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { useFoodEntriesStore } from '../stores/foodEntries.js'
import { toLocalDateString } from '../utils/date.js'
import { useUnits } from '../composables/useUnits.js'
import offApi from '../services/OpenFoodFactsApi.js'
import usdaApi from '../services/UsdaFdcApi.js'
import foodItemApi from '../services/FoodItemApi.js'

const store = useFoodEntriesStore()
const { currentDate, editingEntry } = storeToRefs(store)
const { energyLabel, weightLabel, perWeightLabel, displayWeight, displayPer100g, toPer100g, displayEnergy, toKcal, isImperial, entryMacroGrams } = useUnits()

const iconPencil = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M20.71 7.04c.39-.39.39-1.04 0-1.41l-2.34-2.34c-.37-.39-1.02-.39-1.41 0l-1.84 1.83 3.75 3.75M3 17.25V21h3.75L17.81 9.93l-3.75-3.75L3 17.25z"/></svg>'
const iconTrash = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M19 4h-3.5l-1-1h-5l-1 1H5v2h14M6 19a2 2 0 002 2h8a2 2 0 002-2V7H6v12z"/></svg>'

// Template ref
const amountField = ref(null)

// Form state
const loading = ref(false)
const showManual = ref(false)
const selectedSource = ref(null)
const selectedExternalId = ref(null)
const addedEntries = ref([])
const editingAddedEntry = ref(null)

// Unit dropdown state
const GRAMS_PER_OZ = 28.3495
const defaultUnitOptions = () => [{ value: isImperial.value ? 'oz' : 'g', label: weightLabel.value, gramsPerUnit: isImperial.value ? GRAMS_PER_OZ : 1 }]
const unitOptions = ref(defaultUnitOptions())
const selectedUnit = ref(unitOptions.value[0])

// Search state
const searchQuery = ref('')
const searchResults = ref([])
const searchLoading = ref(false)
const searchDone = ref(false)
const highlightedIndex = ref(-1)
const searchDebounce = ref(null)
const searchError = ref(false)
const searchWarning = ref('')
const frequentFoods = ref([])
const frequentLoaded = ref(false)

/**
 *
 */
function defaultForm() {
	return {
		foodName: '',
		caloriesPer100g: '',
		amount: '',
		mealType: 'breakfast',
		eatenAt: currentDate.value ?? toLocalDateString(),
		proteinPer100g: '',
		carbsPer100g: '',
		fatPer100g: '',
	}
}

/**
 * Reset unit options to the default base unit for the current measurement system.
 */
function resetUnits() {
	unitOptions.value = defaultUnitOptions()
	selectedUnit.value = unitOptions.value[0]
}

/**
 * Convert a stored entry (metric) to form values (user's preferred units).
 * @param {object} entry Stored food entry in metric units
 */
function entryToForm(entry) {
	return {
		foodName: entry.foodName,
		caloriesPer100g: entry.caloriesPer100g != null ? displayEnergy(displayPer100g(entry.caloriesPer100g)) : '',
		amount: entry.amountGrams != null ? displayWeight(entry.amountGrams) : '',
		mealType: entry.mealType,
		eatenAt: entry.eatenAt,
		proteinPer100g: entry.proteinPer100g != null ? displayPer100g(entry.proteinPer100g) : '',
		carbsPer100g: entry.carbsPer100g != null ? displayPer100g(entry.carbsPer100g) : '',
		fatPer100g: entry.fatPer100g != null ? displayPer100g(entry.fatPer100g) : '',
	}
}

const form = reactive(defaultForm())

watch(editingEntry, (entry) => {
	Object.assign(form, entry ? entryToForm(entry) : defaultForm())
	resetUnits()
	showManual.value = false
	addedEntries.value = []
	editingAddedEntry.value = null
	if (!entry && !frequentLoaded.value) {
		fetchFrequentFoods()
	}
}, { immediate: true })

/**
 * Load the user's frequently used food items.
 */
async function fetchFrequentFoods() {
	try {
		frequentFoods.value = await foodItemApi.getFrequent(8)
	} catch (error) {
		console.error('Failed to load frequent foods:', error)
	} finally {
		frequentLoaded.value = true
	}
}

const canSubmit = computed(() => {
	return form.foodName.trim() !== '' && form.caloriesPer100g > 0 && form.amount > 0
})

/**
 * Convert the current form amount + unit to grams.
 */
function amountToGrams() {
	return Number(form.amount) * (selectedUnit.value?.gramsPerUnit ?? 1)
}

const calculatedCalories = computed(() => {
	// Form values are in display units; convert to metric for the preview
	const kcalPer100g = toPer100g(toKcal(form.caloriesPer100g))
	const grams = amountToGrams()
	const kcal = Math.round(kcalPer100g * grams / 100)
	return displayEnergy(kcal)
})

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
	searchWarning.value = ''
	clearTimeout(searchDebounce.value)
	if (searchQuery.value.trim().length < 2) {
		searchResults.value = []
		searchDone.value = false
		return
	}
	searchDebounce.value = setTimeout(() => runSearch(), 600)
}

/**
 * Score a result by how closely its name matches the query.
 * Lower score = better match.
 * @param {string} name Food name
 * @param {string} query Search query
 * @return {number} Relevance score
 */
function matchScore(name, query) {
	const n = name.toLowerCase()
	const q = query.toLowerCase().trim()
	const words = q.split(/\s+/)

	// Exact match
	if (n === q) return 0
	// Name starts with query
	if (n.startsWith(q)) return 1
	// Name contains query as substring
	if (n.includes(q)) return 2
	// All query words present
	const allPresent = words.every(w => n.includes(w))
	if (allPresent) {
		// Prefer shorter names (more specific matches)
		return 3 + n.length / 1000
	}
	// Some words present
	const matched = words.filter(w => n.includes(w)).length
	return 4 + (1 - matched / words.length) + n.length / 1000
}

/**
 * Rank merged search results by relevance to the query.
 * @param {Array} results Combined results from all sources
 * @param {string} query Search query
 * @return {Array} Sorted results
 */
function rankResults(results, query) {
	return results
		.map(r => ({ ...r, _score: matchScore(r.name, query) }))
		.sort((a, b) => a._score - b._score)
		.map(({ _score, ...r }) => r)
}

/**
 *
 */
async function runSearch() {
	const query = searchQuery.value.trim()
	searchLoading.value = true
	searchDone.value = false
	searchError.value = false
	searchWarning.value = ''
	try {
		const [usdaRes, offRes] = await Promise.allSettled([
			usdaApi.search(query),
			offApi.search(query),
		])
		if (usdaRes.status === 'rejected') {
			console.error('USDA search failed:', usdaRes.reason)
		}
		if (offRes.status === 'rejected') {
			console.error('OFF search failed:', offRes.reason)
		}
		const merged = [
			...(usdaRes.status === 'fulfilled' ? usdaRes.value : []),
			...(offRes.status === 'fulfilled' ? offRes.value : []),
		]
		searchResults.value = rankResults(merged, query)
		const bothFailed = usdaRes.status === 'rejected' && offRes.status === 'rejected'
		searchError.value = bothFailed
		if (!bothFailed && usdaRes.status === 'rejected') {
			searchWarning.value = t('calorietracker', 'USDA database unavailable — showing Open Food Facts results only.')
		} else if (!bothFailed && offRes.status === 'rejected') {
			searchWarning.value = t('calorietracker', 'Open Food Facts unavailable — showing USDA results only.')
		}
		searchDone.value = true
	} finally {
		searchLoading.value = false
	}
}

/**
 * Populate form fields from a search result.
 * @param {object} result Food search result
 */
function selectResult(result) {
	form.foodName = result.name
	form.caloriesPer100g = displayEnergy(displayPer100g(result.caloriesPer100g))
	form.proteinPer100g = result.proteinPer100g != null ? displayPer100g(result.proteinPer100g) : ''
	form.carbsPer100g = result.carbsPer100g != null ? displayPer100g(result.carbsPer100g) : ''
	form.fatPer100g = result.fatPer100g != null ? displayPer100g(result.fatPer100g) : ''
	selectedSource.value = result.source ?? null
	selectedExternalId.value = result.externalId ?? null

	// Build unit options: always include grams, add serving if available
	const options = defaultUnitOptions()
	if (result.servingSizeGrams && result.servingSizeGrams > 0) {
		const desc = result.servingDescription
			? t('calorietracker', 'serving ({desc})', { desc: result.servingDescription })
			: t('calorietracker', 'serving ({grams}{unit})', { grams: displayWeight(result.servingSizeGrams), unit: weightLabel.value })
		options.push({
			value: 'serving',
			label: desc,
			gramsPerUnit: result.servingSizeGrams,
		})
	}
	unitOptions.value = options
	selectedUnit.value = options[0]

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
	searchWarning.value = ''
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
	const { amount, ...rest } = form
	return {
		...rest,
		caloriesPer100g: toPer100g(toKcal(Number(form.caloriesPer100g))),
		amountGrams: Math.max(1, Math.round(amountToGrams())),
		proteinPer100g: nullIfEmpty(form.proteinPer100g) !== null ? toPer100g(Number(form.proteinPer100g)) : null,
		carbsPer100g: nullIfEmpty(form.carbsPer100g) !== null ? toPer100g(Number(form.carbsPer100g)) : null,
		fatPer100g: nullIfEmpty(form.fatPer100g) !== null ? toPer100g(Number(form.fatPer100g)) : null,
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
			store.closeAddModal()
		} else if (editingAddedEntry.value) {
			// Re-saving a previously added entry
			const updated = await store.updateEntry({
				id: editingAddedEntry.value.id,
				...toPayload(),
			})
			// Replace the old version in the addedEntries list with the server-returned entry
			const idx = addedEntries.value.findIndex(e => e.id === editingAddedEntry.value.id)
			if (idx !== -1) addedEntries.value.splice(idx, 1, updated)
			editingAddedEntry.value = null
			Object.assign(form, defaultForm())
			resetUnits()
			showManual.value = false
			selectedSource.value = null
			selectedExternalId.value = null
		} else {
			const payload = toPayload()
			const entry = await store.addEntry({
				...payload,
				source: selectedSource.value,
				externalId: selectedExternalId.value,
			})
			// Remember the added entry and reset to search view
			addedEntries.value.push(entry)
			Object.assign(form, defaultForm())
			resetUnits()
			showManual.value = false
			selectedSource.value = null
			selectedExternalId.value = null
		}
	} finally {
		loading.value = false
	}
}

/**
 * Edit a previously added entry: populate the form and switch to manual mode.
 * @param {object} entry The added entry to edit
 */
function editAddedEntry(entry) {
	Object.assign(form, entryToForm(entry))
	resetUnits()
	selectedSource.value = entry.source ?? null
	selectedExternalId.value = entry.externalId ?? null
	editingAddedEntry.value = entry
	showManual.value = true
}

/**
 * Delete an added entry from the server and the local list.
 * @param {object} entry The added entry to delete
 */
async function deleteAddedEntry(entry) {
	await store.deleteEntry(entry.id)
	addedEntries.value = addedEntries.value.filter(e => e.id !== entry.id)

	if (editingAddedEntry.value?.id === entry.id) {
		editingAddedEntry.value = null
		Object.assign(form, defaultForm())
		resetUnits()
		showManual.value = false
		selectedSource.value = null
		selectedExternalId.value = null
	}
}
</script>

<style scoped>
.food-entry-form {
	display: flex;
	flex-direction: column;
	flex: 1;
	min-height: 0;
	width: 100%;
	margin: 0 auto;
	padding: 0 20px 16px;
	box-sizing: border-box;
	overflow: hidden;
}

.food-entry-form__scroll {
	flex: 1;
	overflow-y: auto;
	overflow-x: hidden;
	padding-top: 20px;
}

.food-entry-form__title {
	margin: 0 0 12px;
	font-size: 1.15em;
	font-weight: bold;
	text-align: center;
}

.food-entry-form__fields {
	display: grid;
	gap: 10px;
	margin-bottom: 10px;
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

/* Normalize NcInputField inside field-wrap: remove its built-in top margin */
.food-entry-form__field-wrap :deep(.input-field) {
	margin-block-start: 0;
}

/* Normalize NcSelect inside field-wrap: match NcInputField height and remove bottom margin */
.food-entry-form__field-wrap :deep(.v-select.select) {
	height: var(--default-clickable-area);
	min-height: unset !important;
	min-width: unset;
	margin: 0;
}

.food-entry-form__field-wrap :deep(.vs__dropdown-toggle) {
	height: 100% !important;
	padding: var(--border-width-input-focused, 2px);
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

.food-entry-form__added {
	margin-bottom: 10px;
}

.food-entry-form__details {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 4px 12px;
}

.food-entry-form__detail {
	display: inline-block;
	white-space: nowrap;
	min-width: 4em;
}

.food-entry-form__detail--energy {
	min-width: 5.5em;
	font-weight: 500;
}

.food-entry-form__detail--macro {
	min-width: 4.5em;
}

.food-entry-form__added :deep(.form-box) {
	width: 100%;
}

.food-entry-form__frequent {
	margin-bottom: 10px;
}

.food-entry-form__frequent-list {
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
}

.food-entry-form__frequent-chip {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	padding: 4px 10px;
	border: 1px solid var(--color-border-dark);
	border-radius: var(--border-radius-pill);
	background: var(--color-background-soft);
	color: var(--color-main-text);
	font-size: 0.85em;
	cursor: pointer;
	transition: background 0.1s;
}

.food-entry-form__frequent-chip:hover {
	background: var(--color-background-hover);
}

.food-entry-form__frequent-kcal {
	font-size: 0.85em;
	color: var(--color-text-maxcontrast);
}

.food-entry-form__search {
	margin-bottom: 10px;
}

.food-entry-form__search-input-row {
	position: relative;
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
	flex-direction: column;
	padding: 6px 12px;
	cursor: pointer;
	gap: 2px;
}

.food-entry-form__search-result:hover,
.food-entry-form__search-result--active {
	background: var(--color-background-hover);
}

.food-entry-form__search-result-top {
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: 8px;
}

.food-entry-form__search-result-name {
	flex: 1;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.food-entry-form__search-result-bottom {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 0.8em;
	color: var(--color-text-maxcontrast);
}

.food-entry-form__search-result-kcal {
	white-space: nowrap;
}

.food-entry-form__search-result-macro {
	white-space: nowrap;
}

.food-entry-form__search-result-source {
	font-size: 0.75em;
	font-weight: 600;
	padding: 1px 5px;
	border-radius: var(--border-radius);
	white-space: nowrap;
}

.food-entry-form__search-result-source--usda_fdc {
	background: color-mix(in srgb, var(--color-primary-element) 15%, transparent);
	color: var(--color-primary-element-text, var(--color-primary-text));
}

.food-entry-form__search-result-source--off {
	background: color-mix(in srgb, var(--color-success) 15%, transparent);
	color: var(--color-success-text, var(--color-text-maxcontrast));
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

.food-entry-form__search-warning {
	margin: 4px 0 0;
	font-size: 0.8em;
	color: var(--color-warning-text, var(--color-text-maxcontrast));
}

.food-entry-form__preview {
	margin-bottom: 12px;
	font-weight: bold;
	color: var(--color-primary-element);
}

.food-entry-form__actions {
	display: flex;
	flex-shrink: 0;
	gap: 8px;
	padding-top: 12px;
	border-top: 1px solid var(--color-border);
}

.food-entry-form__actions-spacer {
	flex: 1;
}
</style>
