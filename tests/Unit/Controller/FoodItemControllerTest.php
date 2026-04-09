<?php

/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\CalorieTracker\Tests\Unit\Controller;

use OCA\CalorieTracker\Controller\FoodItemController;
use OCA\CalorieTracker\Db\FoodItem;
use OCA\CalorieTracker\Db\FoodItemMapper;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FoodItemControllerTest extends TestCase {
	private FoodItemMapper&MockObject $mapper;
	private FoodItemController $controller;

	protected function setUp(): void {
		$request = $this->createMock(IRequest::class);
		$this->mapper = $this->createMock(FoodItemMapper::class);
		$this->controller = new FoodItemController($request, $this->mapper, 'user1');
	}

	private function makeItem(int $id = 1): FoodItem {
		$item = new FoodItem();
		$item->setId($id);
		$item->setUserId('user1');
		$item->setSource('usda_fdc');
		$item->setName('Banana');
		$item->setCaloriesPer100g(89);
		return $item;
	}

	public function testIndexDefaultSortRecent(): void {
		$items = [$this->makeItem(1), $this->makeItem(2)];
		$this->mapper->expects($this->once())
			->method('findRecentForUser')
			->with('user1', 25)
			->willReturn($items);

		$response = $this->controller->index();
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$this->assertCount(2, $response->getData());
	}

	public function testIndexSortFrequent(): void {
		$this->mapper->expects($this->once())
			->method('findFrequentForUser')
			->with('user1', 25)
			->willReturn([]);

		$response = $this->controller->index('frequent');
		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}

	public function testIndexInvalidSort(): void {
		$response = $this->controller->index('alphabetical');
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testIndexClampsLimit(): void {
		$this->mapper->expects($this->once())
			->method('findRecentForUser')
			->with('user1', 50)
			->willReturn([]);

		$this->controller->index('recent', 999);
	}

	public function testIndexClampsMinLimit(): void {
		$this->mapper->expects($this->once())
			->method('findRecentForUser')
			->with('user1', 1)
			->willReturn([]);

		$this->controller->index('recent', 0);
	}
}
