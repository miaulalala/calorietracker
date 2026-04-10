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
use OCP\IConfig;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

/**
 * Proxy controller for the Nextcloud Cookbook app.
 * Provides recipe search, detail retrieval, and nutrition updates
 * by forwarding requests to the cookbook webapp API.
 */
class CookbookController extends Controller {

	public function __construct(
		IRequest $request,
		private IClientService $clientService,
		private IURLGenerator $urlGenerator,
		private IConfig $config,
		private ISession $session,
		private LoggerInterface $logger,
	) {
		parent::__construct('calorietracker', $request);
	}

	#[NoAdminRequired]
	public function search(string $query): JSONResponse {
		$query = trim($query);
		if (strlen($query) < 2) {
			return new JSONResponse([]);
		}

		try {
			$data = $this->cookbookGet('/apps/cookbook/webapp/search/' . urlencode($query));

			$results = [];
			foreach ($data as $recipe) {
				if (!is_array($recipe)) {
					continue;
				}
				$id = (int)($recipe['recipe_id'] ?? 0);
				$name = trim((string)($recipe['name'] ?? ''));
				if ($id <= 0 || $name === '') {
					continue;
				}
				$results[] = [
					'id' => $id,
					'name' => $name,
					'imageUrl' => $recipe['imageUrl'] ?? null,
				];
			}

			return new JSONResponse($results);
		} catch (\Exception $e) {
			$this->logger->error('Cookbook search failed for query "{query}": {message}', [
				'query' => $query,
				'message' => $e->getMessage(),
				'exception' => $e,
				'app' => 'calorietracker',
			]);
			return new JSONResponse(['error' => 'Cookbook search failed'], Http::STATUS_BAD_GATEWAY);
		}
	}

	#[NoAdminRequired]
	public function show(int $id): JSONResponse {
		try {
			$recipe = $this->cookbookGet('/apps/cookbook/webapp/recipes/' . $id);

			if (!is_array($recipe)) {
				return new JSONResponse(['error' => 'Recipe not found'], Http::STATUS_NOT_FOUND);
			}

			$nutrition = isset($recipe['nutrition']) && is_array($recipe['nutrition'])
				? $recipe['nutrition'] : [];

			return new JSONResponse([
				'id' => (int)($recipe['id'] ?? $id),
				'name' => $recipe['name'] ?? '',
				'recipeYield' => $recipe['recipeYield'] ?? null,
				'recipeIngredient' => $recipe['recipeIngredient'] ?? [],
				'nutrition' => $nutrition,
				'caloriesPer100g' => $this->extractNutrientValue($nutrition, 'calories'),
				'proteinPer100g' => $this->extractNutrientValue($nutrition, 'proteinContent'),
				'carbsPer100g' => $this->extractNutrientValue($nutrition, 'carbohydrateContent'),
				'fatPer100g' => $this->extractNutrientValue($nutrition, 'fatContent'),
			]);
		} catch (\Exception $e) {
			$this->logger->error('Cookbook recipe fetch failed for id {id}: {message}', [
				'id' => $id,
				'message' => $e->getMessage(),
				'exception' => $e,
				'app' => 'calorietracker',
			]);
			return new JSONResponse(['error' => 'Failed to load recipe'], Http::STATUS_BAD_GATEWAY);
		}
	}

	#[NoAdminRequired]
	public function updateNutrition(int $id): JSONResponse {
		try {
			// Get the full recipe first
			$recipe = $this->cookbookGet('/apps/cookbook/webapp/recipes/' . $id);

			if (!is_array($recipe)) {
				return new JSONResponse(['error' => 'Recipe not found'], Http::STATUS_NOT_FOUND);
			}

			// Read nutrition values from the request body
			$nutrition = $recipe['nutrition'] ?? [];
			if (!is_array($nutrition)) {
				$nutrition = [];
			}

			$calories = $this->request->getParam('calories');
			$protein = $this->request->getParam('protein');
			$carbs = $this->request->getParam('carbs');
			$fat = $this->request->getParam('fat');
			$servingSize = $this->request->getParam('servingSize');

			// Validate that numeric fields are actually numeric
			foreach (['calories' => $calories, 'protein' => $protein, 'carbs' => $carbs, 'fat' => $fat] as $field => $value) {
				if ($value !== null && !is_numeric($value)) {
					return new JSONResponse(
						['error' => "Invalid value for '$field': must be numeric"],
						Http::STATUS_BAD_REQUEST,
					);
				}
			}

			if ($calories !== null) {
				$nutrition['calories'] = round((float)$calories) . ' kcal';
			}
			if ($protein !== null) {
				$nutrition['proteinContent'] = round((float)$protein, 1) . ' g';
			}
			if ($carbs !== null) {
				$nutrition['carbohydrateContent'] = round((float)$carbs, 1) . ' g';
			}
			if ($fat !== null) {
				$nutrition['fatContent'] = round((float)$fat, 1) . ' g';
			}
			if ($servingSize !== null) {
				$nutrition['servingSize'] = (string)$servingSize;
			}

			$recipe['nutrition'] = $nutrition;

			// PUT the full recipe back
			$this->cookbookPut('/apps/cookbook/webapp/recipes/' . $id, $recipe);

			return new JSONResponse(['status' => 'ok']);
		} catch (\Exception $e) {
			$this->logger->error('Cookbook nutrition update failed for recipe {id}: {message}', [
				'id' => $id,
				'message' => $e->getMessage(),
				'exception' => $e,
				'app' => 'calorietracker',
			]);
			return new JSONResponse(['error' => 'Failed to update nutrition'], Http::STATUS_BAD_GATEWAY);
		}
	}

	/**
	 * Extract a numeric value from a schema.org nutrition field like "250 kcal" or "12 g".
	 */
	private function extractNutrientValue(array $nutrition, string $field): ?int {
		$value = $nutrition[$field] ?? null;
		if ($value === null || $value === '') {
			return null;
		}
		// schema.org values are strings like "250 kcal", "12 g"
		if (preg_match('/[\d.]+/', (string)$value, $m)) {
			return (int)round((float)$m[0]);
		}
		return null;
	}

	/**
	 * Build common request options for internal cookbook requests.
	 */
	private function buildRequestOptions(int $timeout = 15): array {
		$options = [
			'headers' => $this->buildHeaders(),
			'timeout' => $timeout,
		];

		// When allow_local_remote_servers is enabled and the target host is
		// loopback or private (typical for dev/docker setups with self-signed
		// certs), skip TLS verification.
		if ($this->config->getSystemValueBool('allow_local_remote_servers', false)) {
			$baseUrl = $this->urlGenerator->getBaseUrl();
			$host = parse_url($baseUrl, PHP_URL_HOST) ?: '';
			if ($this->isLocalHost($host)) {
				$options['verify'] = false;
			}
		}

		return $options;
	}

	/**
	 * Make a GET request to the cookbook app using the current user's session.
	 */
	private function cookbookGet(string $path): array {
		$baseUrl = $this->urlGenerator->getBaseUrl();
		$client = $this->clientService->newClient();

		$response = $client->get($baseUrl . '/index.php' . $path, $this->buildRequestOptions());

		$data = json_decode($response->getBody(), true);
		if (!is_array($data)) {
			throw new \RuntimeException('Cookbook returned invalid JSON');
		}
		return $data;
	}

	/**
	 * Make a PUT request to the cookbook app using the current user's session.
	 */
	private function cookbookPut(string $path, array $body): array {
		$baseUrl = $this->urlGenerator->getBaseUrl();
		$client = $this->clientService->newClient();

		$options = $this->buildRequestOptions();
		$options['json'] = $body;

		$response = $client->put($baseUrl . '/index.php' . $path, $options);

		$data = json_decode($response->getBody(), true);
		return is_array($data) ? $data : [];
	}

	/**
	 * Build request headers that forward the current user's session cookie
	 * so that the cookbook app authenticates the request correctly.
	 */
	private function buildHeaders(): array {
		$headers = [
			'OCS-APIRequest' => 'true',
			'Accept' => 'application/json',
		];

		// Forward the cookie header from the incoming request
		$cookieHeader = $this->request->getHeader('Cookie');
		if ($cookieHeader !== '') {
			$headers['Cookie'] = $cookieHeader;
		}

		// Forward the requesttoken for CSRF protection
		$requestToken = $this->session->get('requesttoken');
		if ($requestToken !== null) {
			$headers['requesttoken'] = $requestToken;
		}

		return $headers;
	}

	/**
	 * Check whether a hostname resolves to a loopback or private address.
	 */
	private function isLocalHost(string $host): bool {
		if ($host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
			return true;
		}

		$ip = gethostbyname($host);
		if ($ip === $host) {
			// Resolution failed — treat as non-local
			return false;
		}

		// Check RFC 1918 / loopback ranges
		return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_NO_PRIV_RANGE) === false;
	}
}
