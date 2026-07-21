<?php

/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\CalorieTracker\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0005Date20260505000000 extends SimpleMigrationStep {
	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('caltracker_food_items')) {
			$table = $schema->getTable('caltracker_food_items');
			if (!$table->hasColumn('density_grams_per_ml')) {
				$table->addColumn('density_grams_per_ml', Types::FLOAT, [
					'notnull' => false,
					'default' => null,
				]);
			}
		}

		if ($schema->hasTable('caltracker_food_entries')) {
			$table = $schema->getTable('caltracker_food_entries');
			if (!$table->hasColumn('amount_value')) {
				$table->addColumn('amount_value', Types::FLOAT, [
					'notnull' => true,
					'default' => 0,
				]);
			}
			if (!$table->hasColumn('amount_unit')) {
				$table->addColumn('amount_unit', Types::STRING, [
					'notnull' => true,
					'length'  => 10,
					'default' => 'g',
				]);
			}
			if (!$table->hasColumn('grams_per_unit')) {
				$table->addColumn('grams_per_unit', Types::FLOAT, [
					'notnull' => true,
					'default' => 1.0,
				]);
			}
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// Backfill existing entries: treat legacy rows as plain grams entries.
		$this->db->executeStatement(
			"UPDATE `*PREFIX*caltracker_food_entries`
			 SET `amount_value` = `amount_grams`, `amount_unit` = 'g', `grams_per_unit` = 1
			 WHERE `amount_value` = 0"
		);
	}
}
