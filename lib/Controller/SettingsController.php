<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\CalorieTracker\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IRequest;

class SettingsController extends Controller {
	public function __construct(
		IRequest $request,
		private IConfig $config,
		private string $userId,
	) {
		parent::__construct('calorietracker', $request);
	}

	#[NoAdminRequired]
	public function get(): JSONResponse {
		return new JSONResponse([
			'dailyCalorieGoal' => (int) $this->config->getUserValue($this->userId, 'calorietracker', 'dailyCalorieGoal', '0'),
			'dailyProteinGoal' => (int) $this->config->getUserValue($this->userId, 'calorietracker', 'dailyProteinGoal', '0'),
			'dailyCarbsGoal'   => (int) $this->config->getUserValue($this->userId, 'calorietracker', 'dailyCarbsGoal', '0'),
			'dailyFatGoal'     => (int) $this->config->getUserValue($this->userId, 'calorietracker', 'dailyFatGoal', '0'),
		]);
	}

	#[NoAdminRequired]
	public function save(
		int $dailyCalorieGoal = 0,
		?int $dailyProteinGoal = null,
		?int $dailyCarbsGoal = null,
		?int $dailyFatGoal = null,
	): JSONResponse {
		$this->config->setUserValue($this->userId, 'calorietracker', 'dailyCalorieGoal', (string) max(0, $dailyCalorieGoal));

		// Only update macro goals when explicitly provided; otherwise preserve the stored values.
		if ($dailyProteinGoal !== null) {
			$this->config->setUserValue($this->userId, 'calorietracker', 'dailyProteinGoal', (string) max(0, $dailyProteinGoal));
		}
		if ($dailyCarbsGoal !== null) {
			$this->config->setUserValue($this->userId, 'calorietracker', 'dailyCarbsGoal', (string) max(0, $dailyCarbsGoal));
		}
		if ($dailyFatGoal !== null) {
			$this->config->setUserValue($this->userId, 'calorietracker', 'dailyFatGoal', (string) max(0, $dailyFatGoal));
		}

		return new JSONResponse([
			'dailyCalorieGoal' => max(0, $dailyCalorieGoal),
			'dailyProteinGoal' => (int) $this->config->getUserValue($this->userId, 'calorietracker', 'dailyProteinGoal', '0'),
			'dailyCarbsGoal'   => (int) $this->config->getUserValue($this->userId, 'calorietracker', 'dailyCarbsGoal', '0'),
			'dailyFatGoal'     => (int) $this->config->getUserValue($this->userId, 'calorietracker', 'dailyFatGoal', '0'),
		]);
	}
}
