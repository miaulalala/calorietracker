<?php

/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\CalorieTracker\Tests\Unit\Controller;

use OCA\CalorieTracker\Controller\WeightLogController;
use OCA\CalorieTracker\Db\WeightLog;
use OCA\CalorieTracker\Service\WeightLogService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class WeightLogControllerTest extends TestCase {
	private WeightLogService&MockObject $service;
	private WeightLogController $controller;

	protected function setUp(): void {
		$request = $this->createMock(IRequest::class);
		$this->service = $this->createMock(WeightLogService::class);
		$logger = $this->createMock(LoggerInterface::class);
		$this->controller = new WeightLogController($request, $this->service, 'user1', $logger);
	}

	private function makeEntry(int $id = 1, float $weightKg = 72.5, string $date = '2026-05-01'): WeightLog {
		$e = new WeightLog();
		$e->setId($id);
		$e->setUserId('user1');
		$e->setWeightKg($weightKg);
		$e->setLoggedAt($date);
		return $e;
	}

	// ── index (GET /weight-logs) ───────────────────────────────────────────────

	public function testIndexReturnsEntries(): void {
		$entries = [$this->makeEntry(1), $this->makeEntry(2, 73.0, '2026-05-02')];
		$this->service->method('getForDateRange')
			->with('user1', '2026-04-01', '2026-05-01')
			->willReturn($entries);

		$response = $this->controller->index('2026-04-01', '2026-05-01');
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$this->assertCount(2, $response->getData());
	}

	public function testIndexReturns400OnInvalidDate(): void {
		$this->service->method('getForDateRange')
			->willThrowException(new \InvalidArgumentException('Invalid date format.'));

		$response = $this->controller->index('bad-date', '2026-05-01');
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	// ── latest (GET /weight-logs/latest) ──────────────────────────────────────

	public function testLatestReturnsEntry(): void {
		$entry = $this->makeEntry();
		$this->service->method('getLatest')->with('user1')->willReturn($entry);

		$response = $this->controller->latest();
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($entry, $response->getData());
	}

	public function testLatestReturnsNullWhenNoEntry(): void {
		$this->service->method('getLatest')->willReturn(null);

		$response = $this->controller->latest();
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$this->assertNull($response->getData());
	}

	// ── create (POST /weight-logs) ─────────────────────────────────────────────

	public function testCreateReturns201WithEntry(): void {
		$entry = $this->makeEntry();
		$this->service->method('log')
			->with('user1', 72.5, '2026-05-01', null)
			->willReturn($entry);

		$response = $this->controller->create(72.5, '2026-05-01');
		$this->assertEquals(Http::STATUS_CREATED, $response->getStatus());
		$this->assertSame($entry, $response->getData());
	}

	public function testCreatePassesNoteToService(): void {
		$entry = $this->makeEntry();
		$this->service->expects($this->once())
			->method('log')
			->with('user1', 72.5, '2026-05-01', 'morning')
			->willReturn($entry);

		$this->controller->create(72.5, '2026-05-01', 'morning');
	}

	public function testCreateReturns400OnValidationError(): void {
		$this->service->method('log')
			->willThrowException(new \InvalidArgumentException('Weight out of range.'));

		$response = $this->controller->create(5.0, '2026-05-01');
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	// ── delete (DELETE /weight-logs/{id}) ─────────────────────────────────────

	public function testDeleteReturnsEntry(): void {
		$entry = $this->makeEntry();
		$this->service->method('delete')->with(1, 'user1')->willReturn($entry);

		$response = $this->controller->delete(1);
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($entry, $response->getData());
	}

	public function testDeleteReturns404WhenNotFound(): void {
		$this->service->method('delete')->willThrowException(new DoesNotExistException(''));

		$response = $this->controller->delete(999);
		$this->assertEquals(Http::STATUS_NOT_FOUND, $response->getStatus());
	}
}
