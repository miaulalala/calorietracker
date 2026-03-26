<?php

/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\CalorieTracker\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\Security\ICrypto;

class TdeeProfileController extends Controller {
	private const VALID_SEX      = ['male', 'female'];
	private const VALID_ACTIVITY = ['sedentary', 'light', 'moderate', 'very', 'extra'];
	private const VALID_GOAL     = ['lose', 'maintain', 'gain'];

	public function __construct(
		IRequest $request,
		private IConfig $config,
		private ICrypto $crypto,
		private string $userId,
	) {
		parent::__construct('calorietracker', $request);
	}

	#[NoAdminRequired]
	public function get(): JSONResponse {
		$stored = $this->config->getUserValue($this->userId, 'calorietracker', 'tdee_profile', '');
		if ($stored === '') {
			return new JSONResponse(null);
		}
		try {
			$json    = $this->crypto->decrypt($stored, $this->userId);
			$profile = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
			if (!is_array($profile)) {
				return new JSONResponse(null);
			}
		} catch (\Exception $e) {
			return new JSONResponse(null);
		}
		return new JSONResponse($profile);
	}

	#[NoAdminRequired]
	public function save(
		string $sex,
		int|float|string $age,
		int|float|string $height,
		int|float|string $weight,
		string $activity,
		string $goal,
	): JSONResponse {
		if (!is_numeric((string) $age) || !is_numeric((string) $height) || !is_numeric((string) $weight)) {
			return new JSONResponse(['error' => 'Invalid input'], Http::STATUS_BAD_REQUEST);
		}

		$ageVal    = (int) $age;
		$heightVal = (float) $height;
		$weightVal = (float) $weight;

		if (!in_array($sex, self::VALID_SEX, true)
			|| !in_array($activity, self::VALID_ACTIVITY, true)
			|| !in_array($goal, self::VALID_GOAL, true)
			|| $ageVal < 10 || $ageVal > 120
			|| $heightVal < 50.0 || $heightVal > 300.0
			|| $weightVal < 20.0 || $weightVal > 500.0
		) {
			return new JSONResponse(['error' => 'Invalid input'], Http::STATUS_BAD_REQUEST);
		}

		$profile = [
			'sex'      => $sex,
			'age'      => $ageVal,
			'height'   => $heightVal,
			'weight'   => $weightVal,
			'activity' => $activity,
			'goal'     => $goal,
		];
		try {
			$json      = json_encode($profile, JSON_THROW_ON_ERROR);
			$encrypted = $this->crypto->encrypt($json, $this->userId);
		} catch (\Exception $e) {
			return new JSONResponse(['error' => 'Failed to save profile'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		$this->config->setUserValue($this->userId, 'calorietracker', 'tdee_profile', $encrypted);
		return new JSONResponse($profile);
	}
}
