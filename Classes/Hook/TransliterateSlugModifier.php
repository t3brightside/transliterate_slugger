<?php
namespace Brightside\TransliterateSlugger\Hook;

use Symfony\Component\String\Slugger\AsciiSlugger;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TransliterateSlugModifier
{
    /**
     * Dynamically applies local transliteration maps matching the page language context.
     */
    public function modifySlug(array $params): string
    {
        // 1. Fetch Language ID and Page ID from the TYPO3 record state
        $languageUid = (int)($params['record']['sys_language_uid'] ?? 0);
        $pageUid = (int)($params['record']['uid'] ?? $params['pid'] ?? 0);

        // 2. Resolve the modern ISO language code via TYPO3 Site Configurations
        try {
            $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
            $site = $siteFinder->getSiteByPageId($pageUid > 0 ? $pageUid : (int)($params['pid'] ?? 0));
            $siteLanguage = $site->getLanguageById($languageUid);
            $languageCode = $siteLanguage->getLocale()->getLanguageCode(); // e.g., 'et', 'da', 'is'
        } catch (\Exception $e) {
            $languageCode = 'en'; // Safe fallback
        }

        // 3. Dedicated localized maps based on native URL expectations
        $languageMaps = [
            'et' => [ // Estonian
                'õ' => 'o', 'Õ' => 'o',
                'ä' => 'a', 'Ä' => 'a',
                'ö' => 'o', 'Ö' => 'o',
                'ü' => 'u', 'Ü' => 'u',
            ],
            'fi' => [ // Finnish
                'ä' => 'a', 'Ä' => 'a',
                'ö' => 'o', 'Ö' => 'o',
            ],
            'sv' => [ // Swedish
                'å' => 'a', 'Å' => 'a',
                'ä' => 'a', 'Ä' => 'a',
                'ö' => 'o', 'Ö' => 'o',
            ],
            'da' => [ // Danish
                'æ' => 'ae', 'Æ' => 'ae',
                'ø' => 'o',  'Ø' => 'o',
                'å' => 'aa', 'Å' => 'aa', // Traditional local URL fallback
            ],
            'no' => [ // Norwegian
                'æ' => 'ae', 'Æ' => 'ae',
                'ø' => 'o',  'Ø' => 'o',
                'å' => 'aa', 'Å' => 'aa', // Traditional local URL fallback
            ],
            'is' => [ // Icelandic
                'á' => 'a', 'Á' => 'a',
                'é' => 'e', 'É' => 'e',
                'í' => 'i', 'Í' => 'i',
                'ó' => 'o', 'Ó' => 'o',
                'ú' => 'u', 'Ú' => 'u',
                'ý' => 'y', 'Ý' => 'y',
                'ð' => 'd', 'Ð' => 'd',  
                'þ' => 'th', 'Þ' => 'th',
                'æ' => 'ae', 'Æ' => 'ae',
                'ö' => 'o', 'Ö' => 'o',
            ]
        ];

        // 4. If the language isn't explicitly targeted, bypass to TYPO3 defaults (e.g. German, English)
        if (!array_key_exists($languageCode, $languageMaps)) {
            return $params['slug'];
        }

        // 5. Isolate raw source title values
        $rawTitle = !empty($params['record']['nav_title']) 
            ? $params['record']['nav_title'] 
            : ($params['record']['title'] ?? '');

        if (empty($rawTitle)) {
            return $params['slug'];
        }

        // Perform the exact character swap matching the page locale
        $cleanTitle = strtr($rawTitle, $languageMaps[$languageCode]);

        // Pass the cleaned text directly to the Symfony conversion engine
        $slugger = new AsciiSlugger();
        $cleanSegment = $slugger->slug($cleanTitle)->lower()->toString();

        // 6. Safeguard page structures (keeping parent directory segments secure)
        if ($params['tableName'] === 'pages') {
            $slugPath = trim($params['slug'], '/');
            if (str_contains($slugPath, '/')) {
                $segments = explode('/', $slugPath);
                array_pop($segments); // Drop the original un-transliterated segment
                $segments[] = $cleanSegment; // Inject our clean replacement
                return '/' . implode('/', $segments);
            }
            return '/' . $cleanSegment;
        }

        return $cleanSegment;
    }
}