<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010 Francois Suter (Cobweb) <typo3@cobweb.ch>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

/**
 * API to retrieve values stored in FlexForm structures
 *
 * @author		Francois Suter (Cobweb) <typo3@cobweb.ch>
 * @package		TYPO3
 * @subpackage	tx_getflexformvalue
 *
 * $Id: class.tx_getflexformvalue_getter.php 32167 2010-04-13 13:13:44Z francois $
 */
class tx_getflexformvalue_getter {

	/**
	 * This method is meant to be called as a userFunc, for example from TypoScript
	 * Given a number of parameters, it will return the desired value from the FlexForm field,
	 * if possible
	 * 
	 * @param	array	$parameters: call parameters
	 * @param	object	$pObj: back-reference to the calling object
	 * @return	string	The value extracted from the FlexForm (empty if not found)
	 */
	public function getValue($content, $parameters) {
			// Perform initializations and define some default values
		$value = '';
		$dbTable = 'pages';
		$dbField = 'tx_templavoila_flex';
		$dbUid = $GLOBALS['TSFE']->id;
		$langDisable = 0;
		$langChildren = 1;
		$sheet = 'sDEF';
		$languageIndex = '';
		$valueIndex = '';
			// Get values from call parameters, when defined
		if (!empty($parameters['dbTable'])) {
			$dbTable = $parameters['dbTable'];
		}
		if (!empty($parameters['dbField'])) {
			$dbField = $parameters['dbField'];
		}
		if (!empty($parameters['dbUid'])) {
			$dbUid = $parameters['dbUid'];
		}
		if (!empty($parameters['langDisable'])) {
			$langDisable = 1;
		}
		if (isset($parameters['langChildren'])) {
			$langChildren = intval($parameters['langChildren']);
		}
		if (!empty($parameters['languageIndex'])) {
			$languageIndex = $parameters['languageIndex'];
		}
		if (!empty($parameters['valueIndex'])) {
			$valueIndex = $parameters['valueIndex'];
		}
		if (!empty($parameters['sheet'])) {
			$sheet = $parameters['sheet'];
		}
		$fieldName = $parameters['field'];
		$debug = !empty($parameters['debug']);
		try {
			$value = self::getValueFromField($dbTable, $dbField, $dbUid, $langDisable, $langChildren, $fieldName, $sheet, $languageIndex, $valueIndex);
		}
		catch (Exception $e) {
				// If in debug mode, send back the error message
			if ($debug) {
				$value = $e->getMessage();
			}
		}
		return $value;
	}

	public static function getValueFromField($dbTable, $dbField, $dbUid, $langDisable, $langChildren, $fieldName, $sheet = 'sDEF', $languageIndex = '', $valueIndex = '') {
		$value = '';
			// Get the FlexForm field's content from the database
			// NOTE: this may throw an exception, but we let it bubble up
		$flexFormField = self::getFlexFormField($dbTable, $dbField, $dbUid);
			// If translations are disabled, override values of language and value indices,
			// because it will always be "DEF"
			// Get the value with these parameters
		if ($langDisable == 1) {
			$languageIndex = 'lDEF';
			$valueIndex = 'vDEF';
			$value = self::extractValueFromFlexFormField($flexFormField, $fieldName, $sheet, $languageIndex, $valueIndex);

			// If translations are not disabled, we need to take into account what
			// translation paradigm is used (free or bound)
			// According to each paradigm try getting the value from the translation first,
			// and then from the default, except if an index was explicitely defined
		} else {
				// Bound translation paradigm
				// In this case the language index is set (lDEF), but the value index varies
			if ($langChildren == 1) {
				$languageIndex = 'lDEF';
					// If the value index is not defined, act according to current language
				if (empty($valueIndex)) {
						// Current language is default language, use default value index
					if ($GLOBALS['TSFE']->sys_language_uid == 0) {
						$valueIndex = 'vDEF';
						$value = self::extractValueFromFlexFormField($flexFormField, $fieldName, $sheet, $languageIndex, $valueIndex);

						// Current language is not default
						// Try to retrieve value for current language, fall back on default if not found
					} else {
						$valueIndex = 'v' . $GLOBALS['TSFE']->sys_language_isocode;
						try {
							$value = self::extractValueFromFlexFormField($flexFormField, $fieldName, $sheet, $languageIndex, $valueIndex);
						}
						catch (Exception $e) {
							$valueIndex = 'vDEF';
							$value = self::extractValueFromFlexFormField($flexFormField, $fieldName, $sheet, $languageIndex, $valueIndex);
						}
					}

					// The value index was explicitely defined, use it as is
				} else {
					$value = self::extractValueFromFlexFormField($flexFormField, $fieldName, $sheet, $languageIndex, $valueIndex);
				}

				// Free translation paradigm
				// In this case the value index is set (vDEF), but the language index varies
			} else {
				$valueIndex = 'vDEF';
					// If the language index is not defined, act according to current language
				if (empty($languageIndex)) {
						// Current language is default language, use default value index
					if ($GLOBALS['TSFE']->sys_language_uid == 0) {
						$languageIndex = 'lDEF';
						$value = self::extractValueFromFlexFormField($flexFormField, $fieldName, $sheet, $languageIndex, $valueIndex);

						// Current language is not default
						// Try to retrieve value for current language, fall back on default if not found
					} else {
						$languageIndex = 'l' . $GLOBALS['TSFE']->sys_language_isocode;
						try {
							$value = self::extractValueFromFlexFormField($flexFormField, $fieldName, $sheet, $languageIndex, $valueIndex);
						}
						catch (Exception $e) {
							$valueIndex = 'lDEF';
							$value = self::extractValueFromFlexFormField($flexFormField, $fieldName, $sheet, $languageIndex, $valueIndex);
						}
					}

					// The language index was explicitely defined, use it as is
				} else {
					$value = self::extractValueFromFlexFormField($flexFormField, $fieldName, $sheet, $languageIndex, $valueIndex);
				}
			}
		}
		return $value;
	}

	/**
	 * This method parses the XML contained in the FlexForm field to retrieve
	 * the desired value. The parameters follow the structure logic of FlexForm XML:
	 *
	 *		- sheet name (e.g. "sDEF")
	 *		- language index (e.g. "lDEF")
	 *		- field name
	 *		- value index name (e.g. "vDEF")
	 *
	 * @param	string	$flexFormField: XML FlexForm data
	 * @param	string	$fieldName: name of the FlexForm field to fetch a value for
	 * @param	string	$sheet: name of the sheet on which the field is located
	 * @param	string	$languageIndex: name of the language index to fetch the data for
	 * @param	string	$valueIndex: name of the index attribute that identifies the desired value node
	 * @return	string	The value found
	 */
	public static function extractValueFromFlexFormField($flexFormField, $fieldName, $sheet = 'sDEF', $languageIndex = 'lDEF', $valueIndex = 'vDEF') {
//t3lib_div::debug(func_get_args());
		$value = '';
		/**
		 * Load XML from FlexForm field into a SimpleXML object
		 *
		 * @var	SimpleXMLElement
		 */
		$xmlObject = simplexml_load_string($flexFormField);
			// Assemble xpath query for fetching the right node
		$xpath = 'data/sheet[@index=\'' . $sheet . '\']';
		$xpath .= '/language[@index=\'' . $languageIndex . '\']';
		$xpath .= '/field[@index=\'' . $fieldName . '\']';
		$xpath .= '/value[@index=\'' . $valueIndex . '\']';
		$node = $xmlObject->xpath($xpath);
			// If no node was found, throw exception
		if ($node === FALSE) {
			throw new Exception('No node found for path: ' . $xpath, 1270669052);

			// If a node was found, get its value
		} else {
			$value = (string)$node[0];
				// If the node didn't contain anything, also throw an Exception
			if ($value === '') {
				throw new Exception('No value in node for path: ' . $xpath, 1270669394);
			}
		}
		return $value;
	}

	/**
	 * This method fetches a given field from a given database record
	 * 
	 * @param	string	$dbTable: name of the table to fetch the record from
	 * @param	string	$dbField: name of the field to retrieve
	 * @param	integer	$dbUid: primary key of the record to fetch
	 * @return	string	content of the chosen database field
	 */
	public static function getFlexFormField($dbTable, $dbField, $dbUid) {
		$flexFormField = '';
			// Sanitize input
		$field = $GLOBALS['TYPO3_DB']->quoteStr($dbField, $dbTable);
		$table = $GLOBALS['TYPO3_DB']->quoteStr($dbTable, $dbTable);
		$uid = intval($dbUid);
			// Throw error if some data was missing
		if (empty($table) || empty($field) || empty($uid)) {
			$errors = array();
			if (empty($table)) {
				$errors[] = 'missing table';
			}
			if (empty($field)) {
				$errors[] = 'missing field';
			}
			if (empty($uid)) {
				$errors[] = 'missing uid';
			}
			$message = 'Incomplete query data: ' . implode(', ', $errors);
			throw new Exception($message, 1270650645);
		}
			// Get the record from the database
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($field, $table, 'uid = ' . $uid);
			// If a record was found fet flexform field value from it
		if ($res && $GLOBALS['TYPO3_DB']->sql_num_rows($res) > 0) {
			$record = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
			$flexFormField = $record[$dbField];

			// No record was found, throw an exception
		} else {
			$message = 'No record found with query: ' . $GLOBALS['TYPO3_DB']->SELECTquery($field, $table, 'uid = ' . $uid);
			throw new Exception($message, 1270648716);
		}
		return $flexFormField;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/getflexformvalue/class.tx_getflexformvalue_getter.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/getflexformvalue/class.tx_getflexformvalue_getter.php']);
}

?>