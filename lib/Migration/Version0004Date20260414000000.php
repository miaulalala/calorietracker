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
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0004Date20260414000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('caltracker_weight_logs')) {
			$table = $schema->createTable('caltracker_weight_logs');

			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20]);
			$table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$table->addColumn('weight_kg', Types::DECIMAL, ['notnull' => true, 'precision' => 5, 'scale' => 1]);
			$table->addColumn('logged_at', Types::STRING, ['notnull' => true, 'length' => 10]);
			$table->addColumn('note', Types::STRING, ['notnull' => false, 'length' => 255, 'default' => null]);

			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['user_id', 'logged_at'], 'caltracker_wl_user_date');
		}

		return $schema;
	}
}
