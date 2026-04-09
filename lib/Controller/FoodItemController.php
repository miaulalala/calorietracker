<?php

/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\CalorieTracker\Controller;

use OCA\CalorieTracker\Db\FoodItemMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class FoodItemController extends Controller {
	private const VALID_SORTS = ['recent', 'frequent'];
	private const MAX_LIMIT = 50;
	private const DEFAULT_LIMIT = 25;

	public function __construct(
		IRequest $request,
		private FoodItemMapper $mapper,
		private string $userId,
	) {
		parent::__construct('calorietracker', $request);
	}

	#[NoAdminRequired]
	public function index(string $sort = 'recent', int $limit = self::DEFAULT_LIMIT): JSONResponse {
		if (!in_array($sort, self::VALID_SORTS, true)) {
			return new JSONResponse(
				['error' => 'Invalid sort. Must be one of: ' . implode(', ', self::VALID_SORTS)],
				Http::STATUS_BAD_REQUEST,
			);
		}
		$limit = max(1, min($limit, self::MAX_LIMIT));

		$items = match ($sort) {
			'recent' => $this->mapper->findRecentForUser($this->userId, $limit),
			'frequent' => $this->mapper->findFrequentForUser($this->userId, $limit),
		};

		return new JSONResponse($items);
	}
}
