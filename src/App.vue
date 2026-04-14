<!--
  - SPDX-FileCopyrightText: 2026 Anna Larch
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcContent app-name="calorietracker">
		<DaySidebar />
		<NcAppContent>
			<router-view />
		</NcAppContent>
		<NcModal v-if="addModalOpen"
			size="normal"
			class="add-food-modal"
			:name="editingEntry ? t('calorietracker', 'Edit entry') : t('calorietracker', 'Add food')"
			@close="store.closeAddModal()">
			<AddFoodEntryForm />
		</NcModal>
		<SettingsView />
		<WeightLogModal v-if="weightLogModalOpen" @close="weightLogStore.closeLogModal()" />
	</NcContent>
</template>

<script setup>
import { storeToRefs } from 'pinia'
import { translate as t } from '@nextcloud/l10n'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcModal from '@nextcloud/vue/components/NcModal'
import DaySidebar from './components/DaySidebar.vue'
import AddFoodEntryForm from './components/AddFoodEntryForm.vue'
import SettingsView from './components/SettingsView.vue'
import WeightLogModal from './components/WeightLogModal.vue'
import { useFoodEntriesStore } from './stores/foodEntries.js'
import { useWeightLogStore } from './stores/weightLog.js'

const store = useFoodEntriesStore()
const { addModalOpen, editingEntry } = storeToRefs(store)

const weightLogStore = useWeightLogStore()
const { logModalOpen: weightLogModalOpen } = storeToRefs(weightLogStore)
</script>

<style>
/* Fixed-size modal with internal scrolling */
.add-food-modal .modal-container {
	height: min(70vh, calc(100% - 2 * var(--header-height, 50px) - 2 * var(--body-container-margin, 0px))) !important;
	overflow: hidden !important;
	display: flex !important;
	flex-direction: column !important;
}

.add-food-modal .modal-container__content {
	flex: 1 !important;
	min-height: 0 !important;
	overflow: hidden !important;
	display: flex !important;
	flex-direction: column !important;
}
</style>
