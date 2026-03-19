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

class Version0002Date20260319000001 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('caltracker_food_entries');

		if (!$table->hasColumn('protein_per100g')) {
			$table->addColumn('protein_per100g', 'integer', [
				'notnull' => false,
				'length' => 4,
				'default' => null,
			]);
		}

		if (!$table->hasColumn('carbs_per100g')) {
			$table->addColumn('carbs_per100g', 'integer', [
				'notnull' => false,
				'length' => 4,
				'default' => null,
			]);
		}

		if (!$table->hasColumn('fat_per100g')) {
			$table->addColumn('fat_per100g', 'integer', [
				'notnull' => false,
				'length' => 4,
				'default' => null,
			]);
		}

		return $schema;
	}
}
