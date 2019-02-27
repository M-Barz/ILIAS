<?php
declare(strict_types=1);
/* Copyright (c) 1998-2019 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * @author  Niels Theen <ntheen@databay.de>
 */

namespace ILIAS\Data;


class IntegerRange
{
	/**
	 * @var int
	 */
	private $minimum;

	/**
	 * @var int
	 */
	private $maximum;

	/**
	 * @param int $minimum
	 * @param int $maximum
	 * @throws \InvalidArgumentException
	 */
	public function __construct(int $minimum, int $maximum)
	{
		if ($maximum < $minimum) {
			throw new \InvalidArgumentException(sprintf('The maximum value("%s") is not a integer', $maximum));
		}

		$this->minimum = $minimum;
		$this->maximum = $maximum;
	}

	/**
	 * @return int
	 */
	public function minimumAsInteger() : int
	{
		return $this->minimum;
	}

	/**
	 * @return int
	 */
	public function maximumAsInteger() : int
	{
		return $this->maximum;
	}
}
