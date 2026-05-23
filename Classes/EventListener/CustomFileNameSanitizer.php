<?php
declare(strict_types=1);

namespace Brightside\TransliterateSlugger\EventListener;

use TYPO3\CMS\Core\Resource\Event\SanitizeFileNameEvent;
use Brightside\TransliterateSlugger\Hook\NativeTransliteratorModifier;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CustomFileNameSanitizer
{
    public function __invoke(SanitizeFileNameEvent $event): void
    {
        $pristineName = $event->getFileName();
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        
        // 1. RECOVER PRISTINE STRING FROM HTTP REQUEST (Bulletproof Bypass)
        if ($request instanceof \Psr\Http\Message\ServerRequestInterface) {
            $queryParams = $request->getQueryParams();
            $parsedBody = $request->getParsedBody();
            
            // A. Catch AJAX pre-flight checks
            if (!empty($queryParams['fileName'])) {
                $pristineName = (string)$queryParams['fileName'];
            } elseif (is_array($parsedBody) && !empty($parsedBody['fileName'])) {
                $pristineName = (string)$parsedBody['fileName'];
            } 
            // B. Catch the actual physical multipart file upload
            else {
                $uploadedFiles = $request->getUploadedFiles();
                array_walk_recursive($uploadedFiles, function ($file) use (&$pristineName, $event) {
                    if ($file instanceof \Psr\Http\Message\UploadedFileInterface) {
                        $clientName = $file->getClientFilename();
                        // Match extension to ensure we grab the correct pristine file
                        if ($clientName !== null && pathinfo($clientName, PATHINFO_EXTENSION) === pathinfo($event->getFileName(), PATHINFO_EXTENSION)) {
                            $pristineName = $clientName;
                        }
                    }
                });
            }
        }

        // 2. MAC OS FIX: Normalize NFD (e.g. o + ¨) to NFC (ö)
        if (class_exists('Normalizer') && \Normalizer::isNormalized($pristineName, \Normalizer::FORM_D)) {
            $pristineName = \Normalizer::normalize($pristineName, \Normalizer::FORM_C);
        }

        $pathInfo = pathinfo($pristineName);
        $extension = isset($pathInfo['extension']) ? '.' . strtolower($pathInfo['extension']) : '';
        $baseName = $pathInfo['filename'];
        
        // 3. DYNAMICALLY CHECK SYSTEM CONFIGURATION
        $isUtf8Filesystem = (bool)($GLOBALS['TYPO3_CONF_VARS']['SYS']['UTF8filesystem'] ?? false);

        if ($isUtf8Filesystem) {
            // PRESERVE UTF-8
            $cleanedBaseName = mb_strtolower($baseName, 'UTF-8');
            $cleanedBaseName = preg_replace('/[\s\+\*\\/\\\\&%"\'\?,;:#]+/', '-', $cleanedBaseName);
        } else {
            // FORCE ASCII VIA ICU (Using System Locale)
            $modifier = GeneralUtility::makeInstance(NativeTransliteratorModifier::class);
            $systemLocale = $GLOBALS['TYPO3_CONF_VARS']['SYS']['systemLocale'] ?? 'en';
            $systemLanguageCode = substr($systemLocale, 0, 2);
            
            $cleanedBaseName = $modifier->processString($baseName, $systemLanguageCode);
            $cleanedBaseName = preg_replace('/[^a-z0-9-]/', '-', $cleanedBaseName);
        }

        // 4. FINAL CLEANUP
        $cleanedBaseName = trim((string)preg_replace('/-+/', '-', $cleanedBaseName), '-');
        
        if ($cleanedBaseName === '') {
            $cleanedBaseName = 'upload-' . time();
        }
        
        $event->setFileName($cleanedBaseName . $extension);
    }
}