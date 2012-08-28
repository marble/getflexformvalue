<?php
// $Id: ext_localconf.php 32009 2010-04-07 20:55:29Z francois $
if (!defined ('TYPO3_MODE')) {
	die ('Access denied.');
}

if (TYPO3_MODE == 'FE') {
	require_once(t3lib_extMgm::extPath($_EXTKEY, 'class.tx_getflexformvalue_getter.php'));
}
?>