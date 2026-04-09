<?php

/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\CalorieTracker\Tests\Unit\Controller;

use OCA\CalorieTracker\Controller\OpenFoodFactsController;
use OCP\AppFramework\Http;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class OpenFoodFactsControllerTest extends TestCase {
	private IClientService&MockObject $clientService;
	private ICache&MockObject $cache;
	private OpenFoodFactsController $controller;

	protected function setUp(): void {
		$request = $this->createMock(IRequest::class);
		$this->clientService = $this->createMock(IClientService::class);
		$logger = $this->createMock(LoggerInterface::class);
		$this->cache = $this->createMock(ICache::class);
		$cacheFactory = $this->createMock(ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($this->cache);

		$this->controller = new OpenFoodFactsController(
			$request,
			$this->clientService,
			$logger,
			$cacheFactory,
		);
	}

	// ── barcode() ──────────────────────────────────────────────────────────────

	public function testBarcodeRejectsTooShort(): void {
		$response = $this->controller->barcode('123');
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testBarcodeRejectsNonNumeric(): void {
		$response = $this->controller->barcode('ABCD12345678');
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testBarcodeReturnsCachedResult(): void {
		$cached = ['source' => 'usda_fdc', 'name' => 'Cached Item'];
		$this->cache->method('get')->with('fdcbarcode:0041220080014')->willReturn($cached);

		$response = $this->controller->barcode('0041220080014');
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$this->assertEquals($cached, $response->getData());
	}

	public function testBarcodeReturnsEmptyWhenNoMatch(): void {
		$this->cache->method('get')->willReturn(null);

		$apiResponse = $this->createMock(IResponse::class);
		$apiResponse->method('getBody')->willReturn(json_encode(['foods' => []]));

		$client = $this->createMock(IClient::class);
		$client->method('get')->willReturn($apiResponse);
		$this->clientService->method('newClient')->willReturn($client);

		$this->cache->expects($this->once())->method('set');

		$response = $this->controller->barcode('0041220080014');
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$this->assertEquals([], $response->getData());
	}

	public function testBarcodeReturnsMatchingFood(): void {
		$this->cache->method('get')->willReturn(null);

		$foodData = [
			'foods' => [[
				'fdcId' => 12345,
				'description' => 'Test Food',
				'gtinUpc' => '0041220080014',
				'foodNutrients' => [
					['nutrientId' => 1008, 'value' => 250.0],
					['nutrientId' => 1003, 'value' => 10.0],
					['nutrientId' => 1005, 'value' => 30.0],
					['nutrientId' => 1004, 'value' => 12.0],
				],
			]],
		];

		$apiResponse = $this->createMock(IResponse::class);
		$apiResponse->method('getBody')->willReturn(json_encode($foodData));
		$client = $this->createMock(IClient::class);
		$client->method('get')->willReturn($apiResponse);
		$this->clientService->method('newClient')->willReturn($client);

		$response = $this->controller->barcode('0041220080014');
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());

		$data = $response->getData();
		$this->assertEquals('usda_fdc', $data['source']);
		$this->assertEquals('12345', $data['externalId']);
		$this->assertEquals('Test Food', $data['name']);
		$this->assertEquals(250, $data['caloriesPer100g']);
		$this->assertEquals(10, $data['proteinPer100g']);
		$this->assertEquals(30, $data['carbsPer100g']);
		$this->assertEquals(12, $data['fatPer100g']);
		$this->assertEquals('0041220080014', $data['barcode']);
	}

	public function testBarcodeMatchesWithLeadingZeroDifference(): void {
		$this->cache->method('get')->willReturn(null);

		$foodData = [
			'foods' => [[
				'fdcId' => 99,
				'description' => 'Zero-padded',
				'gtinUpc' => '041220080014',
				'foodNutrients' => [
					['nutrientId' => 1008, 'value' => 100.0],
				],
			]],
		];

		$apiResponse = $this->createMock(IResponse::class);
		$apiResponse->method('getBody')->willReturn(json_encode($foodData));
		$client = $this->createMock(IClient::class);
		$client->method('get')->willReturn($apiResponse);
		$this->clientService->method('newClient')->willReturn($client);

		$response = $this->controller->barcode('0041220080014');
		$data = $response->getData();
		$this->assertEquals('Zero-padded', $data['name']);
	}

	public function testBarcodeReturns502OnApiFailure(): void {
		$this->cache->method('get')->willReturn(null);
		$this->clientService->method('newClient')
			->willThrowException(new \RuntimeException('Network error'));

		$response = $this->controller->barcode('0041220080014');
		$this->assertEquals(Http::STATUS_BAD_GATEWAY, $response->getStatus());
	}
}
