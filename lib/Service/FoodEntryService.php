<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\CalorieTracker\Service;

use OCA\CalorieTracker\Db\FoodEntry;
use OCA\CalorieTracker\Db\FoodEntryMapper;
use OCA\CalorieTracker\Db\FoodItemMapper;
use OCP\AppFramework\Db\DoesNotExistException;

class FoodEntryService {
	public const MEAL_TYPES = ['breakfast', 'lunch', 'dinner', 'snack'];

	public function __construct(
		private FoodEntryMapper $mapper,
		private FoodItemMapper $foodItemMapper,
	) {
	}

	/**
	 * @return FoodEntry[]
	 */
	public function findAllForDay(string $userId, string $date): array {
		$this->validateDate($date);
		return $this->mapper->findAllForUserOnDate($userId, $date);
	}

	public function create(
		string $userId,
		string $foodName,
		int $caloriesPer100g,
		int $amountGrams,
		string $mealType,
		string $eatenAt,
		?int $proteinPer100g = null,
		?int $carbsPer100g = null,
		?int $fatPer100g = null,
		?string $source = null,
		?string $externalId = null,
	): FoodEntry {
		$this->validateMealType($mealType);
		$this->validateDate($eatenAt);
		$this->validatePositive('caloriesPer100g', $caloriesPer100g);
		$this->validatePositive('amountGrams', $amountGrams);

		// Persist the food reference so it survives cache expiry
		$foodItemId = null;
		if ($source !== null) {
			$foodItem = $this->foodItemMapper->upsert(
				$userId,
				$source,
				$externalId,
				trim($foodName),
				$caloriesPer100g,
				$proteinPer100g,
				$carbsPer100g,
				$fatPer100g,
			);
			$foodItemId = $foodItem->getId();
		}

		$entry = new FoodEntry();
		$entry->setUserId($userId);
		$entry->setFoodName(trim($foodName));
		$entry->setCaloriesPer100g($caloriesPer100g);
		$entry->setAmountGrams($amountGrams);
		$entry->setMealType($mealType);
		$entry->setEatenAt($eatenAt);
		$entry->setProteinPer100g($proteinPer100g);
		$entry->setCarbsPer100g($carbsPer100g);
		$entry->setFatPer100g($fatPer100g);
		$entry->setFoodItemId($foodItemId);

		return $this->mapper->insert($entry);
	}

	public function update(
		int $id,
		string $userId,
		string $foodName,
		int $caloriesPer100g,
		int $amountGrams,
		string $mealType,
		string $eatenAt,
		?int $proteinPer100g = null,
		?int $carbsPer100g = null,
		?int $fatPer100g = null,
	): FoodEntry {
		$this->validateMealType($mealType);
		$this->validateDate($eatenAt);
		$this->validatePositive('caloriesPer100g', $caloriesPer100g);
		$this->validatePositive('amountGrams', $amountGrams);

		$entry = $this->mapper->findForUser($id, $userId);
		$entry->setFoodName(trim($foodName));
		$entry->setCaloriesPer100g($caloriesPer100g);
		$entry->setAmountGrams($amountGrams);
		$entry->setMealType($mealType);
		$entry->setEatenAt($eatenAt);
		$entry->setProteinPer100g($proteinPer100g);
		$entry->setCarbsPer100g($carbsPer100g);
		$entry->setFatPer100g($fatPer100g);

		return $this->mapper->update($entry);
	}

	/**
	 * Returns per-day calorie and macro summaries for a date range.
	 *
	 * @return array<int, array{date: string, totalKcal: int, itemCount: int, totalProteinG: int, totalCarbsG: int, totalFatG: int}>
	 */
	public function getDailySummaries(string $userId, string $from, string $to): array {
		$this->validateDate($from);
		$this->validateDate($to);

		$rows = $this->mapper->getRawEntriesForDateRange($userId, $from, $to);

		$byDate = [];
		foreach ($rows as $row) {
			$date = $row['eaten_at'];
			if (!isset($byDate[$date])) {
				$byDate[$date] = [
					'date' => $date,
					'totalKcal' => 0,
					'itemCount' => 0,
					'totalProteinG' => 0,
					'totalCarbsG' => 0,
					'totalFatG' => 0,
				];
			}
			$amount = (int)$row['amount_grams'];
			$byDate[$date]['totalKcal'] += (int) round((int)$row['calories_per100g'] * $amount / 100);
			$byDate[$date]['itemCount']++;
			if ($row['protein_per100g'] !== null) {
				$byDate[$date]['totalProteinG'] += (int) round((int)$row['protein_per100g'] * $amount / 100);
			}
			if ($row['carbs_per100g'] !== null) {
				$byDate[$date]['totalCarbsG'] += (int) round((int)$row['carbs_per100g'] * $amount / 100);
			}
			if ($row['fat_per100g'] !== null) {
				$byDate[$date]['totalFatG'] += (int) round((int)$row['fat_per100g'] * $amount / 100);
			}
		}

		return array_values($byDate);
	}

	public function delete(int $id, string $userId): FoodEntry {
		$entry = $this->mapper->findForUser($id, $userId);
		return $this->mapper->delete($entry);
	}

	private function validateMealType(string $mealType): void {
		if (!in_array($mealType, self::MEAL_TYPES, true)) {
			throw new \InvalidArgumentException(
				'Invalid meal type. Must be one of: ' . implode(', ', self::MEAL_TYPES)
			);
		}
	}

	private function validateDate(string $date): void {
		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
			throw new \InvalidArgumentException('Invalid date format. Expected YYYY-MM-DD.');
		}
	}

	private function validatePositive(string $field, int $value): void {
		if ($value <= 0) {
			throw new \InvalidArgumentException("$field must be greater than 0.");
		}
	}
}
