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

	private const MAX_FOOD_NAME_LENGTH  = 255;
	private const MAX_CALORIES_PER_100G = 9000;  // well above pure fat (~900 kcal/100 g)
	private const MAX_AMOUNT_GRAMS      = 10000; // 10 kg ceiling
	private const MAX_MACRO_PER_100G    = 100;   // no single macro can exceed 100 g/100 g

	public function __construct(
		private FoodEntryMapper $mapper,
		private FoodItemMapper $foodItemMapper,
	) {
	}

	public function find(int $id, string $userId): FoodEntry {
		return $this->mapper->findForUser($id, $userId);
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
		$foodName = mb_substr(trim($foodName), 0, self::MAX_FOOD_NAME_LENGTH, 'UTF-8');
		$this->validateMealType($mealType);
		$this->validateDate($eatenAt);
		$this->validatePositive('caloriesPer100g', $caloriesPer100g, self::MAX_CALORIES_PER_100G);
		$this->validatePositive('amountGrams', $amountGrams, self::MAX_AMOUNT_GRAMS);
		if ($proteinPer100g !== null) {
			$this->validateMacro('proteinPer100g', $proteinPer100g);
		}
		if ($carbsPer100g !== null) {
			$this->validateMacro('carbsPer100g', $carbsPer100g);
		}
		if ($fatPer100g !== null) {
			$this->validateMacro('fatPer100g', $fatPer100g);
		}

		// Persist the food reference so it survives cache expiry
		$foodItemId = null;
		if ($source !== null) {
			$foodItem = $this->foodItemMapper->upsert(
				$userId,
				$source,
				$externalId,
				$foodName,
				$caloriesPer100g,
				$proteinPer100g,
				$carbsPer100g,
				$fatPer100g,
			);
			$foodItemId = $foodItem->getId();
		}

		$entry = new FoodEntry();
		$entry->setUserId($userId);
		$entry->setFoodName($foodName);
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
		$foodName = mb_substr(trim($foodName), 0, self::MAX_FOOD_NAME_LENGTH, 'UTF-8');
		$this->validateMealType($mealType);
		$this->validateDate($eatenAt);
		$this->validatePositive('caloriesPer100g', $caloriesPer100g, self::MAX_CALORIES_PER_100G);
		$this->validatePositive('amountGrams', $amountGrams, self::MAX_AMOUNT_GRAMS);
		if ($proteinPer100g !== null) {
			$this->validateMacro('proteinPer100g', $proteinPer100g);
		}
		if ($carbsPer100g !== null) {
			$this->validateMacro('carbsPer100g', $carbsPer100g);
		}
		if ($fatPer100g !== null) {
			$this->validateMacro('fatPer100g', $fatPer100g);
		}

		$entry = $this->mapper->findForUser($id, $userId);
		$entry->setFoodName($foodName);
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

	/**
	 * Partial update — only fields present in $fields are changed.
	 *
	 * Uses array_key_exists so that explicitly passing null for a nullable
	 * field (e.g. proteinPer100g) clears it, while omitting the key leaves
	 * the current value untouched.
	 *
	 * @param array<string, mixed> $fields
	 */
	public function patch(int $id, string $userId, array $fields): FoodEntry {
		$entry = $this->mapper->findForUser($id, $userId);

		if (array_key_exists('foodName', $fields) && $fields['foodName'] !== null) {
			$entry->setFoodName(mb_substr(trim($fields['foodName']), 0, self::MAX_FOOD_NAME_LENGTH, 'UTF-8'));
		}
		if (array_key_exists('caloriesPer100g', $fields) && $fields['caloriesPer100g'] !== null) {
			$this->validatePositive('caloriesPer100g', $fields['caloriesPer100g'], self::MAX_CALORIES_PER_100G);
			$entry->setCaloriesPer100g($fields['caloriesPer100g']);
		}
		if (array_key_exists('amountGrams', $fields) && $fields['amountGrams'] !== null) {
			$this->validatePositive('amountGrams', $fields['amountGrams'], self::MAX_AMOUNT_GRAMS);
			$entry->setAmountGrams($fields['amountGrams']);
		}
		if (array_key_exists('mealType', $fields) && $fields['mealType'] !== null) {
			$this->validateMealType($fields['mealType']);
			$entry->setMealType($fields['mealType']);
		}
		if (array_key_exists('eatenAt', $fields) && $fields['eatenAt'] !== null) {
			$this->validateDate($fields['eatenAt']);
			$entry->setEatenAt($fields['eatenAt']);
		}
		if (array_key_exists('proteinPer100g', $fields)) {
			if ($fields['proteinPer100g'] !== null) {
				$this->validateMacro('proteinPer100g', $fields['proteinPer100g']);
			}
			$entry->setProteinPer100g($fields['proteinPer100g']);
		}
		if (array_key_exists('carbsPer100g', $fields)) {
			if ($fields['carbsPer100g'] !== null) {
				$this->validateMacro('carbsPer100g', $fields['carbsPer100g']);
			}
			$entry->setCarbsPer100g($fields['carbsPer100g']);
		}
		if (array_key_exists('fatPer100g', $fields)) {
			if ($fields['fatPer100g'] !== null) {
				$this->validateMacro('fatPer100g', $fields['fatPer100g']);
			}
			$entry->setFatPer100g($fields['fatPer100g']);
		}

		return $this->mapper->update($entry);
	}

	private const MAX_BATCH_COPY = 50;

	/**
	 * Copy all entries from one date to another.
	 *
	 * @return FoodEntry[] the newly created entries
	 */
	public function batchCopy(string $userId, string $fromDate, string $toDate, ?string $mealType = null): array {
		$this->validateDate($fromDate);
		$this->validateDate($toDate);
		if ($mealType !== null) {
			$this->validateMealType($mealType);
		}

		$sourceEntries = $this->mapper->findAllForUserOnDate($userId, $fromDate);
		if ($mealType !== null) {
			$sourceEntries = array_filter($sourceEntries, fn (FoodEntry $e) => $e->getMealType() === $mealType);
		}

		if (count($sourceEntries) === 0) {
			throw new \InvalidArgumentException('No entries found on the source date.');
		}
		if (count($sourceEntries) > self::MAX_BATCH_COPY) {
			throw new \InvalidArgumentException('Too many entries to copy (max ' . self::MAX_BATCH_COPY . ').');
		}

		$created = [];
		foreach ($sourceEntries as $src) {
			$entry = new FoodEntry();
			$entry->setUserId($userId);
			$entry->setFoodName($src->getFoodName());
			$entry->setCaloriesPer100g($src->getCaloriesPer100g());
			$entry->setAmountGrams($src->getAmountGrams());
			$entry->setMealType($src->getMealType());
			$entry->setEatenAt($toDate);
			$entry->setProteinPer100g($src->getProteinPer100g());
			$entry->setCarbsPer100g($src->getCarbsPer100g());
			$entry->setFatPer100g($src->getFatPer100g());
			$entry->setFoodItemId($src->getFoodItemId());
			$created[] = $this->mapper->insert($entry);
		}

		return $created;
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
		[$year, $month, $day] = explode('-', $date);
		if (!checkdate((int)$month, (int)$day, (int)$year)) {
			throw new \InvalidArgumentException('Invalid date. Expected a valid calendar date.');
		}
	}

	private function validatePositive(string $field, int $value, int $max): void {
		if ($value <= 0) {
			throw new \InvalidArgumentException("$field must be greater than 0.");
		}
		if ($value > $max) {
			throw new \InvalidArgumentException("$field must not exceed $max.");
		}
	}

	private function validateMacro(string $field, int $value): void {
		if ($value < 0) {
			throw new \InvalidArgumentException("$field must not be negative.");
		}
		if ($value > self::MAX_MACRO_PER_100G) {
			throw new \InvalidArgumentException("$field must not exceed " . self::MAX_MACRO_PER_100G . ' g per 100 g.');
		}
	}
}
