<?php
namespace Brightside\TransliterateSlugger\Hook;

use Symfony\Component\String\Slugger\AsciiSlugger;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Core\Environment;

class TransliterateSlugModifier
{
    public function modifySlug(array $params): string
    {
        // 1. Resolve the active language code safely
        $languageCode = $this->getLanguageCode($params);

        // --- THE SILENT DEBUG ---
        $logData = print_r([
            'DETECTED_LANGUAGE' => $languageCode, 
            'RAW_PARAMS' => $params
        ], true);
        
        // This writes to var/log/slug_debug.log without breaking the backend AJAX
        $logPath = Environment::getVarPath() . '/log/slug_debug.log';
        file_put_contents($logPath, "\n\n--- NEW REQUEST ---\n" . $logData, FILE_APPEND);
        // ---------------------------

        $maps = [
            'et' => ['õ' => 'o', 'Õ' => 'o', 'ä' => 'a', 'Ä' => 'a', 'ö' => 'o', 'Ö' => 'o', 'ü' => 'u', 'Ü' => 'u'],
            'fi' => ['ä' => 'a', 'Ä' => 'a', 'ö' => 'o', 'Ö' => 'o'],
            'sv' => ['å' => 'a', 'Å' => 'a', 'ä' => 'a', 'Ä' => 'a', 'ö' => 'o', 'Ö' => 'o'],
            'da' => ['æ' => 'ae', 'Æ' => 'ae', 'ø' => 'o', 'Ø' => 'o', 'å' => 'aa', 'Å' => 'aa'],
            'no' => ['æ' => 'ae', 'Æ' => 'ae', 'ø' => 'o', 'Ø' => 'o', 'å' => 'aa', 'Å' => 'aa'],
            'is' => ['á' => 'a', 'Á' => 'a', 'é' => 'e', 'É' => 'e', 'í' => 'i', 'Í' => 'i', 'ó' => 'o', 'Ó' => 'o', 'ú' => 'u', 'Ú' => 'u', 'ý' => 'y', 'Ý' => 'y', 'ð' => 'd', 'Ð' => 'd', 'þ' => 'th', 'Þ' => 'th', 'æ' => 'ae', 'Æ' => 'ae', 'ö' => 'o', 'Ö' => 'o']
        ];

        if (!isset($maps[$languageCode])) {
            return $params['slug'];
        }

        $record = $params['record'] ?? [];
        $rawTitle = '';
        
        $sourceFields = $params['configuration']['generatorOptions']['fields'] ?? ['title'];
        foreach ($sourceFields as $field) {
            if (!empty($record[$field])) {
                $rawTitle .= $record[$field] . '-';
            }
        }
        $rawTitle = rtrim($rawTitle, '-');

        if (empty($rawTitle)) {
            return $params['slug'];
        }
        
        $cleanText = strtr($rawTitle, $maps[$languageCode]);
        $cleanSegment = (new AsciiSlugger())->slug($cleanText)->lower()->toString();

        if (($params['tableName'] ?? '') === 'pages') {
            $parentPath = dirname($params['slug'] ?? '');
            return ($parentPath === '/' ? '/' : $parentPath . '/') . $cleanSegment;
        }

        return $cleanSegment;
    }

    /**
     * Helper that resolves language safely.
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
            
            try {
                $site = $siteFinder->getSiteByPageId($pageUid);
            } catch (\TYPO3\CMS\Core\Exception\SiteNotFoundException $e) {
                $sites = $siteFinder->getAllSites();
                if (empty($sites)) {
                    return 'et'; 
                }
                $site = reset($sites);
            }

            return $site->getLanguageById($languageUid)->getLocale()->getLanguageCode();
        } catch (\Exception $e) {
            return 'et'; 
        }
    }
}