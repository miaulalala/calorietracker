<?php

/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\CalorieTracker\Controller;

use OCA\CalorieTracker\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class ApiController extends Controller {
	private const API_VERSION = 1;

	public function __construct(
		IRequest $request,
		private string $appVersion,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	public function capabilities(): JSONResponse {
		return new JSONResponse([
			'appVersion' => $this->appVersion,
			'apiVersion' => self::API_VERSION,
			'features' => [
				'entries',
				'summaries',
				'foodSearch',
				'barcodeLookup',
				'foodItems',
				'batchCopy',
				'settings',
			],
		]);
	}
}
