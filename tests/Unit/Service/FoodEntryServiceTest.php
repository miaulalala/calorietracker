<?php

/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\CalorieTracker\Tests\Unit\Service;

use OCA\CalorieTracker\Db\FoodEntry;
use OCA\CalorieTracker\Db\FoodEntryMapper;
use OCA\CalorieTracker\Db\FoodItemMapper;
use OCA\CalorieTracker\Service\FoodEntryService;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FoodEntryServiceTest extends TestCase {
	private FoodEntryMapper&MockObject $mapper;
	private FoodItemMapper&MockObject $foodItemMapper;
	private FoodEntryService $service;

	protected function setUp(): void {
		$this->mapper = $this->createMock(FoodEntryMapper::class);
		$this->foodItemMapper = $this->createMock(FoodItemMapper::class);
		$this->service = new FoodEntryService($this->mapper, $this->foodItemMapper);
	}

	private function makeEntry(array $overrides = []): FoodEntry {
		$entry = new FoodEntry();
		$entry->setId($overrides['id'] ?? 1);
		$entry->setUserId($overrides['userId'] ?? 'user1');
		$entry->setFoodName($overrides['foodName'] ?? 'Banana');
		$entry->setCaloriesPer100g($overrides['caloriesPer100g'] ?? 89);
		$entry->setAmountGrams($overrides['amountGrams'] ?? 120);
		$entry->setAmountValue((float)($overrides['amountValue'] ?? $overrides['amountGrams'] ?? 120));
		$entry->setAmountUnit($overrides['amountUnit'] ?? 'g');
		$entry->setGramsPerUnit((float)($overrides['gramsPerUnit'] ?? 1.0));
		$entry->setMealType($overrides['mealType'] ?? 'breakfast');
		$entry->setEatenAt($overrides['eatenAt'] ?? '2026-04-01');
		$entry->setProteinPer100g($overrides['proteinPer100g'] ?? 1);
		$entry->setCarbsPer100g($overrides['carbsPer100g'] ?? 23);
		$entry->setFatPer100g($overrides['fatPer100g'] ?? 0);
		return $entry;
	}

	// ── find() ─────────────────────────────────────────────────────────────────

	public function testFindReturnsEntry(): void {
		$entry = $this->makeEntry();
		$this->mapper->method('findForUser')->with(1, 'user1')->willReturn($entry);

		$result = $this->service->find(1, 'user1');
		$this->assertSame($entry, $result);
	}

	public function testFindThrowsWhenNotFound(): void {
		$this->mapper->method('findForUser')->willThrowException(new DoesNotExistException(''));

		$this->expectException(DoesNotExistException::class);
		$this->service->find(999, 'user1');
	}

	// ── patch() ────────────────────────────────────────────────────────────────

	public function testPatchUpdatesOnlyProvidedFields(): void {
		$entry = $this->makeEntry();
		$this->mapper->method('findForUser')->with(1, 'user1')->willReturn($entry);
		$this->mapper->method('update')->willReturnArgument(0);

		$result = $this->service->patch(1, 'user1', ['amountValue' => 200.0, 'gramsPerUnit' => 1.0]);

		$this->assertEquals(200, $result->getAmountGrams());
		// Unchanged fields stay the same
		$this->assertEquals('Banana', $result->getFoodName());
		$this->assertEquals(89, $result->getCaloriesPer100g());
		$this->assertEquals('breakfast', $result->getMealType());
	}

	public function testPatchValidatesMealType(): void {
		$entry = $this->makeEntry();
		$this->mapper->method('findForUser')->willReturn($entry);

		$this->expectException(\InvalidArgumentException::class);
		$this->service->patch(1, 'user1', ['mealType' => 'brunch']);
	}

	public function testPatchValidatesDate(): void {
		$entry = $this->makeEntry();
		$this->mapper->method('findForUser')->willReturn($entry);

		$this->expectException(\InvalidArgumentException::class);
		$this->service->patch(1, 'user1', ['eatenAt' => 'not-a-date']);
	}

	public function testPatchValidatesCalories(): void {
		$entry = $this->makeEntry();
		$this->mapper->method('findForUser')->willReturn($entry);

		$this->expectException(\InvalidArgumentException::class);
		$this->service->patch(1, 'user1', ['caloriesPer100g' => -5]);
	}

	public function testPatchValidatesMacro(): void {
		$entry = $this->makeEntry();
		$this->mapper->method('findForUser')->willReturn($entry);

		$this->expectException(\InvalidArgumentException::class);
		$this->service->patch(1, 'user1', ['proteinPer100g' => 150]);
	}

	public function testPatchTruncatesFoodName(): void {
		$entry = $this->makeEntry();
		$this->mapper->method('findForUser')->willReturn($entry);
		$this->mapper->method('update')->willReturnArgument(0);

		$longName = str_repeat('A', 300);
		$result = $this->service->patch(1, 'user1', ['foodName' => $longName]);
		$this->assertEquals(255, mb_strlen($result->getFoodName()));
	}

	public function testPatchMultipleFields(): void {
		$entry = $this->makeEntry();
		$this->mapper->method('findForUser')->willReturn($entry);
		$this->mapper->method('update')->willReturnArgument(0);

		$result = $this->service->patch(1, 'user1', [
			'foodName' => 'Apple',
			'caloriesPer100g' => 52,
			'mealType' => 'snack',
			'eatenAt' => '2026-04-02',
		]);

		$this->assertEquals('Apple', $result->getFoodName());
		$this->assertEquals(52, $result->getCaloriesPer100g());
		$this->assertEquals('snack', $result->getMealType());
		$this->assertEquals('2026-04-02', $result->getEatenAt());
	}

	public function testPatchWithNoFieldsDoesNothing(): void {
		$entry = $this->makeEntry();
		$this->mapper->method('findForUser')->willReturn($entry);
		$this->mapper->method('update')->willReturnArgument(0);

		$result = $this->service->patch(1, 'user1', []);
		$this->assertEquals('Banana', $result->getFoodName());
	}

	public function testPatchCastsStringAmountValueToFloat(): void {
		$entry = $this->makeEntry();
		$this->mapper->method('findForUser')->willReturn($entry);
		$this->mapper->method('update')->willReturnArgument(0);

		$result = $this->service->patch(1, 'user1', ['amountValue' => '250', 'caloriesPer100g' => '100']);
		$this->assertSame(250, $result->getAmountGrams());
		$this->assertSame(100, $result->getCaloriesPer100g());
	}

	public function testPatchRejectsNonNumericAmountValue(): void {
		$entry = $this->makeEntry();
		$this->mapper->method('findForUser')->willReturn($entry);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('amountValue must be a number.');
		$this->service->patch(1, 'user1', ['amountValue' => 'abc']);
	}

	public function testPatchClearsNullableMacro(): void {
		$entry = $this->makeEntry();
		$this->mapper->method('findForUser')->willReturn($entry);
		$this->mapper->method('update')->willReturnArgument(0);

		$result = $this->service->patch(1, 'user1', ['proteinPer100g' => null]);
		$this->assertNull($result->getProteinPer100g());
	}

	// ── batchCopy() ────────────────────────────────────────────────────────────

	public function testBatchCopyCopiesAllEntries(): void {
		$entries = [
			$this->makeEntry(['id' => 1, 'mealType' => 'breakfast']),
			$this->makeEntry(['id' => 2, 'mealType' => 'lunch', 'foodName' => 'Rice']),
		];
		$this->mapper->method('findAllForUserOnDate')->with('user1', '2026-04-01')->willReturn($entries);
		$this->mapper->method('insert')->willReturnCallback(function (FoodEntry $e) {
			$e->setId(100);
			return $e;
		});

		$result = $this->service->batchCopy('user1', '2026-04-01', '2026-04-02');

		$this->assertCount(2, $result);
		$this->assertEquals('2026-04-02', $result[0]->getEatenAt());
		$this->assertEquals('2026-04-02', $result[1]->getEatenAt());
		$this->assertEquals('Banana', $result[0]->getFoodName());
		$this->assertEquals('Rice', $result[1]->getFoodName());
	}

	public function testBatchCopyFiltersByMealType(): void {
		$entries = [
			$this->makeEntry(['id' => 1, 'mealType' => 'breakfast']),
			$this->makeEntry(['id' => 2, 'mealType' => 'lunch', 'foodName' => 'Rice']),
		];
		$this->mapper->method('findAllForUserOnDate')->willReturn($entries);
		$this->mapper->method('insert')->willReturnCallback(function (FoodEntry $e) {
			$e->setId(100);
			return $e;
		});

		$result = $this->service->batchCopy('user1', '2026-04-01', '2026-04-02', 'lunch');

		$this->assertCount(1, $result);
		$this->assertEquals('Rice', $result[0]->getFoodName());
		$this->assertEquals('lunch', $result[0]->getMealType());
	}

	public function testBatchCopyThrowsWhenNoEntries(): void {
		$this->mapper->method('findAllForUserOnDate')->willReturn([]);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('No entries found on the source date.');
		$this->service->batchCopy('user1', '2026-04-01', '2026-04-02');
	}

	public function testBatchCopyValidatesFromDate(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service->batchCopy('user1', 'bad-date', '2026-04-02');
	}

	public function testBatchCopyValidatesToDate(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service->batchCopy('user1', '2026-04-01', 'bad-date');
	}

	public function testBatchCopyValidatesMealType(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service->batchCopy('user1', '2026-04-01', '2026-04-02', 'brunch');
	}

	public function testBatchCopyPreservesFoodItemId(): void {
		$entry = $this->makeEntry();
		$entry->setFoodItemId(42);
		$this->mapper->method('findAllForUserOnDate')->willReturn([$entry]);
		$this->mapper->method('insert')->willReturnArgument(0);

		$result = $this->service->batchCopy('user1', '2026-04-01', '2026-04-02');
		$this->assertEquals(42, $result[0]->getFoodItemId());
	}

	public function testBatchCopyCopiesVolumeFields(): void {
		$entry = $this->makeEntry(['amountUnit' => 'ml', 'gramsPerUnit' => 1.0, 'amountValue' => 250.0]);
		$this->mapper->method('findAllForUserOnDate')->willReturn([$entry]);
		$this->mapper->method('insert')->willReturnArgument(0);

		$result = $this->service->batchCopy('user1', '2026-04-01', '2026-04-02');
		$this->assertEquals('ml', $result[0]->getAmountUnit());
		$this->assertEquals(250.0, $result[0]->getAmountValue());
		$this->assertEquals(1.0, $result[0]->getGramsPerUnit());
	}

	// ── create() ───────────────────────────────────────────────────────────────

	public function testCreateStoresGramsUnitEntry(): void {
		$this->mapper->method('insert')->willReturnArgument(0);

		$entry = $this->service->create('user1', 'Oats', 370, 80.0, 'breakfast', '2026-04-01');

		$this->assertEquals(80, $entry->getAmountGrams());
		$this->assertEquals(80.0, $entry->getAmountValue());
		$this->assertEquals('g', $entry->getAmountUnit());
		$this->assertEquals(1.0, $entry->getGramsPerUnit());
	}

	public function testCreateComputesAmountGramsFromVolumeUnit(): void {
		// 200 ml × 1.0 g/ml = 200 g
		$this->mapper->method('insert')->willReturnArgument(0);

		$entry = $this->service->create(
			'user1', 'Water', 1, 200.0, 'breakfast', '2026-04-01',
			null, null, null, null, null, 'ml', 1.0,
		);

		$this->assertEquals(200, $entry->getAmountGrams());
		$this->assertEquals(200.0, $entry->getAmountValue());
		$this->assertEquals('ml', $entry->getAmountUnit());
		$this->assertEquals(1.0, $entry->getGramsPerUnit());
	}

	public function testCreateComputesAmountGramsFromTspUnit(): void {
		// 3 tsp × (4.92892 ml/tsp × 1.0 g/ml) ≈ 15 g
		$this->mapper->method('insert')->willReturnArgument(0);

		$entry = $this->service->create(
			'user1', 'Honey', 304, 3.0, 'breakfast', '2026-04-01',
			null, null, null, null, null, 'tsp', 4.92892,
		);

		$this->assertEquals((int) round(3.0 * 4.92892), $entry->getAmountGrams());
		$this->assertEquals('tsp', $entry->getAmountUnit());
	}

	public function testCreateValidatesAmountUnit(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid amountUnit');
		$this->service->create('user1', 'X', 100, 50.0, 'breakfast', '2026-04-01',
			null, null, null, null, null, 'litres', 1.0);
	}

	public function testCreateRejectsZeroAmountValue(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('amountValue must be greater than 0');
		$this->service->create('user1', 'X', 100, 0.0, 'breakfast', '2026-04-01');
	}

	public function testCreateRejectsNegativeAmountValue(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service->create('user1', 'X', 100, -10.0, 'breakfast', '2026-04-01');
	}

	public function testCreateRejectsZeroGramsPerUnit(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('gramsPerUnit must be greater than 0');
		$this->service->create('user1', 'X', 100, 50.0, 'breakfast', '2026-04-01',
			null, null, null, null, null, 'ml', 0.0);
	}

	public function testCreateThrowsWhenComputedGramsExceedMax(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Computed amount in grams must not exceed');
		// 500 cups × 300 g/cup = 150 000 g > 10 000 limit
		$this->service->create('user1', 'X', 100, 500.0, 'breakfast', '2026-04-01',
			null, null, null, null, null, 'cup', 300.0);
	}

	// ── update() ───────────────────────────────────────────────────────────────

	public function testUpdateWithVolumeUnit(): void {
		$existing = $this->makeEntry();
		$this->mapper->method('findForUser')->willReturn($existing);
		$this->mapper->method('update')->willReturnArgument(0);

		// 500 ml × 1.0 g/ml = 500 g
		$result = $this->service->update(1, 'user1', 'OJ', 45, 500.0, 'breakfast', '2026-04-01',
			null, null, null, 'ml', 1.0);

		$this->assertEquals(500, $result->getAmountGrams());
		$this->assertEquals(500.0, $result->getAmountValue());
		$this->assertEquals('ml', $result->getAmountUnit());
	}

	// ── patch() volume fields ──────────────────────────────────────────────────

	public function testPatchRecomputesGramsWhenAmountValueChanges(): void {
		$entry = $this->makeEntry(['amountUnit' => 'ml', 'gramsPerUnit' => 1.0]);
		$this->mapper->method('findForUser')->willReturn($entry);
		$this->mapper->method('update')->willReturnArgument(0);

		$result = $this->service->patch(1, 'user1', ['amountValue' => 350.0]);

		$this->assertEquals(350, $result->getAmountGrams());
		$this->assertEquals(350.0, $result->getAmountValue());
	}

	public function testPatchRecomputesGramsWhenGramsPerUnitChanges(): void {
		// Entry was 3 tsp; now density changes so gramsPerUnit changes from 4.93 to 6.0
		$entry = $this->makeEntry(['amountValue' => 3.0, 'amountUnit' => 'tsp', 'gramsPerUnit' => 4.93]);
		$this->mapper->method('findForUser')->willReturn($entry);
		$this->mapper->method('update')->willReturnArgument(0);

		$result = $this->service->patch(1, 'user1', ['gramsPerUnit' => 6.0]);

		$this->assertEquals((int) round(3.0 * 6.0), $result->getAmountGrams());
	}

	public function testPatchValidatesAmountUnit(): void {
		$entry = $this->makeEntry();
		$this->mapper->method('findForUser')->willReturn($entry);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid amountUnit');
		$this->service->patch(1, 'user1', ['amountUnit' => 'litres']);
	}

	public function testPatchValidatesGramsPerUnit(): void {
		$entry = $this->makeEntry();
		$this->mapper->method('findForUser')->willReturn($entry);

		$this->expectException(\InvalidArgumentException::class);
		$this->service->patch(1, 'user1', ['gramsPerUnit' => 0.0]);
	}
}
