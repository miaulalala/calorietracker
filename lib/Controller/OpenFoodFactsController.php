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
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Http\Client\IClientService;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * Searches the USDA FoodData Central database, which covers both whole/generic
 * foods (Foundation, SR Legacy) and branded packaged products.
 * Foundation and SR Legacy results are sorted above Branded ones so that a
 * search for "banana" surfaces "Bananas, raw" before branded banana products.
 */
class OpenFoodFactsController extends Controller {
	private const FDC_SEARCH_URL = 'https://api.nal.usda.gov/fdc/v1/foods/search';

	/**
	 * Public demo key provided by api.data.gov — limited to ~30 req/hour per IP.
	 * Users can configure their own free key at https://fdc.nal.usda.gov/api-key-signup
	 */
	private const FDC_API_KEY = 'DEMO_KEY';

	private const OFF_BARCODE_URL = 'https://world.openfoodfacts.org/api/v2/product/';

	private const USER_AGENT = 'NextcloudCalorieTracker/1.0 (https://nextcloud.com)';
	private const TTL_SEARCH  = 7 * 24 * 3600; // 7 d — nutritional data is very stable

	private const MAX_FOOD_NAME_LENGTH = 255;

	// USDA FDC nutrient IDs
	private const NUTRIENT_KCAL    = 1008;
	private const NUTRIENT_PROTEIN = 1003;
	private const NUTRIENT_CARBS   = 1005; // Carbohydrate, by difference
	private const NUTRIENT_FAT     = 1004; // Total lipid (fat)

	// Lower number = shown first; whole-food datasets precede branded products
	private const DATA_TYPE_ORDER = [
		'Foundation'     => 0,
		'SR Legacy'      => 1,
		'Survey (FNDDS)' => 2,
		'Branded'        => 3,
	];

	private ICache $cache;

	public function __construct(
		IRequest $request,
		private IClientService $clientService,
		private LoggerInterface $logger,
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

		$searchKey = 'fdcsearch:' . md5(strtolower($query));

		$cached = $this->cache->get($searchKey);
		if ($cached !== null) {
			return new JSONResponse($cached);
		}

		try {
			$client = $this->clientService->newClient();
			$response = $client->get(self::FDC_SEARCH_URL, [
				'query' => [
					'api_key'  => self::FDC_API_KEY,
					'query'    => $query,
					'dataType' => 'Foundation,SR Legacy,Branded Food',
					'pageSize' => 20,
				],
				'headers' => ['User-Agent' => self::USER_AGENT],
				'timeout' => 15,
			]);

			$data  = json_decode($response->getBody(), true);
			$foods = $data['foods'] ?? [];

			$results = [];
			foreach ($foods as $food) {
				$name = trim($food['description'] ?? '');
				if ($name === '') {
					continue;
				}

				// Index nutrients by nutrientId for fast lookup
				$nutrients = [];
				foreach ($food['foodNutrients'] ?? [] as $n) {
					$id = $n['nutrientId'] ?? null;
					if ($id !== null) {
						$nutrients[(int) $id] = $n['value'] ?? null;
					}
				}

				$kcal = $nutrients[self::NUTRIENT_KCAL] ?? null;
				if ($kcal === null) {
					continue;
				}

				$results[] = [
					'source'          => 'usda_fdc',
					'externalId'      => isset($food['fdcId']) ? (string) $food['fdcId'] : null,
					'name'            => $name,
					'caloriesPer100g' => (int) round((float) $kcal),
					'proteinPer100g'  => isset($nutrients[self::NUTRIENT_PROTEIN])
						? (int) round((float) $nutrients[self::NUTRIENT_PROTEIN]) : null,
					'carbsPer100g'    => isset($nutrients[self::NUTRIENT_CARBS])
						? (int) round((float) $nutrients[self::NUTRIENT_CARBS]) : null,
					'fatPer100g'      => isset($nutrients[self::NUTRIENT_FAT])
						? (int) round((float) $nutrients[self::NUTRIENT_FAT]) : null,
					'_order'          => self::DATA_TYPE_ORDER[$food['dataType'] ?? ''] ?? 99,
				];
			}

			// Whole foods (Foundation, SR Legacy) first, branded products last
			usort($results, static fn (array $a, array $b): int => $a['_order'] <=> $b['_order']);

			$results = array_slice(
				array_map(static function (array $r): array {
					unset($r['_order']);
					return $r;
				}, $results),
				0, 10
			);

			$this->cache->set($searchKey, $results, self::TTL_SEARCH);

			return new JSONResponse($results);
		} catch (\Exception $e) {
			$this->logger->error('USDA FDC search failed for query "{query}": {message}', [
				'query'     => $query,
				'message'   => $e->getMessage(),
				'exception' => $e,
				'app'       => 'calorietracker',
			]);
			return new JSONResponse(['error' => 'Search failed'], Http::STATUS_BAD_GATEWAY);
		}
	}

	/**
	 * Look up a single product by barcode (EAN-8, EAN-13, UPC-A, UPC-E) via
	 * the Open Food Facts API. Returns the same shape as a search result item.
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: 30, period: 60)]
	public function barcode(string $barcode): JSONResponse {
		$barcode = trim($barcode);
		if (!preg_match('/^\d{8,14}$/', $barcode)) {
			return new JSONResponse(['error' => 'Invalid barcode. Expected 8–14 digits.'], Http::STATUS_BAD_REQUEST);
		}

		$cacheKey = 'offbarcode:' . $barcode;
		$cached = $this->cache->get($cacheKey);
		if ($cached !== null) {
			// null is cached as a sentinel for "not found", return 404
			if ($cached === false) {
				return new JSONResponse(null, Http::STATUS_NOT_FOUND);
			}
			return new JSONResponse($cached);
		}

		try {
			$client = $this->clientService->newClient();
			$response = $client->get(self::OFF_BARCODE_URL . urlencode($barcode) . '.json', [
				'query'   => ['fields' => 'product_name,product_name_en,nutriments'],
				'headers' => ['User-Agent' => self::USER_AGENT],
				'timeout' => 10,
			]);

			$data = json_decode($response->getBody(), true);

			if (($data['status'] ?? 0) !== 1) {
				// Cache negative result to avoid hammering the API for unknown barcodes
				$this->cache->set($cacheKey, false, self::TTL_SEARCH);
				return new JSONResponse(null, Http::STATUS_NOT_FOUND);
			}

			$product    = $data['product'];
			$nutriments = $product['nutriments'] ?? [];
			$kcal       = $nutriments['energy-kcal_100g'] ?? null;

			// Prefer the English name, fall back to the default product name
			$rawName = $product['product_name_en'] ?? $product['product_name'] ?? '';
			$name    = mb_substr(trim($rawName), 0, self::MAX_FOOD_NAME_LENGTH, 'UTF-8');

			if ($name === '' || $kcal === null) {
				$this->cache->set($cacheKey, false, self::TTL_SEARCH);
				return new JSONResponse(null, Http::STATUS_NOT_FOUND);
			}

			$result = [
				'source'          => 'openfoodfacts',
				'externalId'      => $barcode,
				'name'            => $name,
				'caloriesPer100g' => (int) round((float) $kcal),
				'proteinPer100g'  => isset($nutriments['proteins_100g'])
					? (int) round((float) $nutriments['proteins_100g']) : null,
				'carbsPer100g'    => isset($nutriments['carbohydrates_100g'])
					? (int) round((float) $nutriments['carbohydrates_100g']) : null,
				'fatPer100g'      => isset($nutriments['fat_100g'])
					? (int) round((float) $nutriments['fat_100g']) : null,
			];

			$this->cache->set($cacheKey, $result, self::TTL_SEARCH);
			return new JSONResponse($result);
		} catch (\Exception $e) {
			$this->logger->error('OpenFoodFacts barcode lookup failed for "{barcode}": {message}', [
				'barcode'   => $barcode,
				'message'   => $e->getMessage(),
				'exception' => $e,
				'app'       => 'calorietracker',
			]);
			return new JSONResponse(['error' => 'Lookup failed'], Http::STATUS_BAD_GATEWAY);
		}
	}
}
