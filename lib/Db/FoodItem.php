<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\CalorieTracker\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getSource()
 * @method void setSource(string $source)
 * @method string|null getExternalId()
 * @method void setExternalId(?string $externalId)
 * @method string getName()
 * @method void setName(string $name)
 * @method int getCaloriesPer100g()
 * @method void setCaloriesPer100g(int $caloriesPer100g)
 * @method int|null getProteinPer100g()
 * @method void setProteinPer100g(?int $proteinPer100g)
 * @method int|null getCarbsPer100g()
 * @method void setCarbsPer100g(?int $carbsPer100g)
 * @method int|null getFatPer100g()
 * @method void setFatPer100g(?int $fatPer100g)
 */
class FoodItem extends Entity implements \JsonSerializable {
	protected string $userId = '';
	protected string $source = '';
	protected ?string $externalId = null;
	protected string $name = '';
	protected int $caloriesPer100g = 0;
	protected ?int $proteinPer100g = null;
	protected ?int $carbsPer100g = null;
	protected ?int $fatPer100g = null;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('caloriesPer100g', 'integer');
		$this->addType('proteinPer100g', 'integer');
		$this->addType('carbsPer100g', 'integer');
		$this->addType('fatPer100g', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id'              => $this->getId(),
			'source'          => $this->getSource(),
			'externalId'      => $this->getExternalId(),
			'name'            => $this->getName(),
			'caloriesPer100g' => $this->getCaloriesPer100g(),
			'proteinPer100g'  => $this->getProteinPer100g(),
			'carbsPer100g'    => $this->getCarbsPer100g(),
			'fatPer100g'      => $this->getFatPer100g(),
		];
	}
}
