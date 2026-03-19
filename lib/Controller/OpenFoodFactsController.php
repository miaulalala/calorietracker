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

		$searchKey = 'search:' . md5(strtolower($query));

		$cached = $this->cache->get($searchKey);
		if ($cached !== null) {
			return new JSONResponse($this->sortByLanguage($cached, $userLang));
		}

		try {
			$client = $this->clientService->newClient();
			$response = $client->get(self::OFF_SEARCH_URL, [
				'query' => [
					'search_terms' => $query,
					'search_simple' => '1',
					'action' => 'process',
					'json' => '1',
					'fields' => 'code,product_name,nutriments,lang',
					'page_size' => '10',
				],
				'headers' => ['User-Agent' => self::USER_AGENT],
				'timeout' => 15,
			]);

			$data = json_decode($response->getBody(), true);
			$products = $data['products'] ?? [];

			$results = [];
			foreach ($products as $product) {
				$name = trim($product['product_name'] ?? '');
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
					'lang' => $product['lang'] ?? '',
				];

				$barcode = trim($product['code'] ?? '');
				if ($barcode !== '') {
					$result['barcode'] = $barcode;
					$this->cache->set('product:' . $barcode, $result, self::TTL_PRODUCT);
				}

				$results[] = $result;
			}

			// Cache with lang included so sorting can be applied per user on retrieval
			$this->cache->set($searchKey, $results, self::TTL_SEARCH);

			return new JSONResponse($this->sortByLanguage($results, $userLang));
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

	/**
	 * Stable sort: products whose lang matches $userLang float to the top.
	 * The lang field is stripped before returning so it doesn't leak to the frontend.
	 *
	 * @param array<int, array<string, mixed>> $results
	 * @return array<int, array<string, mixed>>
	 */
	private function sortByLanguage(array $results, string $userLang): array {
		usort($results, static function (array $a, array $b) use ($userLang): int {
			$aMatch = ($a['lang'] ?? '') === $userLang ? 0 : 1;
			$bMatch = ($b['lang'] ?? '') === $userLang ? 0 : 1;
			return $aMatch - $bMatch;
		});

		return array_map(static function (array $r): array {
			unset($r['lang']);
			return $r;
		}, $results);
	}
}
