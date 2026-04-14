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

	private const VALID_ENERGY_UNITS = ['kcal', 'kj'];
	private const VALID_MEASUREMENT_SYSTEMS = ['metric', 'imperial'];

	#[NoAdminRequired]
	public function get(): JSONResponse {
		$energyUnit = $this->config->getUserValue($this->userId, 'calorietracker', 'energyUnit', 'kcal');
		if (!in_array($energyUnit, self::VALID_ENERGY_UNITS, true)) {
			$energyUnit = 'kcal';
		}
		$measurementSystem = $this->config->getUserValue($this->userId, 'calorietracker', 'measurementSystem', 'metric');
		if (!in_array($measurementSystem, self::VALID_MEASUREMENT_SYSTEMS, true)) {
			$measurementSystem = 'metric';
		}

		return new JSONResponse([
			'dailyCalorieGoal'     => (int) $this->config->getUserValue($this->userId, 'calorietracker', 'dailyCalorieGoal', '0'),
			'dailyProteinGoal'     => (int) $this->config->getUserValue($this->userId, 'calorietracker', 'dailyProteinGoal', '0'),
			'dailyCarbsGoal'       => (int) $this->config->getUserValue($this->userId, 'calorietracker', 'dailyCarbsGoal', '0'),
			'dailyFatGoal'         => (int) $this->config->getUserValue($this->userId, 'calorietracker', 'dailyFatGoal', '0'),
			'energyUnit'           => $energyUnit,
			'measurementSystem'    => $measurementSystem,
			'showWeightOnDayView'  => $this->config->getUserValue($this->userId, 'calorietracker', 'showWeightOnDayView', '0') === '1',
		]);
	}

	#[NoAdminRequired]
	public function save(
		int $dailyCalorieGoal = 0,
		?int $dailyProteinGoal = null,
		?int $dailyCarbsGoal = null,
		?int $dailyFatGoal = null,
		?string $energyUnit = null,
		?string $measurementSystem = null,
		?bool $showWeightOnDayView = null,
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

		// Only update unit preferences when explicitly provided; otherwise preserve stored values.
		if ($energyUnit !== null && in_array($energyUnit, self::VALID_ENERGY_UNITS, true)) {
			$this->config->setUserValue($this->userId, 'calorietracker', 'energyUnit', $energyUnit);
		}
		if ($measurementSystem !== null && in_array($measurementSystem, self::VALID_MEASUREMENT_SYSTEMS, true)) {
			$this->config->setUserValue($this->userId, 'calorietracker', 'measurementSystem', $measurementSystem);
		}
		if ($showWeightOnDayView !== null) {
			$this->config->setUserValue($this->userId, 'calorietracker', 'showWeightOnDayView', $showWeightOnDayView ? '1' : '0');
		}

		$storedEnergyUnit = $this->config->getUserValue($this->userId, 'calorietracker', 'energyUnit', 'kcal');
		if (!in_array($storedEnergyUnit, self::VALID_ENERGY_UNITS, true)) {
			$storedEnergyUnit = 'kcal';
		}
		$storedMeasurementSystem = $this->config->getUserValue($this->userId, 'calorietracker', 'measurementSystem', 'metric');
		if (!in_array($storedMeasurementSystem, self::VALID_MEASUREMENT_SYSTEMS, true)) {
			$storedMeasurementSystem = 'metric';
		}

		return new JSONResponse([
			'dailyCalorieGoal'     => max(0, $dailyCalorieGoal),
			'dailyProteinGoal'     => (int) $this->config->getUserValue($this->userId, 'calorietracker', 'dailyProteinGoal', '0'),
			'dailyCarbsGoal'       => (int) $this->config->getUserValue($this->userId, 'calorietracker', 'dailyCarbsGoal', '0'),
			'dailyFatGoal'         => (int) $this->config->getUserValue($this->userId, 'calorietracker', 'dailyFatGoal', '0'),
			'energyUnit'           => $storedEnergyUnit,
			'measurementSystem'    => $storedMeasurementSystem,
			'showWeightOnDayView'  => $this->config->getUserValue($this->userId, 'calorietracker', 'showWeightOnDayView', '0') === '1',
		]);
	}
}
