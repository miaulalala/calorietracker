<?php

/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\CalorieTracker\Tests\Unit\Controller;

use OCA\CalorieTracker\Controller\UsdaFdcController;
use OCP\AppFramework\Http;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class UsdaFdcControllerTest extends TestCase {
	private IClientService&MockObject $clientService;
	private ICache&MockObject $cache;
	private UsdaFdcController $controller;

	protected function setUp(): void {
		$request = $this->createMock(IRequest::class);
		$this->clientService = $this->createMock(IClientService::class);
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturn('DEMO_KEY');
		$logger = $this->createMock(LoggerInterface::class);
		$this->cache = $this->createMock(ICache::class);
		$cacheFactory = $this->createMock(ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($this->cache);

		$this->controller = new UsdaFdcController(
			$request,
			$this->clientService,
			$config,
			$logger,
			$cacheFactory,
		);
	}

	public function testSearchReturnsEmptyForShortQuery(): void {
		$response = $this->controller->search('a');
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$this->assertEquals([], $response->getData());
	}

	public function testSearchReturnsCachedResult(): void {
		$cached = [['name' => 'Banana', 'source' => 'usda_fdc']];
		$this->cache->method('get')->willReturn($cached);

		$response = $this->controller->search('banana');
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$this->assertEquals($cached, $response->getData());
	}

	public function testSearchParsesApiResponse(): void {
		$this->cache->method('get')->willReturn(null);

		$apiData = [
			'foods' => [[
				'fdcId' => 12345,
				'description' => 'Bananas, raw',
				'dataType' => 'Foundation',
				'foodNutrients' => [
					['nutrientId' => 1008, 'value' => 89.0],
					['nutrientId' => 1003, 'value' => 1.1],
					['nutrientId' => 1005, 'value' => 22.8],
					['nutrientId' => 1004, 'value' => 0.3],
				],
			]],
		];

		$apiResponse = $this->createMock(IResponse::class);
		$apiResponse->method('getBody')->willReturn(json_encode($apiData));
		$client = $this->createMock(IClient::class);
		$client->method('get')->willReturn($apiResponse);
		$this->clientService->method('newClient')->willReturn($client);

		$response = $this->controller->search('banana');
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());

		$data = $response->getData();
		$this->assertCount(1, $data);
		$this->assertEquals('usda_fdc', $data[0]['source']);
		$this->assertEquals('12345', $data[0]['externalId']);
		$this->assertEquals('Bananas, raw', $data[0]['name']);
		$this->assertEquals(89, $data[0]['caloriesPer100g']);
		$this->assertEquals(1, $data[0]['proteinPer100g']);
		$this->assertEquals(23, $data[0]['carbsPer100g']);
		$this->assertEquals(0, $data[0]['fatPer100g']);
	}

	public function testSearchSortsFoundationBeforeBranded(): void {
		$this->cache->method('get')->willReturn(null);

		$apiData = [
			'foods' => [
				[
					'fdcId' => 1,
					'description' => 'Branded banana chips',
					'dataType' => 'Branded',
					'foodNutrients' => [['nutrientId' => 1008, 'value' => 500.0]],
				],
				[
					'fdcId' => 2,
					'description' => 'Bananas, raw',
					'dataType' => 'Foundation',
					'foodNutrients' => [['nutrientId' => 1008, 'value' => 89.0]],
				],
			],
		];

		$apiResponse = $this->createMock(IResponse::class);
		$apiResponse->method('getBody')->willReturn(json_encode($apiData));
		$client = $this->createMock(IClient::class);
		$client->method('get')->willReturn($apiResponse);
		$this->clientService->method('newClient')->willReturn($client);

		$data = $this->controller->search('banana')->getData();
		$this->assertCount(2, $data);
		$this->assertEquals('Bananas, raw', $data[0]['name']);
		$this->assertEquals('Branded banana chips', $data[1]['name']);
	}

	public function testSearchSkipsFoodsWithoutCalories(): void {
		$this->cache->method('get')->willReturn(null);

		$apiData = [
			'foods' => [[
				'fdcId' => 1,
				'description' => 'No calorie data',
				'dataType' => 'Foundation',
				'foodNutrients' => [['nutrientId' => 1003, 'value' => 5.0]],
			]],
		];

		$apiResponse = $this->createMock(IResponse::class);
		$apiResponse->method('getBody')->willReturn(json_encode($apiData));
		$client = $this->createMock(IClient::class);
		$client->method('get')->willReturn($apiResponse);
		$this->clientService->method('newClient')->willReturn($client);

		$data = $this->controller->search('test')->getData();
		$this->assertCount(0, $data);
	}

	public function testSearchReturns502OnInvalidJson(): void {
		$this->cache->method('get')->willReturn(null);

		$apiResponse = $this->createMock(IResponse::class);
		$apiResponse->method('getBody')->willReturn('not json');
		$client = $this->createMock(IClient::class);
		$client->method('get')->willReturn($apiResponse);
		$this->clientService->method('newClient')->willReturn($client);

		$response = $this->controller->search('banana');
		$this->assertEquals(Http::STATUS_BAD_GATEWAY, $response->getStatus());
	}

	public function testSearchReturns502OnApiFailure(): void {
		$this->cache->method('get')->willReturn(null);
		$this->clientService->method('newClient')
			->willThrowException(new \RuntimeException('Network error'));

		$response = $this->controller->search('banana');
		$this->assertEquals(Http::STATUS_BAD_GATEWAY, $response->getStatus());
	}

	public function testSearchCachesResults(): void {
		$this->cache->method('get')->willReturn(null);
		$this->cache->expects($this->once())->method('set');

		$apiResponse = $this->createMock(IResponse::class);
		$apiResponse->method('getBody')->willReturn(json_encode(['foods' => []]));
		$client = $this->createMock(IClient::class);
		$client->method('get')->willReturn($apiResponse);
		$this->clientService->method('newClient')->willReturn($client);

		$this->controller->search('test');
	}
}
