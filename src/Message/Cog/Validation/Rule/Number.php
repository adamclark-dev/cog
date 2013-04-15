<?php

namespace Message\Cog\Validation\Rule;

use Message\Cog\Validation\CollectionInterface;
use Message\Cog\Validation\Loader;
use Message\Cog\Validation\Check\Type as CheckType;

/**
* Rules
*/
class Number implements CollectionInterface
{
	public function register(Loader $loader)
	{
		$loader->registerRule('min', array($this, 'min'), '%s must%s be equal to or greater than %s.')
			->registerRule('max', array($this, 'max'), '%s must%s be less than or equal to %s.')
			->registerRule('between', array($this, 'between'), '%s must%s be between %s and %s.');
	}

	public function min($var, $min)
	{
		CheckType::checkNumeric($var, '$var');
		CheckType::checkNumeric($min, '$min');

		return $var >= $min;
	}

	public function max($var, $max)
	{
		CheckType::checkNumeric($var, '$var');
		CheckType::checkNumeric($max, '$max');

		return $var <= $max;
	}

	public function between($var, $min, $max)
	{
		CheckType::checkNumeric($var, '$var');
		CheckType::checkNumeric($min, '$min');
		CheckType::checkNumeric($max, '$max');

		if ($min >= $max) {
			throw new \Exception(__CLASS__ . '::' . __METHOD__ . ' - $max must be greater than $min');
		}

		return $this->min($var, $min) && $this->max($var, $max);
	}

}