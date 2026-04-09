<?php

/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

// App autoloader (our lib/ classes + nextcloud/ocp stubs via require-dev)
require_once __DIR__ . '/../vendor/autoload.php';

// The nextcloud/ocp package has no autoload section — its classes are only
// loadable through the Nextcloud server's own class loader.  Register the
// OCP directory from the dev package so PHPUnit can resolve OCP types.
spl_autoload_register(function (string $class): void {
	$prefix = 'OCP\\';
	if (!str_starts_with($class, $prefix)) {
		return;
	}
	$relative = str_replace('\\', '/', substr($class, strlen($prefix)));
	$file = __DIR__ . '/../vendor/nextcloud/ocp/OCP/' . $relative . '.php';
	if (file_exists($file)) {
		require_once $file;
	}
});
