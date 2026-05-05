<?php

/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\CalorieTracker\Controller;

use OCA\CalorieTracker\Service\WeightLogService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class WeightLogController extends Controller {
	public function __construct(
		IRequest $request,
		private WeightLogService $service,
		private string $userId,
		private LoggerInterface $logger,
	) {
		parent::__construct('calorietracker', $request);
	}

	#[NoAdminRequired]
	public function index(string $from, string $to): JSONResponse {
		try {
			return new JSONResponse($this->service->getForDateRange($this->userId, $from, $to));
		} catch (\InvalidArgumentException $e) {
			return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	public function latest(): JSONResponse {
		$entry = $this->service->getLatest($this->userId);
		return new JSONResponse($entry);
	}

	#[NoAdminRequired]
	public function create(float $weightKg, string $loggedAt, ?string $note = null): JSONResponse {
		try {
			$entry = $this->service->log($this->userId, $weightKg, $loggedAt, $note);
			return new JSONResponse($entry, Http::STATUS_CREATED);
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
			$this->logger->info('Weight log {id} not found for user during delete', ['id' => $id, 'app' => 'calorietracker']);
			return new JSONResponse(['error' => 'Not found'], Http::STATUS_NOT_FOUND);
		}
	}
}
