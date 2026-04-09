<?php

/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\CalorieTracker\Tests\Unit\Controller;

use OCA\CalorieTracker\Controller\FoodEntryController;
use OCA\CalorieTracker\Db\FoodEntry;
use OCA\CalorieTracker\Service\FoodEntryService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class FoodEntryControllerTest extends TestCase {
	private FoodEntryService&MockObject $service;
	private FoodEntryController $controller;

	protected function setUp(): void {
		$request = $this->createMock(IRequest::class);
		$this->service = $this->createMock(FoodEntryService::class);
		$logger = $this->createMock(LoggerInterface::class);
		$this->controller = new FoodEntryController($request, $this->service, 'user1', $logger);
	}

	private function makeEntry(int $id = 1): FoodEntry {
		$e = new FoodEntry();
		$e->setId($id);
		$e->setFoodName('Banana');
		$e->setCaloriesPer100g(89);
		$e->setAmountGrams(120);
		$e->setMealType('breakfast');
		$e->setEatenAt('2026-04-01');
		return $e;
	}

	// ── show (GET /entries/{id}) ───────────────────────────────────────────────

	public function testShowReturnsEntry(): void {
		$entry = $this->makeEntry();
		$this->service->method('find')->with(1, 'user1')->willReturn($entry);

		$response = $this->controller->show(1);
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($entry, $response->getData());
	}

	public function testShowReturns404WhenNotFound(): void {
		$this->service->method('find')->willThrowException(new DoesNotExistException(''));

		$response = $this->controller->show(999);
		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	// ── patch (PATCH /entries/{id}) ────────────────────────────────────────────

	public function testPatchReturnsUpdatedEntry(): void {
		$entry = $this->makeEntry();
		$this->service->method('patch')->willReturn($entry);

		$response = $this->controller->patch(1);
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($entry, $response->getData());
	}

	public function testPatchReturns404WhenNotFound(): void {
		$this->service->method('patch')->willThrowException(new DoesNotExistException(''));

		$response = $this->controller->patch(999);
		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testPatchReturns400OnValidationError(): void {
		$this->service->method('patch')->willThrowException(new \InvalidArgumentException('bad'));

		$response = $this->controller->patch(1);
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	// ── batch (POST /entries/batch) ────────────────────────────────────────────

	public function testBatchReturnsCreatedEntries(): void {
		$entries = [$this->makeEntry(10), $this->makeEntry(11)];
		$this->service->method('batchCopy')->willReturn($entries);

		$response = $this->controller->batch('2026-04-01', '2026-04-02');
		$this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
		$this->assertCount(2, $response->getData());
	}

	public function testBatchPassesMealTypeFilter(): void {
		$this->service->expects($this->once())
			->method('batchCopy')
			->with('user1', '2026-04-01', '2026-04-02', 'lunch')
			->willReturn([$this->makeEntry()]);

		$this->controller->batch('2026-04-01', '2026-04-02', 'lunch');
	}

	public function testBatchReturns400OnValidationError(): void {
		$this->service->method('batchCopy')->willThrowException(new \InvalidArgumentException('No entries'));

		$response = $this->controller->batch('2026-04-01', '2026-04-02');
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}
}
