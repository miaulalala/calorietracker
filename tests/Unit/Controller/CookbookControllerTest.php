<?php

/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\CalorieTracker\Tests\Unit\Controller;

use OCA\CalorieTracker\Controller\CookbookController;
use OCP\AppFramework\Http;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CookbookControllerTest extends TestCase {
	private IRequest&MockObject $request;
	private IClientService&MockObject $clientService;
	private IClient&MockObject $client;
	private IURLGenerator&MockObject $urlGenerator;
	private IConfig&MockObject $config;
	private ISession&MockObject $session;
	private CookbookController $controller;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->clientService = $this->createMock(IClientService::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->config = $this->createMock(IConfig::class);
		$this->session = $this->createMock(ISession::class);
		$logger = $this->createMock(LoggerInterface::class);

		$this->urlGenerator->method('getBaseUrl')->willReturn('https://cloud.example.com');
		$this->request->method('getHeader')->willReturn('');
		$this->session->method('get')->willReturn(null);
		$this->config->method('getSystemValueBool')->willReturn(false);

		$this->client = $this->createMock(IClient::class);
		$this->clientService->method('newClient')->willReturn($this->client);

		$this->controller = new CookbookController(
			$this->request,
			$this->clientService,
			$this->urlGenerator,
			$this->config,
			$this->session,
			$logger,
		);
	}

	// ── search() ───────────────────────────────────────────────────────────────

	public function testSearchRejectsShortQuery(): void {
		$response = $this->controller->search('a');
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$this->assertEquals([], $response->getData());
	}

	public function testSearchReturnsFilteredResults(): void {
		$apiData = [
			['recipe_id' => 1, 'name' => 'Pasta Carbonara', 'imageUrl' => '/img/1.jpg'],
			['recipe_id' => 0, 'name' => 'Invalid'],
			['recipe_id' => 2, 'name' => ''],
			'not-an-array',
			['recipe_id' => 3, 'name' => 'Tomato Soup'],
		];

		$apiResponse = $this->createMock(IResponse::class);
		$apiResponse->method('getBody')->willReturn(json_encode($apiData));
		$this->client->method('get')->willReturn($apiResponse);

		$response = $this->controller->search('pasta');
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());

		$data = $response->getData();
		$this->assertCount(2, $data);
		$this->assertEquals(1, $data[0]['id']);
		$this->assertEquals('Pasta Carbonara', $data[0]['name']);
		$this->assertEquals(3, $data[1]['id']);
		$this->assertEquals('Tomato Soup', $data[1]['name']);
	}

	public function testSearchReturns502OnFailure(): void {
		$this->client->method('get')->willThrowException(new \RuntimeException('Connection refused'));

		$response = $this->controller->search('pasta');
		$this->assertEquals(Http::STATUS_BAD_GATEWAY, $response->getStatus());
	}

	// ── show() ─────────────────────────────────────────────────────────────────

	public function testShowReturnsRecipeWithNutrition(): void {
		$recipe = [
			'id' => 42,
			'name' => 'Test Recipe',
			'recipeYield' => '4',
			'recipeIngredient' => ['200g chicken', '100g rice'],
			'nutrition' => [
				'calories' => '350 kcal',
				'proteinContent' => '25.5 g',
				'carbohydrateContent' => '40 g',
				'fatContent' => '8 g',
			],
		];

		$apiResponse = $this->createMock(IResponse::class);
		$apiResponse->method('getBody')->willReturn(json_encode($recipe));
		$this->client->method('get')->willReturn($apiResponse);

		$response = $this->controller->show(42);
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());

		$data = $response->getData();
		$this->assertEquals(42, $data['id']);
		$this->assertEquals('Test Recipe', $data['name']);
		$this->assertEquals('4', $data['recipeYield']);
		$this->assertEquals(350, $data['caloriesPerServing']);
		$this->assertEquals(26, $data['proteinPerServing']);
		$this->assertEquals(40, $data['carbsPerServing']);
		$this->assertEquals(8, $data['fatPerServing']);
	}

	public function testShowReturnsNullForMissingNutrition(): void {
		$recipe = [
			'id' => 10,
			'name' => 'No Nutrition',
			'recipeYield' => null,
			'recipeIngredient' => [],
		];

		$apiResponse = $this->createMock(IResponse::class);
		$apiResponse->method('getBody')->willReturn(json_encode($recipe));
		$this->client->method('get')->willReturn($apiResponse);

		$response = $this->controller->show(10);
		$data = $response->getData();
		$this->assertNull($data['caloriesPerServing']);
		$this->assertNull($data['proteinPerServing']);
	}

	public function testShowReturns502OnFailure(): void {
		$this->client->method('get')->willThrowException(new \RuntimeException('timeout'));

		$response = $this->controller->show(1);
		$this->assertEquals(Http::STATUS_BAD_GATEWAY, $response->getStatus());
	}

	// ── updateNutrition() ──────────────────────────────────────────────────────

	public function testUpdateNutritionWritesBackToRecipe(): void {
		$recipe = [
			'id' => 42,
			'name' => 'Test',
			'nutrition' => [],
		];

		$getResponse = $this->createMock(IResponse::class);
		$getResponse->method('getBody')->willReturn(json_encode($recipe));

		$putResponse = $this->createMock(IResponse::class);
		$putResponse->method('getBody')->willReturn(json_encode([]));

		$this->client->expects($this->once())->method('get')->willReturn($getResponse);
		$this->client->expects($this->once())->method('put')
			->with(
				$this->stringContains('/cookbook/webapp/recipes/42'),
				$this->callback(function (array $options) {
					$json = $options['json'] ?? [];
					$nutrition = $json['nutrition'] ?? [];
					return ($nutrition['calories'] ?? '') === '250 kcal'
						&& ($nutrition['proteinContent'] ?? '') === '20 g'
						&& ($nutrition['servingSize'] ?? '') === '1/4 of recipe';
				}),
			)
			->willReturn($putResponse);

		$response = $this->controller->updateNutrition(42, 250.0, 20.0, null, null, '1/4 of recipe');
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$this->assertEquals('ok', $response->getData()['status']);
	}

	public function testUpdateNutritionReturns502OnFailure(): void {
		$this->client->method('get')->willThrowException(new \RuntimeException('timeout'));

		$response = $this->controller->updateNutrition(1, 100.0);
		$this->assertEquals(Http::STATUS_BAD_GATEWAY, $response->getStatus());
	}
}
