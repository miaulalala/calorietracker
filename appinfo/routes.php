<?php

/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

return [
	'routes' => [
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

		// API capabilities
		['name' => 'api#capabilities', 'url' => '/api/capabilities', 'verb' => 'GET'],

		// food database search proxies
		['name' => 'open_food_facts#search', 'url' => '/off/search', 'verb' => 'GET'],
		['name' => 'open_food_facts#barcode', 'url' => '/off/barcode/{code}', 'verb' => 'GET'],
		['name' => 'usda_fdc#search', 'url' => '/usda/search', 'verb' => 'GET'],

		// food entries
		['name' => 'food_entry#index', 'url' => '/entries', 'verb' => 'GET'],
		['name' => 'food_entry#create', 'url' => '/entries', 'verb' => 'POST'],
		['name' => 'food_entry#batch', 'url' => '/entries/batch', 'verb' => 'POST'],
		['name' => 'food_entry#summary', 'url' => '/entries/summary', 'verb' => 'GET'],
		['name' => 'food_entry#show', 'url' => '/entries/{id}', 'verb' => 'GET'],
		['name' => 'food_entry#update', 'url' => '/entries/{id}', 'verb' => 'PUT'],
		['name' => 'food_entry#patch', 'url' => '/entries/{id}', 'verb' => 'PATCH'],
		['name' => 'food_entry#delete', 'url' => '/entries/{id}', 'verb' => 'DELETE'],

		// food items (quick re-entry)
		['name' => 'food_item#index', 'url' => '/food-items', 'verb' => 'GET'],

		// settings
		['name' => 'settings#get', 'url' => '/settings', 'verb' => 'GET'],
		['name' => 'settings#save', 'url' => '/settings', 'verb' => 'PUT'],
	],
];
