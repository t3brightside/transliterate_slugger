<?php
defined('TYPO3') or die();

$GLOBALS['TCA']['pages']['columns']['slug']['config']['generatorOptions']['postModifiers'][] = 
    \Brightside\TransliterateSlugger\Hook\TransliterateSlugModifier::class . '->modifySlug';