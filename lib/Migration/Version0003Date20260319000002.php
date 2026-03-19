<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\CalorieTracker\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0003Date20260319000002 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		// ------------------------------------------------------------------
		// caltracker_food_items — persistent food reference data
		// ------------------------------------------------------------------
		if (!$schema->hasTable('caltracker_food_items')) {
			$table = $schema->createTable('caltracker_food_items');

			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20]);
			$table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);

			// Where the item came from ('usda_fdc', 'manual', …)
			$table->addColumn('source', Types::STRING, ['notnull' => true, 'length' => 32]);
			// Source-specific identifier (e.g. fdcId from USDA FDC); null for manual entries
			$table->addColumn('external_id', Types::STRING, ['notnull' => false, 'length' => 64, 'default' => null]);

			$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 512]);
			$table->addColumn('calories_per100g', Types::INTEGER, ['notnull' => true, 'length' => 4]);
			$table->addColumn('protein_per100g', Types::INTEGER, ['notnull' => false, 'length' => 4, 'default' => null]);
			$table->addColumn('carbs_per100g', Types::INTEGER, ['notnull' => false, 'length' => 4, 'default' => null]);
			$table->addColumn('fat_per100g', Types::INTEGER, ['notnull' => false, 'length' => 4, 'default' => null]);

			$table->setPrimaryKey(['id']);
			// Fast lookup when deduplicating on insert
			$table->addIndex(['user_id', 'source', 'external_id'], 'caltracker_fi_user_src_ext');
		}

		// ------------------------------------------------------------------
		// caltracker_food_entries — add nullable FK to food_items
		// ------------------------------------------------------------------
		$entries = $schema->getTable('caltracker_food_entries');
		if (!$entries->hasColumn('food_item_id')) {
			$entries->addColumn('food_item_id', Types::BIGINT, [
				'notnull' => false,
				'length' => 20,
				'default' => null,
			]);
		}

		return $schema;
	}
}
