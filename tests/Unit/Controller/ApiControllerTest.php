<?php

/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\CalorieTracker\Tests\Unit\Controller;

use OCA\CalorieTracker\Controller\ApiController;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

class ApiControllerTest extends TestCase {
	private ApiController $controller;

	protected function setUp(): void {
		$request = $this->createMock(IRequest::class);
		$this->controller = new ApiController($request, '0.3.0');
	}

	public function testCapabilitiesReturnsExpectedShape(): void {
		$response = $this->controller->capabilities();
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());

		$data = $response->getData();
		$this->assertEquals('0.3.0', $data['appVersion']);
		$this->assertEquals(1, $data['apiVersion']);
		$this->assertIsArray($data['features']);
	}

	public function testCapabilitiesIncludesAllFeatures(): void {
		$data = $this->controller->capabilities()->getData();

		$expected = ['entries', 'summaries', 'foodSearch', 'barcodeLookup', 'foodItems', 'batchCopy', 'settings'];
		$this->assertEquals($expected, $data['features']);
	}
}
