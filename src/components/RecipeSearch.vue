<!--
  - SPDX-FileCopyrightText: 2026 Anna Larch
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="recipe-search">
		<NcInputField v-model="searchQuery"
			type="search"
			:label="t('calorietracker', 'Search recipes')"
			:placeholder="t('calorietracker', 'e.g. Pasta, Salad, Soup…')"
			role="combobox"
			:aria-expanded="results.length > 0"
			aria-autocomplete="list"
			aria-controls="recipe-search-results"
			autocomplete="off"
			:loading="searchLoading"
			@input="onSearchInput"
			@keydown.down.prevent="highlightNext"
			@keydown.up.prevent="highlightPrev"
			@keydown.enter.prevent="selectHighlighted"
			@keydown.esc="closeSearch"
			@blur="onSearchBlur" />

		<div v-if="searchLoading && results.length === 0" class="recipe-search__status">
			<p>{{ t('calorietracker', 'Searching recipes…') }}</p>
		</div>

		<div v-else-if="searchError" class="recipe-search__status">
			<p class="recipe-search__error">
				{{ t('calorietracker', 'Could not search recipes. Is the Cookbook app working?') }}
			</p>
		</div>

		<div v-else-if="searchDone && results.length === 0 && searchQuery.trim().length >= 2" class="recipe-search__status">
			<p>{{ t('calorietracker', 'No recipes found.') }}</p>
		</div>

		<ul v-if="results.length > 0"
			id="recipe-search-results"
			class="recipe-search__results"
			role="listbox"
			:aria-label="t('calorietracker', 'Recipe results')">
			<li v-for="(recipe, i) in results"
				:key="recipe.id"
				class="recipe-search__result"
				:class="{ 'recipe-search__result--active': i === highlightedIndex }"
				role="option"
				:aria-selected="i === highlightedIndex"
				@mousedown.prevent="selectRecipe(recipe)">
				<span class="recipe-search__result-name">{{ recipe.name }}</span>
			</li>
		</ul>

		<!-- Recipe detail / estimation view -->
		<div v-if="selectedRecipe" class="recipe-search__detail">
			<h3 class="recipe-search__detail-name">
				{{ selectedRecipe.name }}
			</h3>

			<div v-if="estimating" class="recipe-search__status">
				<NcLoadingIcon :size="28" />
				<p>{{ t('calorietracker', 'Estimating nutrition from ingredients…') }}</p>
			</div>

			<template v-else>
				<div v-if="selectedRecipe.caloriesPerServing != null" class="recipe-search__nutrition">
					<p class="recipe-search__section-label">
						{{ t('calorietracker', 'Nutrition per serving') }}
					</p>
					<div class="recipe-search__nutrition-grid">
						<span>{{ displayEnergy(selectedRecipe.caloriesPerServing) }} {{ energyLabel }}</span>
						<span v-if="selectedRecipe.proteinPerServing != null">P {{ selectedRecipe.proteinPerServing }}g</span>
						<span v-if="selectedRecipe.carbsPerServing != null">C {{ selectedRecipe.carbsPerServing }}g</span>
						<span v-if="selectedRecipe.fatPerServing != null">F {{ selectedRecipe.fatPerServing }}g</span>
					</div>
				</div>

				<div v-if="estimationError" class="recipe-search__status">
					<p class="recipe-search__error">
						{{ estimationError }}
					</p>
				</div>

				<div class="recipe-search__detail-actions">
					<NcButton v-if="selectedRecipe.caloriesPerServing == null"
						variant="primary"
						:disabled="estimating"
						@click="estimateNutrition">
						{{ t('calorietracker', 'Estimate nutrition') }}
					</NcButton>
					<NcButton v-if="selectedRecipe.caloriesPerServing != null"
						variant="primary"
						@click="useRecipe">
						{{ t('calorietracker', 'Use this recipe') }}
					</NcButton>
					<NcButton variant="secondary"
						@click="selectedRecipe = null">
						{{ t('calorietracker', 'Back to results') }}
					</NcButton>
				</div>
			</template>
		</div>
	</div>
</template>

<script setup>
import { ref } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { useUnits } from '../composables/useUnits.js'
import cookbookApi from '../services/CookbookApi.js'
import { estimateRecipeNutrition, estimateGramsPerServing } from '../utils/ingredientParser.js'

const emit = defineEmits(['select'])
const { displayEnergy, energyLabel } = useUnits()

const searchQuery = ref('')
const results = ref([])
const searchLoading = ref(false)
const searchDone = ref(false)
const searchError = ref(false)
const searchDebounce = ref(null)
const highlightedIndex = ref(-1)
const selectedRecipe = ref(null)
const estimating = ref(false)
const estimationError = ref('')

/**
 *
 */
function onSearchInput() {
	highlightedIndex.value = -1
	searchError.value = false
	clearTimeout(searchDebounce.value)
	if (searchQuery.value.trim().length < 2) {
		results.value = []
		searchDone.value = false
		return
	}
	searchDebounce.value = setTimeout(() => runSearch(), 600)
}

/**
 *
 */
function highlightNext() {
	if (results.value.length === 0) return
	highlightedIndex.value = (highlightedIndex.value + 1) % results.value.length
}

/**
 *
 */
function highlightPrev() {
	if (results.value.length === 0) return
	if (highlightedIndex.value <= 0) {
		highlightedIndex.value = results.value.length - 1
		return
	}
	highlightedIndex.value--
}

/**
 *
 */
function selectHighlighted() {
	if (highlightedIndex.value >= 0 && results.value[highlightedIndex.value]) {
		selectRecipe(results.value[highlightedIndex.value])
	}
}

/**
 *
 */
function closeSearch() {
	searchQuery.value = ''
	results.value = []
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
async function runSearch() {
	searchLoading.value = true
	searchDone.value = false
	searchError.value = false
	selectedRecipe.value = null
	try {
		const data = await cookbookApi.search(searchQuery.value)
		results.value = data
		searchDone.value = true
	} catch (e) {
		console.error('Cookbook search failed:', e)
		searchError.value = true
		results.value = []
		searchDone.value = true
	} finally {
		searchLoading.value = false
	}
}

/**
 *
 * @param recipe
 */
async function selectRecipe(recipe) {
	selectedRecipe.value = null
	estimationError.value = ''
	try {
		const detail = await cookbookApi.getRecipe(recipe.id)
		detail.hasNutrition = detail.caloriesPerServing != null
		if (detail.recipeIngredient?.length) {
			detail.gramsPerServing = estimateGramsPerServing(detail.recipeIngredient, detail.recipeYield)
		}
		selectedRecipe.value = detail
		results.value = []
	} catch (e) {
		console.error('Failed to load recipe:', e)
		estimationError.value = t('calorietracker', 'Failed to load recipe details.')
	}
}

/**
 *
 */
async function estimateNutrition() {
	if (!selectedRecipe.value?.recipeIngredient?.length) {
		estimationError.value = t('calorietracker', 'This recipe has no ingredients to estimate from.')
		return
	}

	estimating.value = true
	estimationError.value = ''
	try {
		const nutrition = await estimateRecipeNutrition(
			selectedRecipe.value.recipeIngredient,
			selectedRecipe.value.recipeYield,
		)

		// Update the local recipe view
		selectedRecipe.value = {
			...selectedRecipe.value,
			...nutrition,
			hasNutrition: true,
		}

		// Write the nutrition back to the cookbook app
		try {
			await cookbookApi.updateNutrition(selectedRecipe.value.id, {
				calories: nutrition.caloriesPerServing,
				protein: nutrition.proteinPerServing,
				carbs: nutrition.carbsPerServing,
				fat: nutrition.fatPerServing,
				servingSize: nutrition.servingSize,
			})
		} catch (e) {
			// Non-fatal: we can still use the estimated values
			console.warn('Failed to save nutrition back to cookbook:', e)
		}
	} catch (e) {
		console.error('Nutrition estimation failed:', e)
		estimationError.value = t('calorietracker', 'Could not estimate nutrition. Some ingredients may not have been found in the food database.')
	} finally {
		estimating.value = false
	}
}

/**
 *
 */
function useRecipe() {
	if (!selectedRecipe.value) return
	emit('select', {
		name: selectedRecipe.value.name,
		source: 'cookbook',
		externalId: String(selectedRecipe.value.id),
		caloriesPerServing: selectedRecipe.value.caloriesPerServing,
		proteinPerServing: selectedRecipe.value.proteinPerServing,
		carbsPerServing: selectedRecipe.value.carbsPerServing,
		fatPerServing: selectedRecipe.value.fatPerServing,
		gramsPerServing: selectedRecipe.value.gramsPerServing ?? 100,
		recipeYield: selectedRecipe.value.recipeYield,
	})
}
</script>

<style scoped>
.recipe-search {
	display: flex;
	flex-direction: column;
	gap: 10px;
}

.recipe-search__status {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 0.9em;
	color: var(--color-text-maxcontrast);
}

.recipe-search__error {
	color: var(--color-error);
	margin: 0;
}

.recipe-search__results {
	margin: 4px 0 0;
	padding: 4px 0;
	list-style: none;
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
}

.recipe-search__result {
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: 8px;
	padding: 6px 12px;
	cursor: pointer;
}

.recipe-search__result:hover,
.recipe-search__result--active {
	background: var(--color-background-hover);
}

.recipe-search__result-name {
	flex: 1;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.recipe-search__badge {
	font-size: 0.8em;
	white-space: nowrap;
	color: var(--color-text-maxcontrast);
}

.recipe-search__badge--has-nutrition {
	color: var(--color-success);
}

.recipe-search__detail {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.recipe-search__detail-name {
	margin: 0;
	font-size: 1.05em;
	font-weight: bold;
}

.recipe-search__section-label {
	margin: 0 0 4px;
	font-size: 0.85em;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.05em;
	color: var(--color-text-maxcontrast);
}

.recipe-search__nutrition-grid {
	display: flex;
	flex-wrap: wrap;
	gap: 4px 16px;
	font-size: 0.95em;
}

.recipe-search__detail-actions {
	display: flex;
	gap: 8px;
}

</style>
