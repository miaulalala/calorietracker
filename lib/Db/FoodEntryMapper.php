<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\CalorieTracker\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<FoodEntry> */
class FoodEntryMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'caltracker_food_entries', FoodEntry::class);
	}

	/**
	 * @return FoodEntry[]
	 */
	public function findAllForUserOnDate(string $userId, string $date): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('eaten_at', $qb->createNamedParameter($date)))
			->orderBy('meal_type', 'ASC');

		return $this->findEntities($qb);
	}

	/**
	 * Returns raw calorie/amount rows for a date range — used for summary aggregation.
	 *
	 * @return array<int, array{eaten_at: string, calories_per100g: int, amount_grams: int}>
	 */
	public function getRawEntriesForDateRange(string $userId, string $from, string $to): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('eaten_at', 'calories_per100g', 'amount_grams', 'protein_per100g', 'carbs_per100g', 'fat_per100g')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->gte('eaten_at', $qb->createNamedParameter($from)))
			->andWhere($qb->expr()->lte('eaten_at', $qb->createNamedParameter($to)));

		$result = $qb->executeQuery();
		$rows = $result->fetchAllAssociative();
		$result->closeCursor();
		return $rows;
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function findForUser(int $id, string $userId): FoodEntry {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

		return $this->findEntity($qb);
	}
}
