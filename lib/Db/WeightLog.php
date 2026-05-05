<?php

/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\CalorieTracker\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method float getWeightKg()
 * @method void setWeightKg(float $weightKg)
 * @method string getLoggedAt()
 * @method void setLoggedAt(string $loggedAt)
 * @method string|null getNote()
 * @method void setNote(?string $note)
 */
class WeightLog extends Entity implements \JsonSerializable {
	protected string $userId = '';
	protected float $weightKg = 0.0;
	protected string $loggedAt = '';
	protected ?string $note = null;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('weightKg', 'float');
	}

	public function jsonSerialize(): array {
		return [
			'id'       => $this->getId(),
			'weightKg' => $this->getWeightKg(),
			'loggedAt' => $this->getLoggedAt(),
			'note'     => $this->getNote(),
		];
	}
}
