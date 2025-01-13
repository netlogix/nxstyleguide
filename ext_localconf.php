<?php

declare(strict_types=1);

use Netlogix\Nxstyleguide\Cache\MetaDataState;

defined('TYPO3') || die();

$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][TYPO3\CMS\Frontend\Cache\MetaDataState::class]['className'] =
    MetaDataState::class;
