<?php
/*======================================================================*\
|| VBulletin by Tornevall Tools - hooks                                ||
\*======================================================================*/

if (!defined('VB_ENTRY')) {
    die('Access denied.');
}

/**
 * This file is intentionally conservative.
 * vBulletin hook names differ depending on where the editor is rendered.
 * The JS also has a DOM-ready fallback, so the first test is only to ensure
 * that the script is loaded on pages where the package hook runs.
 */

function vbulletin_by_tools_load_editor_assets()
{
    $options = vB::getDatastore()->getValue('options');

    if (empty($options['tornis_tools_ai_enabled'])) {
        return;
    }

    $base = vB::getDatastore()->getOption('bburl');
    $base = rtrim((string)$base, '/');

    echo '<link rel="stylesheet" href="' . htmlspecialchars($base, ENT_QUOTES, 'UTF-8') . '/core/packages/vbulletin_by_tools/js/tornis_tools_ai.css" />' . "\n";
    echo '<script src="' . htmlspecialchars($base, ENT_QUOTES, 'UTF-8') . '/core/packages/vbulletin_by_tools/js/tornis_tools_ai.js"></script>' . "\n";
}
