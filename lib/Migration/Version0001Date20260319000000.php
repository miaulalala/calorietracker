<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\CalorieTracker\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0001Date20260319000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('caltracker_food_entries')) {
			$table = $schema->createTable('caltracker_food_entries');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('user_id', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('food_name', 'string', [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('calories_per100g', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('amount_grams', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('meal_type', 'string', [
				'notnull' => true,
				'length' => 16,
			]);
			$table->addColumn('eaten_at', 'string', [
				'notnull' => true,
				'length' => 10,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['user_id', 'eaten_at'], 'caltracker_user_date_idx');
		}

		return $schema;
	}
}
