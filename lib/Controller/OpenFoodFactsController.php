<?php

/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
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
 * Searches the Open Food Facts database for packaged and branded food products.
 * Results are normalised to per-100 g values to match the USDA FDC format.
 *
 * Also provides barcode lookup via the USDA FoodData Central API for
 * UPC/EAN/GTIN barcodes (used by the mobile app scanner).
 */
class OpenFoodFactsController extends Controller {
	private const OFF_SEARCH_URL = 'https://world.openfoodfacts.org/cgi/search.pl';

	// USDA FDC endpoints for barcode lookup
	private const FDC_SEARCH_URL = 'https://api.nal.usda.gov/fdc/v1/foods/search';

	/**
	 * Public demo key provided by api.data.gov — limited to ~30 req/hour per IP.
	 * Users can configure their own free key at https://fdc.nal.usda.gov/api-key-signup
	 */
	private const FDC_API_KEY = 'DEMO_KEY';

	private const USER_AGENT = 'NextcloudCalorieTracker/1.0 (https://nextcloud.com)';
	private const TTL_SEARCH  = 7 * 24 * 3600; // 7 d
	private const TTL_BARCODE = 30 * 24 * 3600; // 30 d — barcode mapping is very stable

	private const MAX_FOOD_NAME_LENGTH = 255;

	// USDA FDC nutrient IDs (used for barcode lookup)
	private const NUTRIENT_KCAL    = 1008;
	private const NUTRIENT_PROTEIN = 1003;
	private const NUTRIENT_CARBS   = 1005; // Carbohydrate, by difference
	private const NUTRIENT_FAT     = 1004; // Total lipid (fat)

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
	#[UserRateLimit(limit: 20, period: 60)]
	public function barcode(string $code): JSONResponse {
		$code = trim($code);
		if (!preg_match('/^\d{8,14}$/', $code)) {
			return new JSONResponse(['error' => 'Invalid barcode. Expected 8-14 digits (UPC/EAN/GTIN).'], Http::STATUS_BAD_REQUEST);
		}

		$cacheKey = 'fdcbarcode:' . $code;
		$cached = $this->cache->get($cacheKey);
		if ($cached !== null) {
			return new JSONResponse($cached);
		}

		try {
			$client = $this->clientService->newClient();
			$response = $client->get(self::FDC_SEARCH_URL, [
				'query' => [
					'api_key' => self::FDC_API_KEY,
					'query' => $code,
					'dataType' => 'Branded',
					'pageSize' => 3,
				],
				'headers' => ['User-Agent' => self::USER_AGENT],
				'timeout' => 15,
			]);

			$data = json_decode($response->getBody(), true);
			$foods = $data['foods'] ?? [];

			// Find the first food whose gtinUpc matches the queried barcode
			$match = null;
			foreach ($foods as $food) {
				$gtin = $food['gtinUpc'] ?? '';
				if ($gtin === $code || ltrim($gtin, '0') === ltrim($code, '0')) {
					$match = $food;
					break;
				}
			}

			if ($match === null) {
				$this->cache->set($cacheKey, [], self::TTL_BARCODE);
				return new JSONResponse([]);
			}

			$nutrients = [];
			foreach ($match['foodNutrients'] ?? [] as $n) {
				$id = $n['nutrientId'] ?? null;
				if ($id !== null) {
					$nutrients[(int)$id] = $n['value'] ?? null;
				}
			}

			$kcal = $nutrients[self::NUTRIENT_KCAL] ?? null;
			if ($kcal === null) {
				$this->cache->set($cacheKey, [], self::TTL_BARCODE);
				return new JSONResponse([]);
			}

			$result = [
				'source' => 'usda_fdc',
				'externalId' => isset($match['fdcId']) ? (string)$match['fdcId'] : null,
				'name' => mb_substr(trim($match['description'] ?? ''), 0, self::MAX_FOOD_NAME_LENGTH, 'UTF-8'),
				'caloriesPer100g' => (int)round((float)$kcal),
				'proteinPer100g' => isset($nutrients[self::NUTRIENT_PROTEIN])
					? (int)round((float)$nutrients[self::NUTRIENT_PROTEIN]) : null,
				'carbsPer100g' => isset($nutrients[self::NUTRIENT_CARBS])
					? (int)round((float)$nutrients[self::NUTRIENT_CARBS]) : null,
				'fatPer100g' => isset($nutrients[self::NUTRIENT_FAT])
					? (int)round((float)$nutrients[self::NUTRIENT_FAT]) : null,
				'barcode' => $code,
			];

			$this->cache->set($cacheKey, $result, self::TTL_BARCODE);
			return new JSONResponse($result);
		} catch (\Exception $e) {
			$this->logger->error('USDA FDC barcode lookup failed for "{code}": {message}', [
				'code' => $code,
				'message' => $e->getMessage(),
				'exception' => $e,
				'app' => 'calorietracker',
			]);
			return new JSONResponse(['error' => 'Barcode lookup failed'], Http::STATUS_BAD_GATEWAY);
		}
	}

	#[NoAdminRequired]
	#[UserRateLimit(limit: 20, period: 60)]
	public function search(string $query): JSONResponse {
		$query = trim($query);
		if (strlen($query) < 2) {
			return new JSONResponse([]);
		}

		$searchKey = 'offsearch:' . md5(strtolower($query));

		$cached = $this->cache->get($searchKey);
		if ($cached !== null) {
			return new JSONResponse($cached);
		}

		try {
			$client = $this->clientService->newClient();
			$response = $client->get(self::OFF_SEARCH_URL, [
				'query' => [
					'search_terms' => $query,
					'action'       => 'process',
					'json'         => '1',
					'page_size'    => '20',
					'fields'       => 'code,product_name,product_name_en,nutriments',
				],
				'headers' => ['User-Agent' => self::USER_AGENT],
				'timeout' => 15,
			]);

			$data = json_decode($response->getBody(), true);
			if (!is_array($data)) {
				throw new \RuntimeException('Open Food Facts returned a non-JSON response');
			}
			$products = isset($data['products']) && is_array($data['products'])
				? $data['products'] : [];

			$results = [];
			foreach ($products as $product) {
				if (!is_array($product)) {
					continue;
				}
				// Prefer the English name; fall back to the localised product_name
				$name = trim($product['product_name_en'] ?? $product['product_name'] ?? '');
				if ($name === '') {
					continue;
				}
				$name = mb_substr($name, 0, self::MAX_FOOD_NAME_LENGTH, 'UTF-8');

				$n    = isset($product['nutriments']) && is_array($product['nutriments'])
					? $product['nutriments'] : [];
				$kcal = $n['energy-kcal_100g'] ?? null;
				if ($kcal === null) {
					continue;
				}

				$results[] = [
					'source'          => 'off',
					'externalId'      => isset($product['code']) && $product['code'] !== '' ? (string) $product['code'] : null,
					'name'            => $name,
					'caloriesPer100g' => (int) round((float) $kcal),
					'proteinPer100g'  => isset($n['proteins_100g'])
						? (int) round((float) $n['proteins_100g']) : null,
					'carbsPer100g'    => isset($n['carbohydrates_100g'])
						? (int) round((float) $n['carbohydrates_100g']) : null,
					'fatPer100g'      => isset($n['fat_100g'])
						? (int) round((float) $n['fat_100g']) : null,
				];
			}

			$results = array_slice($results, 0, 10);

			$this->cache->set($searchKey, $results, self::TTL_SEARCH);

			return new JSONResponse($results);
		} catch (\Exception $e) {
			$this->logger->error('Open Food Facts search failed for query "{query}": {message}', [
				'query'     => $query,
				'message'   => $e->getMessage(),
				'exception' => $e,
				'app'       => 'calorietracker',
			]);
			return new JSONResponse(['error' => 'Search failed'], Http::STATUS_BAD_GATEWAY);
		}
	}
}
