<?php

/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

return [
	'routes' => [
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

		// food database search proxies
		['name' => 'open_food_facts#search', 'url' => '/off/search', 'verb' => 'GET'],
		['name' => 'usda_fdc#search', 'url' => '/usda/search', 'verb' => 'GET'],

		// food entries
		['name' => 'food_entry#index', 'url' => '/entries', 'verb' => 'GET'],
		['name' => 'food_entry#create', 'url' => '/entries', 'verb' => 'POST'],
		['name' => 'food_entry#update', 'url' => '/entries/{id}', 'verb' => 'PUT'],
		['name' => 'food_entry#delete', 'url' => '/entries/{id}', 'verb' => 'DELETE'],
		['name' => 'food_entry#summary', 'url' => '/entries/summary', 'verb' => 'GET'],

		// settings
		['name' => 'settings#get', 'url' => '/settings', 'verb' => 'GET'],
		['name' => 'settings#save', 'url' => '/settings', 'verb' => 'PUT'],
	],
];
