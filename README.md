# Transliterate Slugger for TYPO3

[![License](https://poser.pugx.org/t3brightside/transliterate-slugger/license)](LICENSE.txt)
[![Packagist](https://img.shields.io/packagist/v/t3brightside/transliterate-slugger.svg?style=flat)](https://packagist.org/packages/t3brightside/transliterate-slugger)
[![Downloads](https://poser.pugx.org/t3brightside/transliterate-slugger/downloads)](https://packagist.org/packages/t3brightside/transliterate-slugger)
[![Brightside](https://img.shields.io/badge/by-t3brightside.com-orange.svg?style=flat)](https://t3brightside.com)

By default, TYPO3 hardcodes German phonetic rules for URLs (converting `ä` to `ae`, `ö` to `oe`, etc.). This breaks URL expectations for Estonian, Baltic, and Nordic languages, which prefer simple accent stripping (`ä` to `a`, `ö` to `o`).

This extension automatically fixes this by intercepting **all slug fields** in TYPO3 (Pages, News, etc.) and processing them through your server's native PHP ICU transliteration engine. It safely strips accents for global languages while correctly routing native German translations to their required format.

## Dependencies

This extension requires the following to be installed on your server/environment:

- **PHP Extension:** `ext-intl` (Required to run PHP's native `Transliterator` class)

## Install

1. **Install via Composer:**
   Run the following command in your project root:

    ```bash
    composer require brightside/transliterate-slugger
    ```

2. **Dump Autoload:**
   Ensure TYPO3 picks up the new dependency injection configurations:

    ```bash
    composer dump-autoload
    ```

3. **Clear TYPO3 Caches:**
   Go to the TYPO3 Backend:
    - Navigate to **Admin Tools > Maintenance**
    - Click **Flush TYPO3 and PHP Cache**

Once installed and the cache is cleared, the extension works automatically in the background. No further configuration is required.

## Sources

- [GitHub](https://github.com/t3brightside/transliterate_slugger)
- [Packagist](https://packagist.org/packages/t3brightside/transliterate_slugger)
- [TER](https://extensions.typo3.org/extension/transliterate_slugger/)

## Development & maintenance

[Brightside OÜ – TYPO3 development and hosting specialised web agency](https://t3brightside.com/)
