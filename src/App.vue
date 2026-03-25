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
			size="full"
			:name="editingEntry ? t('calorietracker', 'Edit entry') : t('calorietracker', 'Add food')"
			@close="store.closeAddModal()">
			<AddFoodEntryForm />
		</NcModal>
		<SettingsView />
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
import { useFoodEntriesStore } from './stores/foodEntries.js'

const store = useFoodEntriesStore()
const { addModalOpen, editingEntry } = storeToRefs(store)
</script>
