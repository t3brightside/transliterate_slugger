<?php
namespace Brightside\TransliterateSlugger\EventListener;

use TYPO3\CMS\Core\Configuration\Event\AfterTcaCompilationEvent;
use Brightside\TransliterateSlugger\Hook\TransliterateSlugModifier;

class UniversalSlugInjector
{
    public function __invoke(AfterTcaCompilationEvent $event): void
    {
        $tca = $event->getTca();

        foreach ($tca as $tableName => &$tableConfig) {
            if (!isset($tableConfig['columns']) || !is_array($tableConfig['columns'])) {
                continue;
            }

            foreach ($tableConfig['columns'] as $fieldName => &$fieldConfig) {
                if (($fieldConfig['config']['type'] ?? '') === 'slug') {
                    
                    // Explicitly initialize generatorOptions and postModifiers using approved schema structure
                    if (!isset($fieldConfig['config']['generatorOptions']['postModifiers']) || !is_array($fieldConfig['config']['generatorOptions']['postModifiers'])) {
                        $fieldConfig['config']['generatorOptions']['postModifiers'] = [];
                    }

                    // Append our modifier class safely
                    $fieldConfig['config']['generatorOptions']['postModifiers'][] = 
                        TransliterateSlugModifier::class . '->modifySlug';
                }
            }
        }

        $event->setTca($tca);
    }
}