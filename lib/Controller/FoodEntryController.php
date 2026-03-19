<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\CalorieTracker\Controller;

use OCA\CalorieTracker\Service\FoodEntryService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class FoodEntryController extends Controller {
	public function __construct(
		IRequest $request,
		private FoodEntryService $service,
		private string $userId,
	) {
		parent::__construct('calorietracker', $request);
	}

	#[NoAdminRequired]
	public function index(string $date): JSONResponse {
		try {
			return new JSONResponse($this->service->findAllForDay($this->userId, $date));
		} catch (\InvalidArgumentException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	public function create(
		string $foodName,
		int $caloriesPer100g,
		int $amountGrams,
		string $mealType,
		string $eatenAt,
		?int $proteinPer100g = null,
		?int $carbsPer100g = null,
		?int $fatPer100g = null,
	): JSONResponse {
		try {
			$entry = $this->service->create(
				$this->userId,
				$foodName,
				$caloriesPer100g,
				$amountGrams,
				$mealType,
				$eatenAt,
				$proteinPer100g,
				$carbsPer100g,
				$fatPer100g,
			);
			return new JSONResponse($entry, Http::STATUS_CREATED);
		} catch (\InvalidArgumentException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	public function update(
		int $id,
		string $foodName,
		int $caloriesPer100g,
		int $amountGrams,
		string $mealType,
		string $eatenAt,
		?int $proteinPer100g = null,
		?int $carbsPer100g = null,
		?int $fatPer100g = null,
	): JSONResponse {
		try {
			$entry = $this->service->update(
				$id,
				$this->userId,
				$foodName,
				$caloriesPer100g,
				$amountGrams,
				$mealType,
				$eatenAt,
				$proteinPer100g,
				$carbsPer100g,
				$fatPer100g,
			);
			return new JSONResponse($entry);
		} catch (DoesNotExistException) {
			return new JSONResponse(['error' => 'Not found'], Http::STATUS_NOT_FOUND);
		} catch (\InvalidArgumentException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	public function delete(int $id): JSONResponse {
		try {
			$entry = $this->service->delete($id, $this->userId);
			return new JSONResponse($entry);
		} catch (DoesNotExistException) {
			return new JSONResponse(['error' => 'Not found'], Http::STATUS_NOT_FOUND);
		}
	}

	#[NoAdminRequired]
	public function summary(string $from, string $to): JSONResponse {
		try {
			return new JSONResponse($this->service->getDailySummaries($this->userId, $from, $to));
		} catch (\InvalidArgumentException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}
}
