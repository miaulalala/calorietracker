<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\CalorieTracker\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<FoodItem> */
class FoodItemMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'caltracker_food_items', FoodItem::class);
	}

	/**
	 * Return food items sorted by most recently used (latest entry eaten_at).
	 *
	 * Uses a subquery to aggregate per food_item_id, then joins back to the
	 * items table so all columns are available without violating SQL strict
	 * GROUP BY rules (required by PostgreSQL / SQLite).
	 *
	 * @return FoodItem[]
	 */
	public function findRecentForUser(string $userId, int $limit = 25): array {
		$sub = $this->db->getQueryBuilder();
		$sub->select('food_item_id')
			->selectAlias($sub->func()->max('eaten_at'), 'last_used')
			->from('caltracker_food_entries')
			->where($sub->expr()->isNotNull('food_item_id'))
			->groupBy('food_item_id');

		$qb = $this->db->getQueryBuilder();
		$qb->select('fi.*')
			->from($this->getTableName(), 'fi')
			->innerJoin('fi', $qb->createFunction('(' . $sub->getSQL() . ')'), 'agg',
				$qb->expr()->eq('fi.id', 'agg.food_item_id'))
			->where($qb->expr()->eq('fi.user_id', $qb->createNamedParameter($userId)))
			->orderBy('agg.last_used', 'DESC')
			->setMaxResults($limit);

		// Forward the subquery's positional parameters
		foreach ($sub->getParameters() as $key => $value) {
			$qb->setParameter($key, $value, $sub->getParameterType($key));
		}

		return $this->findEntities($qb);
	}

	/**
	 * Return food items sorted by usage frequency (most entries first).
	 *
	 * Uses a subquery to compute counts per food_item_id, then joins back
	 * to avoid non-aggregated columns in SELECT with GROUP BY (PostgreSQL).
	 *
	 * @return FoodItem[]
	 */
	public function findFrequentForUser(string $userId, int $limit = 25): array {
		$sub = $this->db->getQueryBuilder();
		$sub->select('food_item_id')
			->selectAlias($sub->func()->count('id'), 'use_count')
			->from('caltracker_food_entries')
			->where($sub->expr()->isNotNull('food_item_id'))
			->groupBy('food_item_id');

		$qb = $this->db->getQueryBuilder();
		$qb->select('fi.*')
			->from($this->getTableName(), 'fi')
			->innerJoin('fi', $qb->createFunction('(' . $sub->getSQL() . ')'), 'agg',
				$qb->expr()->eq('fi.id', 'agg.food_item_id'))
			->where($qb->expr()->eq('fi.user_id', $qb->createNamedParameter($userId)))
			->orderBy('agg.use_count', 'DESC')
			->setMaxResults($limit);

		foreach ($sub->getParameters() as $key => $value) {
			$qb->setParameter($key, $value, $sub->getParameterType($key));
		}

		return $this->findEntities($qb);
	}

	/**
	 * Find an existing food item by source + external_id, or return null.
	 */
	public function findBySourceId(string $userId, string $source, string $externalId): ?FoodItem {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('source', $qb->createNamedParameter($source)))
			->andWhere($qb->expr()->eq('external_id', $qb->createNamedParameter($externalId)));

		try {
			return $this->findEntity($qb);
		} catch (\OCP\AppFramework\Db\DoesNotExistException) {
			return null;
		}
	}

	/**
	 * Find-or-create a food item. If it already exists (matched by user+source+externalId)
	 * the nutritional values are refreshed in case they changed.
	 */
	public function upsert(
		string $userId,
		string $source,
		?string $externalId,
		string $name,
		int $caloriesPer100g,
		?int $proteinPer100g,
		?int $carbsPer100g,
		?int $fatPer100g,
	): FoodItem {
		// For items with a known external ID, check if we already have it
		if ($externalId !== null) {
			$existing = $this->findBySourceId($userId, $source, $externalId);
			if ($existing !== null) {
				// Refresh nutritional values (data may have changed upstream)
				$existing->setName($name);
				$existing->setCaloriesPer100g($caloriesPer100g);
				$existing->setProteinPer100g($proteinPer100g);
				$existing->setCarbsPer100g($carbsPer100g);
				$existing->setFatPer100g($fatPer100g);
				return $this->update($existing);
			}
		}

		$item = new FoodItem();
		$item->setUserId($userId);
		$item->setSource($source);
		$item->setExternalId($externalId);
		$item->setName($name);
		$item->setCaloriesPer100g($caloriesPer100g);
		$item->setProteinPer100g($proteinPer100g);
		$item->setCarbsPer100g($carbsPer100g);
		$item->setFatPer100g($fatPer100g);
		return $this->insert($item);
	}
}
