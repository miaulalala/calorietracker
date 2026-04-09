<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\CalorieTracker\AppInfo;

use OCA\CalorieTracker\Controller\ApiController;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\IAppConfig;

class Application extends App implements IBootstrap {
	public const APP_ID = 'calorietracker';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerService(ApiController::class, function ($c) {
			$appConfig = $c->get(IAppConfig::class);
			return new ApiController(
				$c->get(\OCP\IRequest::class),
				$appConfig->getValueString(self::APP_ID, 'installed_version', '0.0.0'),
			);
		});
	}

	public function boot(IBootContext $context): void {
	}
}
