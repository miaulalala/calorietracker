<?php

/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\CalorieTracker\Tests\Unit\Controller;

use OCA\CalorieTracker\Controller\TdeeProfileController;
use OCP\AppFramework\Http;
use OCP\IConfig;
use OCP\IRequest;
use OCP\Security\ICrypto;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TdeeProfileControllerTest extends TestCase {
	private IConfig&MockObject $config;
	private ICrypto&MockObject $crypto;
	private TdeeProfileController $controller;

	protected function setUp(): void {
		$request = $this->createMock(IRequest::class);
		$this->config = $this->createMock(IConfig::class);
		$this->crypto = $this->createMock(ICrypto::class);
		$this->controller = new TdeeProfileController($request, $this->config, $this->crypto, 'user1');
	}

	private function validParams(): array {
		return ['sex' => 'male', 'age' => 30, 'height' => 180.0, 'weight' => 75.0, 'activity' => 'moderate', 'goal' => 'maintain'];
	}

	// ── GET ────────────────────────────────────────────────────────────────────

	public function testGetReturnsNullWhenUnset(): void {
		$this->config->method('getUserValue')->willReturn('');

		$response = $this->controller->get();
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$this->assertNull($response->getData());
	}

	public function testGetReturnsNullWhenDecryptionFails(): void {
		$this->config->method('getUserValue')->willReturn('encrypted-blob');
		$this->crypto->method('decrypt')->willThrowException(new \Exception('bad'));

		$response = $this->controller->get();
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$this->assertNull($response->getData());
	}

	public function testGetReturnsNullWhenJsonInvalid(): void {
		$this->config->method('getUserValue')->willReturn('encrypted-blob');
		$this->crypto->method('decrypt')->willReturn('not-json');

		$response = $this->controller->get();
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$this->assertNull($response->getData());
	}

	public function testGetReturnsProfile(): void {
		$profile = $this->validParams();
		$this->config->method('getUserValue')->willReturn('encrypted-blob');
		$this->crypto->method('decrypt')->willReturn(json_encode($profile));

		$response = $this->controller->get();
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$this->assertEquals($profile, $response->getData());
	}

	// ── PUT (save) ─────────────────────────────────────────────────────────────

	public function testSaveStoresEncryptedProfile(): void {
		$params = $this->validParams();
		$this->crypto->method('encrypt')->willReturn('encrypted-blob');
		$this->config->expects($this->once())
			->method('setUserValue')
			->with('user1', 'calorietracker', 'tdee_profile', 'encrypted-blob');

		$response = $this->controller->save(...$params);
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$this->assertEquals($params, $response->getData());
	}

	public function testSaveRejectsInvalidSex(): void {
		$params = $this->validParams();
		$params['sex'] = 'other';

		$response = $this->controller->save(...$params);
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testSaveRejectsInvalidActivity(): void {
		$params = $this->validParams();
		$params['activity'] = 'ultra';

		$response = $this->controller->save(...$params);
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testSaveRejectsInvalidGoal(): void {
		$params = $this->validParams();
		$params['goal'] = 'bulk';

		$response = $this->controller->save(...$params);
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testSaveRejectsAgeTooLow(): void {
		$params = $this->validParams();
		$params['age'] = 5;

		$response = $this->controller->save(...$params);
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testSaveRejectsAgeTooHigh(): void {
		$params = $this->validParams();
		$params['age'] = 200;

		$response = $this->controller->save(...$params);
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testSaveRejectsHeightOutOfRange(): void {
		$params = $this->validParams();
		$params['height'] = 10.0;

		$response = $this->controller->save(...$params);
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testSaveRejectsWeightOutOfRange(): void {
		$params = $this->validParams();
		$params['weight'] = 600.0;

		$response = $this->controller->save(...$params);
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testSaveReturns500WhenEncryptionFails(): void {
		$this->crypto->method('encrypt')->willThrowException(new \Exception('crypto fail'));

		$response = $this->controller->save(...$this->validParams());
		$this->assertEquals(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
	}

	public function testSaveAndGetRoundTrip(): void {
		$params = $this->validParams();
		$json = json_encode($params);

		$this->crypto->method('encrypt')->willReturn('encrypted');
		$this->crypto->method('decrypt')->with('encrypted', 'user1')->willReturn($json);
		$this->config->method('getUserValue')->willReturn('encrypted');

		$saveResponse = $this->controller->save(...$params);
		$this->assertEquals(Http::STATUS_OK, $saveResponse->getStatus());

		$getResponse = $this->controller->get();
		$this->assertEquals($params, $getResponse->getData());
	}
}
