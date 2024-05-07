# TYPO3 Extension `nxstyleguide`

[![TYPO3 V12](https://img.shields.io/badge/TYPO3-12-orange.svg)](https://get.typo3.org/version/12)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%208.1-8892BF.svg)](https://php.net/)
[![GitHub CI status](https://github.com/netlogix/nxstyleguide/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/netlogix/nxstyleguide/actions)


## Compatibility

The current version of this extension has been tested in TYPO3 12 on PHP 8.1, 8.2 and 8.3.

The following patch for the TypoScriptFrontendController is currently required for the extension to be fully functional:

```json
{
  // ...
  "extra": {
    "patches": {
      // ...
      "typo3/cms-frontend": {
        "[FEATURE] Add TSFE Hook insertPageCacheContent": "patches/typo3/cms/FEATURE-TSFE-insertPageCacheContent-hook.patch"
      }
      // ...
    }
  }
  // ...
}
```

```diff
--- a/Classes/Controller/TypoScriptFrontendController.php
+++ b/Classes/Controller/TypoScriptFrontendController.php
@@ -2657,6 +2657,11 @@
         }
         // Add the cache themselves as well, because they are fetched by getPageCacheTags()
         $cacheData['cacheTags'] = $this->pageCacheTags;
+
+        $_params = ['pObj' => &$this, 'cache_data' => &$cacheData];
+        foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['insertPageCacheContent'] ?? [] as $_funcRef) {
+            GeneralUtility::callUserFunction($_funcRef, $_params, $this);
+        }
         $this->pageCache->set($this->newHash, $cacheData, $this->pageCacheTags, $expirationTstamp - $GLOBALS['EXEC_TIME']);
     }
```