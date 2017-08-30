<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Class ilDclSelectionRecordFieldModel
 *
 * @author  Theodor Truffer <tt@studer-raimann.ch>
 */
abstract class ilDclSelectionRecordFieldModel extends ilDclBaseRecordFieldModel {

	// those should be overwritten by subclasses
	const PROP_SELECTION_TYPE = '';
	const PROP_SELECTION_OPTIONS = '';

	/**
	 * @return array|mixed|string
	 */
	public function getValue() {
		if ($this->getField()->isMulti() && !is_array($this->value)) {
			return array($this->value);
		}
		if (!$this->getField()->isMulti() && is_array($this->value)) {
			return (count($this->value) == 1) ? array_shift($this->value) : '';
		}
		return $this->value;
	}


	/**
	 * @param mixed $value
	 *
	 * @return string
	 */
	public function parseExportValue($value) {
		$values = ilDclSelectionOption::getValues($this->getField()->getId(), $value);
		return is_array($values) ? implode("; ", $values) : $values;
	}


	/**
	 * @param $excel
	 * @param $row
	 * @param $col
	 *
	 * @return array|int|int[]|string
	 */
	public function getValueFromExcel($excel, $row, $col){
		global $DIC;
		$lng = $DIC['lng'];
		$string = parent::getValueFromExcel($excel, $row, $col);
		$old = $string;
		if ($this->getField()->isMulti()) {
			$string = $this->getMultipleValuesFromString($string);
			$has_value = count($string);
		} else {
			$string = $this->getValueFromString($string);
			$has_value = $string;
		}

		if (!$has_value && $old) {
			$warning = "(" . $row . ", " . ilDataCollectionImporter::getExcelCharForInteger($col+1) . ") " . $lng->txt("dcl_no_such_reference") . " "
				. $old;
			return array('warning' => $warning);
		}

		return $string;
	}

	/**
	 * Copied from reference field and slightly adjusted.
	 *
	 * This method tries to get as many valid values out of a string separated by commata. This is problematic as a string value could contain commata itself.
	 * It is optimized to work with an exported list from this DataCollection. And works fine in most cases. Only areference list with the values "hello" and "hello, world"
	 * Will mess with it.
	 * @param $stringValues string
	 * @return int[]
	 */
	protected function getMultipleValuesFromString($stringValues) {
		$delimiter = strpos($stringValues, '; ') ? '; ' : ', ';
		$slicedStrings = explode($delimiter, $stringValues);
		$slicedReferences = array();
		$resolved = 0;
		for($i = 0; $i < count($slicedStrings); $i++) {
			//try to find a reference since the last resolved value separated by a comma.
			// $i = 1; $resolved = 0; $string = "hello, world, gaga" -> try to match "hello, world".
			$searchString = implode(array_slice($slicedStrings, $resolved, $i - $resolved + 1));
			if($ref = $this->getValueFromString($searchString)){
				$slicedReferences[] = $ref;
				$resolved = $i;
				continue;
			}

			//try to find a reference with the current index.
			// $i = 1; $resolved = 0; $string = "hello, world, gaga" -> try to match "world".
			$searchString = $slicedStrings[$i];
			if($ref = $this->getValueFromString($searchString)){
				$slicedReferences[] = $ref;
				$resolved = $i;
				continue;
			}
		}
		return $slicedReferences;
	}


	/**
	 * Copied from reference field and slightly adjusted.
	 *
	 * @param $string
	 *
	 * @return int|null|string
	 */
	protected function getValueFromString($string) {
		$options = $this->getField()->getProperty(static::PROP_SELECTION_OPTIONS);
		foreach ($options as $opt) {
			/** @var $opt ilDclSelectionOption */
			if ($opt->getValue() == $string) {
				return $opt->getOptId();
			}
		}
		return null;
	}
}