<?php
declare(strict_types=1);

namespace Brightside\TransliterateSlugger\Hook;

use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\SlugHelper;

class NativeTransliteratorModifier
{
    public function modifySlug(array $params, SlugHelper $reference): string
    {
        $languageCode = $this->getLanguageCode($params);
        $record = $params['record'] ?? [];
        
        // 1. Extract pristine source text from fields (supports comma-separated groupings)
        $slugParts = [];
        $fieldsConfig = $params['configuration']['generatorOptions']['fields'] ?? ['title'];
        
        foreach ($fieldsConfig as $fieldNameParts) {
            if (is_string($fieldNameParts)) {
                $fieldNameParts = GeneralUtility::trimExplode(',', $fieldNameParts);
            }
            foreach ($fieldNameParts as $fieldName) {
                if (!empty($record[$fieldName])) {
                    $slugParts[] = (string)$record[$fieldName];
                    break; 
                }
            }
        }
        
        $rawText = implode('-', $slugParts);

        // Fail-safe protection if text extraction returns completely empty
        if (empty($rawText)) {
            return $params['slug'] ?? '';
        }

        // 2. Process through shared native ICU logic
        $rawText = $this->processString($rawText, $languageCode);

        // 3. Apply the final URL-safe slug formatting via TYPO3's native SlugHelper
        $cleanSegment = $reference->sanitize($rawText);

        // 4. Surgically replace ONLY the final segment (Bulletproof string manipulation)
        $coreSlug = (string)($params['slug'] ?? '');
        $lastSlashPos = strrpos($coreSlug, '/');

        if ($lastSlashPos !== false) {
            // Get the parent path string (everything before the final slash)
            $parentPath = substr($coreSlug, 0, $lastSlashPos);
            
            // rtrim/ltrim guarantees that no matter what TYPO3 passes, there is ONLY EVER one slash between them
            return rtrim($parentPath, '/') . '/' . ltrim($cleanSegment, '/');
        }

        return $cleanSegment;
    }

    /**
     * Shared Core Logic: Used by both the Slugger and the File Sanitizer
     */
    public function processString(string $rawText, string $languageCode): string
    {
        // Build a dynamic ruleset string matching the target language (e.g., 'Any-Latin; de-ASCII')
        $transliteratorId = "Any-Latin; {$languageCode}-ASCII";
        
        // Query the server's compiled engine to see if the language-specific mapping is valid
        $testInstance = @\Transliterator::create($transliteratorId);

        // If the language ID does not exist natively (like 'et-ASCII'), fall back to the universal rule
        if (!$testInstance instanceof \Transliterator) {
            $transliteratorId = 'Any-Latin; Latin-ASCII';
        }

        // Apply the resolved native rule chain
        $nativeCleanedText = transliterator_transliterate($transliteratorId, $rawText);
        
        return $nativeCleanedText !== false ? $nativeCleanedText : $rawText;
    }

    /**
     * Resolves the ISO-639-1 language code safely from the active Site Config
     */
    private function getLanguageCode(array $params): string
    {
        try {
            $tableName = $params['tableName'] ?? '';
            $record = $params['record'] ?? [];

            $pageUid = ($tableName === 'pages') ? (int)($record['uid'] ?? 0) : (int)($record['pid'] ?? 0);
            
            if ($pageUid < 0) {
                $prevRecord = BackendUtility::getRecord($tableName, abs($pageUid), 'pid');
                $pageUid = (int)($prevRecord['pid'] ?? 0);
            }

            if ($pageUid <= 0) {
                $pageUid = (int)($params['pid'] ?? 0);
            }

            $languageUid = (int)($record['sys_language_uid'] ?? 0);
            if ($languageUid < 0) {
                $languageUid = 0;
            }
            
            $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
            $site = $siteFinder->getSiteByPageId($pageUid);

            return $site->getLanguageById($languageUid)->getLocale()->getLanguageCode();
        } catch (\Exception $e) {
            return 'en'; 
        }
    }
}