<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\CalorieTracker\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Http\Client\IClientService;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IRequest;
use OCP\L10N\IFactory as IL10NFactory;
use Psr\Log\LoggerInterface;

class OpenFoodFactsController extends Controller {
	private const OFF_SEARCH_URL = 'https://world.openfoodfacts.org/cgi/search.pl';
	private const USER_AGENT = 'NextcloudCalorieTracker/1.0 (https://nextcloud.com)';
	private const TTL_SEARCH = 24 * 3600;      // 24 h — new products may appear
	private const TTL_PRODUCT = 7 * 24 * 3600; // 7 d  — individual products are stable

	private ICache $cache;

	public function __construct(
		IRequest $request,
		private IClientService $clientService,
		private LoggerInterface $logger,
		private IL10NFactory $l10nFactory,
		ICacheFactory $cacheFactory,
	) {
		parent::__construct('calorietracker', $request);
		$this->cache = $cacheFactory->createDistributed('calorietracker');
	}

	#[NoAdminRequired]
	public function search(string $query): JSONResponse {
		$query = trim($query);
		if (strlen($query) < 2) {
			return new JSONResponse([]);
		}

		// Normalise to a 2-letter code ("de_DE" → "de")
		$userLang = substr($this->l10nFactory->findLanguage('calorietracker'), 0, 2);

		// Cache key is language-aware: different users get results in their language
		$searchKey = 'search:' . $userLang . ':' . md5(strtolower($query));

		$cached = $this->cache->get($searchKey);
		if ($cached !== null) {
			return new JSONResponse($cached);
		}

		try {
			$client = $this->clientService->newClient();

			// Request the original name, English, and the user's language variant.
			// OFF returns product_name in the lc-preferred language when a translation exists.
			$langNameField = 'product_name_' . $userLang;
			$fields = implode(',', array_unique([
				'code', 'product_name', 'product_name_en', $langNameField, 'nutriments',
			]));

			$response = $client->get(self::OFF_SEARCH_URL, [
				'query' => [
					'search_terms' => $query,
					'search_simple' => '1',
					'action' => 'process',
					'json' => '1',
					'lc' => $userLang,
					'fields' => $fields,
					'page_size' => '10',
				],
				'headers' => ['User-Agent' => self::USER_AGENT],
				'timeout' => 15,
			]);

			$data = json_decode($response->getBody(), true);
			$products = $data['products'] ?? [];

			$results = [];
			foreach ($products as $product) {
				// Name priority: user-language translation → English → original product_name
				$name = trim($product[$langNameField] ?? '')
					?: trim($product['product_name_en'] ?? '')
					?: trim($product['product_name'] ?? '');

				$kcal = $product['nutriments']['energy-kcal_100g'] ?? null;

				if ($name === '' || $kcal === null) {
					continue;
				}

				$protein = $product['nutriments']['proteins_100g'] ?? null;
				$carbs = $product['nutriments']['carbohydrates_100g'] ?? null;
				$fat = $product['nutriments']['fat_100g'] ?? null;

				$result = [
					'name' => $name,
					'caloriesPer100g' => (int) round((float) $kcal),
					'proteinPer100g' => $protein !== null ? (int) round((float) $protein) : null,
					'carbsPer100g' => $carbs !== null ? (int) round((float) $carbs) : null,
					'fatPer100g' => $fat !== null ? (int) round((float) $fat) : null,
				];

				$barcode = trim($product['code'] ?? '');
				if ($barcode !== '') {
					$result['barcode'] = $barcode;
					$this->cache->set('product:' . $barcode, $result, self::TTL_PRODUCT);
				}

				$results[] = $result;
			}

			$this->cache->set($searchKey, $results, self::TTL_SEARCH);

			return new JSONResponse($results);
		} catch (\Exception $e) {
			$this->logger->error('Open Food Facts search failed for query "{query}": {message}', [
				'query' => $query,
				'message' => $e->getMessage(),
				'exception' => $e,
				'app' => 'calorietracker',
			]);
			return new JSONResponse(['error' => 'Search failed'], Http::STATUS_BAD_GATEWAY);
		}
	}
}
