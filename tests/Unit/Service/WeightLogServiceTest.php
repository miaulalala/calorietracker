<?php

/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\CalorieTracker\Tests\Unit\Service;

use OCA\CalorieTracker\Db\WeightLog;
use OCA\CalorieTracker\Db\WeightLogMapper;
use OCA\CalorieTracker\Service\WeightLogService;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class WeightLogServiceTest extends TestCase {
	private WeightLogMapper&MockObject $mapper;
	private WeightLogService $service;

	protected function setUp(): void {
		$this->mapper = $this->createMock(WeightLogMapper::class);
		$this->service = new WeightLogService($this->mapper);
	}

	private function makeEntry(int $id = 1, float $weightKg = 72.5, string $date = '2026-05-01'): WeightLog {
		$e = new WeightLog();
		$e->setId($id);
		$e->setUserId('user1');
		$e->setWeightKg($weightKg);
		$e->setLoggedAt($date);
		return $e;
	}

	// ── getLatest() ────────────────────────────────────────────────────────────

	public function testGetLatestReturnsEntry(): void {
		$entry = $this->makeEntry();
		$this->mapper->method('findLatestForUser')->with('user1')->willReturn($entry);

		$result = $this->service->getLatest('user1');
		$this->assertSame($entry, $result);
	}

	public function testGetLatestReturnsNullWhenNone(): void {
		$this->mapper->method('findLatestForUser')->willReturn(null);
		$this->assertNull($this->service->getLatest('user1'));
	}

	// ── getForDateRange() ──────────────────────────────────────────────────────

	public function testGetForDateRangeReturnsMappedEntries(): void {
		$entries = [$this->makeEntry(1), $this->makeEntry(2, 73.0, '2026-05-02')];
		$this->mapper->method('findForUserInRange')
			->with('user1', '2026-04-01', '2026-05-01')
			->willReturn($entries);

		$result = $this->service->getForDateRange('user1', '2026-04-01', '2026-05-01');
		$this->assertCount(2, $result);
	}

	public function testGetForDateRangeThrowsOnInvalidFromDate(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service->getForDateRange('user1', 'not-a-date', '2026-05-01');
	}

	public function testGetForDateRangeThrowsOnInvalidToDate(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service->getForDateRange('user1', '2026-04-01', '2026/05/01');
	}

	public function testGetForDateRangeThrowsOnNonExistentDate(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service->getForDateRange('user1', '2026-02-30', '2026-05-01');
	}

	// ── log() ──────────────────────────────────────────────────────────────────

	public function testLogInsertsNewEntryWhenNoneExists(): void {
		$entry = $this->makeEntry();
		$this->mapper->method('findForUserOnDate')->willReturn(null);
		$this->mapper->expects($this->once())->method('insert')->willReturn($entry);
		$this->mapper->expects($this->never())->method('update');

		$result = $this->service->log('user1', 72.5, '2026-05-01');
		$this->assertSame($entry, $result);
	}

	public function testLogUpdatesExistingEntryForSameDate(): void {
		$existing = $this->makeEntry(1, 70.0);
		$this->mapper->method('findForUserOnDate')->willReturn($existing);
		$this->mapper->expects($this->once())->method('update')->willReturnArgument(0);
		$this->mapper->expects($this->never())->method('insert');

		$result = $this->service->log('user1', 75.0, '2026-05-01');
		$this->assertEquals(75.0, $result->getWeightKg());
	}

	public function testLogRoundsWeightToOneDecimal(): void {
		$this->mapper->method('findForUserOnDate')->willReturn(null);
		$this->mapper->method('insert')->willReturnArgument(0);

		$result = $this->service->log('user1', 72.456, '2026-05-01');
		$this->assertEquals(72.5, $result->getWeightKg());
	}

	public function testLogTruncatesNote(): void {
		$this->mapper->method('findForUserOnDate')->willReturn(null);
		$this->mapper->method('insert')->willReturnArgument(0);

		$longNote = str_repeat('a', 300);
		$result = $this->service->log('user1', 72.5, '2026-05-01', $longNote);
		$this->assertEquals(255, mb_strlen($result->getNote()));
	}

	public function testLogSetsNoteToNullWhenBlank(): void {
		$this->mapper->method('findForUserOnDate')->willReturn(null);
		$this->mapper->method('insert')->willReturnArgument(0);

		$result = $this->service->log('user1', 72.5, '2026-05-01', '   ');
		$this->assertNull($result->getNote());
	}

	public function testLogThrowsOnInvalidDate(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service->log('user1', 72.5, 'bad-date');
	}

	public function testLogThrowsWhenWeightTooLow(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service->log('user1', 10.0, '2026-05-01');
	}

	public function testLogThrowsWhenWeightTooHigh(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service->log('user1', 600.0, '2026-05-01');
	}

	public function testLogAcceptsBoundaryWeights(): void {
		$this->mapper->method('findForUserOnDate')->willReturn(null);
		$this->mapper->method('insert')->willReturnArgument(0);

		$low = $this->service->log('user1', 20.0, '2026-05-01');
		$this->assertEquals(20.0, $low->getWeightKg());

		$high = $this->service->log('user1', 500.0, '2026-05-02');
		$this->assertEquals(500.0, $high->getWeightKg());
	}

	// ── delete() ───────────────────────────────────────────────────────────────

	public function testDeleteRemovesEntry(): void {
		$entry = $this->makeEntry();
		$this->mapper->method('findForUser')->with(1, 'user1')->willReturn($entry);
		$this->mapper->expects($this->once())->method('delete')->with($entry)->willReturn($entry);

		$result = $this->service->delete(1, 'user1');
		$this->assertSame($entry, $result);
	}

	public function testDeleteThrowsWhenNotFound(): void {
		$this->mapper->method('findForUser')->willThrowException(new DoesNotExistException(''));

		$this->expectException(DoesNotExistException::class);
		$this->service->delete(999, 'user1');
	}
}
