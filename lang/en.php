<?php
/**
 * Maui Web Explorer - language file: English
 *
 * HOW TO ADD A NEW LANGUAGE:
 *   1. Copy this file, rename it to the language code (e.g. de.php = German).
 *   2. Change the VALUES (right side) only - do NOT change the keys (left side).
 *   3. Register the language in the $LANGS list in index.php (code => display name).
 *
 * NOTES:
 *   - %d and %s are placeholders (numbers / file sizes) - KEEP them in translations.
 *   - %1$s, %2$s, %3$s are numbered placeholders - keep all of them, reorder to
 *     fit your language's grammar.
 *   - Missing keys automatically fall back to Bahasa Melayu.
 */
return [
    // === Language dropdown & search ===
    'lang_title'   => 'Language',
    'search_ph'    => 'Search in this folder…',
    'clear_search' => 'Clear search',

    // === Toolbar ===
    'zip_dl'   => 'Download All (Zip)',
    'sort_inc' => 'Toggle sort direction',
    'sort_pick'=> 'Choose sort type',
    'v_grid'   => 'Grid view',
    'v_list'   => 'List view',
    'v_theme'  => 'Toggle theme',
    'info'     => '%d folders, %d files',
    'info_q'   => '%d results',

    // === Sort options ===
    's_name'  => 'Name',
    's_size'  => 'Size',
    's_ctime' => 'Created',
    's_mtime' => 'Modified',
    's_ext'   => 'Type',

    // === List / cards ===
    'lh_name' => 'Name',
    'lh_size' => 'Size',
    'lh_mod'  => 'Modified',
    'up'      => 'Up',
    'dir'     => 'Folder',
    'save_as' => 'Save file (Save As)',

    // === Empty states ===
    'e_search' => 'No files match your search.',
    'e_sub'    => 'This folder is empty.',
    'e_root'   => 'Empty folder - nothing to display.',

    // === Load-more button ===
    'more'      => 'Load %d more',
    'all'       => 'All',
    'more_aria' => 'Load more items',
    'more_pick' => 'Choose how many',

    // === Misc ===
    'top'     => 'Back to top',
    'die404'  => 'Directory not found.',
    'die404b' => 'Cannot read directory.',

    // === Lightbox (inline preview) ===
    'lb_dl'   => '⬇ Download',
    'lb_close'=> 'Close',
    'lb_load' => 'Loading…',
    'lb_fail' => 'Failed to load the file for preview.',
    'lb_trim' => '…(trimmed)',

    // === Zip warning (popup) ===
    'zw_title' => 'Folder too large to download as a Zip',
    'zw_msg'   => 'This folder totals %1$s (%2$s files). The "Download All (Zip)" limit is %3$s.',
    'zw_hint'  => 'Please download files one by one using the save button (⬇) on each item.',
    'zw_ok'    => 'OK',

    // === 413 warning page (folder too large) ===
    'p_title' => 'Folder too large',
    'p_h1'    => 'Folder too large to download as a Zip',
    'p_p1'    => 'This folder totals %1$s (%2$s files).',
    'p_p2'    => 'The "Download All (Zip)" limit is %3$s. Please download files one by one using the save button on each item.',
    'p_back'  => 'Back to folder',
];
