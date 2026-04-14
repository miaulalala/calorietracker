<?php

/**
 * SPDX-FileCopyrightText: 2026 Anna Larch
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\CalorieTracker\Service;

use OCA\CalorieTracker\Db\WeightLog;
use OCA\CalorieTracker\Db\WeightLogMapper;
use OCP\AppFramework\Db\DoesNotExistException;

class WeightLogService {
	private const MIN_WEIGHT_KG = 20.0;
	private const MAX_WEIGHT_KG = 500.0;
	private const MAX_NOTE_LENGTH = 255;

	public function __construct(
		private WeightLogMapper $mapper,
	) {
	}

	public function getLatest(string $userId): ?WeightLog {
		return $this->mapper->findLatestForUser($userId);
	}

	/**
	 * @return WeightLog[]
	 */
	public function getForDateRange(string $userId, string $from, string $to): array {
		$this->validateDate($from);
		$this->validateDate($to);
		return $this->mapper->findForUserInRange($userId, $from, $to);
	}

	/**
	 * Log weight for a date. If a record already exists for that date, update it.
	 */
	public function log(string $userId, float $weightKg, string $date, ?string $note = null): WeightLog {
		$this->validateDate($date);
		$this->validateWeight($weightKg);
		$weightKg = round($weightKg, 1);

		if ($note !== null) {
			$note = mb_substr(trim($note), 0, self::MAX_NOTE_LENGTH, 'UTF-8');
			if ($note === '') {
				$note = null;
			}
		}

		$existing = $this->mapper->findForUserOnDate($userId, $date);
		if ($existing !== null) {
			$existing->setWeightKg($weightKg);
			$existing->setNote($note);
			return $this->mapper->update($existing);
		}

		$entry = new WeightLog();
		$entry->setUserId($userId);
		$entry->setWeightKg($weightKg);
		$entry->setLoggedAt($date);
		$entry->setNote($note);
		return $this->mapper->insert($entry);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function delete(int $id, string $userId): WeightLog {
		$entry = $this->mapper->findForUser($id, $userId);
		return $this->mapper->delete($entry);
	}

	private function validateDate(string $date): void {
		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
			throw new \InvalidArgumentException('Invalid date format. Expected YYYY-MM-DD.');
		}
		[$year, $month, $day] = explode('-', $date);
		if (!checkdate((int)$month, (int)$day, (int)$year)) {
			throw new \InvalidArgumentException('Invalid date. Expected a valid calendar date.');
		}
	}

	private function validateWeight(float $weight): void {
		if ($weight < self::MIN_WEIGHT_KG || $weight > self::MAX_WEIGHT_KG) {
			throw new \InvalidArgumentException(
				'Weight must be between ' . self::MIN_WEIGHT_KG . ' and ' . self::MAX_WEIGHT_KG . ' kg.'
			);
		}
	}
}
