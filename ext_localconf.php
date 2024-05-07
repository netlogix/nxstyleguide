<?php

use Netlogix\Nxstyleguide\Hooks\PageCacheEnhancer;

defined('TYPO3') || die();

call_user_func(static function (): void {
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['insertPageCacheContent'][] =
        PageCacheEnhancer::class . '->insertPageCacheContent';
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['pageLoadedFromCache'][] =
        PageCacheEnhancer::class . '->pageLoadedFromCache';
});
