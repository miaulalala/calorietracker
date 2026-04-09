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
use Psr\Log\LoggerInterface;

class FoodEntryController extends Controller {
	public function __construct(
		IRequest $request,
		private FoodEntryService $service,
		private string $userId,
		private LoggerInterface $logger,
	) {
		parent::__construct('calorietracker', $request);
	}

	#[NoAdminRequired]
	public function show(int $id): JSONResponse {
		try {
			return new JSONResponse($this->service->find($id, $this->userId));
		} catch (DoesNotExistException) {
			return new JSONResponse(['error' => 'Not found'], Http::STATUS_NOT_FOUND);
		}
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
		?string $source = null,
		?string $externalId = null,
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
				$source,
				$externalId,
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
			$this->logger->info('Food entry {id} not found for user during update', ['id' => $id, 'app' => 'calorietracker']);
			return new JSONResponse(['error' => 'Not found'], Http::STATUS_NOT_FOUND);
		} catch (\InvalidArgumentException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	public function patch(
		int $id,
		?string $foodName = null,
		?int $caloriesPer100g = null,
		?int $amountGrams = null,
		?string $mealType = null,
		?string $eatenAt = null,
		?int $proteinPer100g = null,
		?int $carbsPer100g = null,
		?int $fatPer100g = null,
	): JSONResponse {
		try {
			$entry = $this->service->patch(
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
			$this->logger->info('Food entry {id} not found for user during delete', ['id' => $id, 'app' => 'calorietracker']);
			return new JSONResponse(['error' => 'Not found'], Http::STATUS_NOT_FOUND);
		}
	}

	#[NoAdminRequired]
	public function batch(string $fromDate, string $toDate, ?string $mealType = null): JSONResponse {
		try {
			$entries = $this->service->batchCopy($this->userId, $fromDate, $toDate, $mealType);
			return new JSONResponse($entries, Http::STATUS_CREATED);
		} catch (\InvalidArgumentException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
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
