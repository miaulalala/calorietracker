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
use OCP\AppFramework\Http\JSONResponse;
use OCP\Http\Client\IClientService;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * Searches the Open Food Facts database for packaged and branded food products.
 * Results are normalised to per-100 g values to match the USDA FDC format.
 */
class OpenFoodFactsController extends Controller {
	private const OFF_SEARCH_URL = 'https://world.openfoodfacts.org/cgi/search.pl';

	private const USER_AGENT = 'NextcloudCalorieTracker/1.0 (https://nextcloud.com)';
	private const TTL_SEARCH  = 7 * 24 * 3600; // 7 d

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
					'fields'       => 'product_name,product_name_en,nutriments',
				],
				'headers' => ['User-Agent' => self::USER_AGENT],
				'timeout' => 15,
			]);

			$data = json_decode($response->getBody(), true);
			if (!is_array($data)) {
				throw new \RuntimeException('Open Food Facts returned a non-JSON response');
			}
			$products = $data['products'] ?? [];

			$results = [];
			foreach ($products as $product) {
				// Prefer the English name; fall back to the localised product_name
				$name = trim($product['product_name_en'] ?? $product['product_name'] ?? '');
				if ($name === '') {
					continue;
				}

				$n    = $product['nutriments'] ?? [];
				$kcal = $n['energy-kcal_100g'] ?? null;
				if ($kcal === null) {
					continue;
				}

				$results[] = [
					'source'          => 'off',
					'externalId'      => null,
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
