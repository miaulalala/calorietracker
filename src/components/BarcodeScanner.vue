<!--
  - SPDX-FileCopyrightText: 2026 Anna Larch
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="barcode-scanner">
		<!-- Camera scanning state -->
		<template v-if="scanning">
			<div class="barcode-scanner__viewport">
				<video ref="videoEl"
					class="barcode-scanner__video"
					muted
					playsinline
					aria-hidden="true" />
				<div class="barcode-scanner__overlay" aria-hidden="true">
					<div class="barcode-scanner__viewfinder" />
				</div>
			</div>
			<p class="barcode-scanner__hint">
				{{ t('calorietracker', 'Point the camera at a product barcode') }}
			</p>
			<NcButton type="tertiary" @click="stopScanner">
				{{ t('calorietracker', 'Stop camera') }}
			</NcButton>
		</template>

		<!-- Idle state -->
		<template v-else>
			<div class="barcode-scanner__start">
				<NcButton type="secondary"
					:disabled="cameraUnavailable"
					@click="startScanner">
					<template #icon>
						<svg xmlns="http://www.w3.org/2000/svg"
							viewBox="0 0 24 24"
							fill="none"
							stroke="currentColor"
							stroke-width="2"
							stroke-linecap="round"
							stroke-linejoin="round"
							width="20"
							height="20"
							aria-hidden="true">
							<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z" />
							<circle cx="12" cy="13" r="4" />
						</svg>
					</template>
					{{ t('calorietracker', 'Scan with camera') }}
				</NcButton>
				<p v-if="cameraUnavailable" class="barcode-scanner__camera-error">
					{{ t('calorietracker', 'Camera not available. Please enter the barcode manually.') }}
				</p>
			</div>

			<div class="barcode-scanner__divider" aria-hidden="true">
				<span>{{ t('calorietracker', 'or') }}</span>
			</div>

			<div class="barcode-scanner__manual">
				<label class="barcode-scanner__manual-label" for="barcode-manual-input">
					{{ t('calorietracker', 'Enter barcode') }}
				</label>
				<div class="barcode-scanner__manual-row">
					<input id="barcode-manual-input"
						v-model="manualBarcode"
						class="barcode-scanner__manual-input"
						type="text"
						inputmode="numeric"
						:placeholder="t('calorietracker', 'e.g. 5449000000996')"
						@keydown.enter.prevent="submitManual">
					<NcButton type="primary"
						:disabled="!isValidBarcode"
						@click="submitManual">
						{{ t('calorietracker', 'Look up') }}
					</NcButton>
				</div>
			</div>
		</template>
	</div>
</template>

<script setup>
import { ref, computed, onUnmounted } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'

const emit = defineEmits(['result'])

const videoEl = ref(null)
const scanning = ref(false)
const manualBarcode = ref('')
const cameraUnavailable = ref(false)

let scanControls = null

const isValidBarcode = computed(() => /^\d{8,14}$/.test(manualBarcode.value.trim()))

async function startScanner() {
	cameraUnavailable.value = false
	scanning.value = true

	// Wait for video element to mount
	await new Promise(resolve => setTimeout(resolve, 50))

	try {
		const { BrowserMultiFormatReader } = await import('@zxing/browser')
		const reader = new BrowserMultiFormatReader()

		scanControls = await reader.decodeFromVideoDevice(
			undefined, // default camera
			videoEl.value,
			(result, err) => {
				if (result) {
					const code = result.getText()
					stopScanner()
					emit('result', code)
				}
			},
		)
	} catch (e) {
		scanning.value = false
		cameraUnavailable.value = true
	}
}

function stopScanner() {
	if (scanControls) {
		scanControls.stop()
		scanControls = null
	}
	scanning.value = false
}

function submitManual() {
	const code = manualBarcode.value.trim()
	if (!isValidBarcode.value) return
	manualBarcode.value = ''
	emit('result', code)
}

onUnmounted(() => {
	stopScanner()
})
</script>

<style scoped>
.barcode-scanner {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 16px;
	padding: 8px 0;
}

.barcode-scanner__viewport {
	position: relative;
	width: 100%;
	max-width: 400px;
	aspect-ratio: 4 / 3;
	background: #000;
	border-radius: var(--border-radius-large);
	overflow: hidden;
}

.barcode-scanner__video {
	width: 100%;
	height: 100%;
	object-fit: cover;
}

.barcode-scanner__overlay {
	position: absolute;
	inset: 0;
	display: flex;
	align-items: center;
	justify-content: center;
}

.barcode-scanner__viewfinder {
	width: 70%;
	height: 40%;
	border: 2px solid var(--color-primary-element);
	border-radius: 4px;
	box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.45);
}

.barcode-scanner__hint {
	font-size: 0.85em;
	color: var(--color-text-maxcontrast);
	margin: 0;
}

.barcode-scanner__start {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 8px;
}

.barcode-scanner__camera-error {
	font-size: 0.85em;
	color: var(--color-error);
	margin: 0;
	text-align: center;
}

.barcode-scanner__divider {
	display: flex;
	align-items: center;
	width: 100%;
	gap: 12px;
	color: var(--color-text-maxcontrast);
	font-size: 0.85em;
}

.barcode-scanner__divider::before,
.barcode-scanner__divider::after {
	content: '';
	flex: 1;
	height: 1px;
	background: var(--color-border);
}

.barcode-scanner__manual {
	width: 100%;
	display: flex;
	flex-direction: column;
	gap: 6px;
}

.barcode-scanner__manual-label {
	font-size: 0.9em;
	color: var(--color-text-maxcontrast);
}

.barcode-scanner__manual-row {
	display: flex;
	gap: 8px;
}

.barcode-scanner__manual-input {
	flex: 1;
	height: 34px;
	padding: 0 8px;
	border: 1px solid var(--color-border-dark);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	color: var(--color-main-text);
	font-size: 1em;
}
</style>
