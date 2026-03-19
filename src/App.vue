<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcContent app-name="calorietracker">
		<DaySidebar />
		<NcAppContent>
			<router-view />
		</NcAppContent>
		<NcModal
			v-if="addModalOpen"
			size="full"
			:name="editingEntry ? t('calorietracker', 'Edit entry') : t('calorietracker', 'Add food')"
			@close="$store.dispatch('foodEntries/closeAddModal')">
			<AddFoodEntryForm />
		</NcModal>
		<SettingsView />
	</NcContent>
</template>

<script>
import { mapState } from 'vuex'
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import DaySidebar from './components/DaySidebar.vue'
import AddFoodEntryForm from './components/AddFoodEntryForm.vue'
import SettingsView from './components/SettingsView.vue'

export default {
	name: 'App',
	components: {
		NcContent,
		NcAppContent,
		NcModal,
		DaySidebar,
		AddFoodEntryForm,
		SettingsView,
	},

	computed: {
		...mapState('foodEntries', ['addModalOpen', 'editingEntry']),
	},
}
</script>
