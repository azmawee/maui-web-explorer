<?php
/**
 * Maui Web Explorer - fail bahasa: Bahasa Melayu (DEFAULT)
 *
 * CARA TAMBAH BAHASA BARU:
 *   1. Salin fail ini, tukar nama ikut kod bahasa (cth: de.php = Jerman).
 *   2. Tukar NILAI (bahagian kanan) sahaja - JANGAN ubah kunci (bahagian kiri).
 *   3. Daftar bahasa dalam senarai $LANGS di index.php (kod => nama paparan).
 *
 * NOTA:
 *   - %d dan %s ialah placeholder (nombor / saiz fail) - KEKALKAN dalam terjemahan.
 *   - %1$s, %2$s, %3$s ialah placeholder bernombor - kekalkan semua, susun ikut
 *     tatabahasa bahasa anda.
 *   - Kunci yang tiada dalam fail ini akan jatuh ke Bahasa Melayu secara automatik.
 */
return [
    // === Dropdown bahasa & carian ===
    'lang_title'   => 'Bahasa',
    'search_ph'    => 'Cari dalam folder ini…',
    'clear_search' => 'Kosongkan carian',

    // === Toolbar ===
    'zip_dl'   => 'Download Semua (Zip)',
    'sort_inc' => 'Tokok arah susunan',
    'sort_pick'=> 'Pilih jenis susunan',
    'v_grid'   => 'Paparan grid',
    'v_list'   => 'Paparan senarai',
    'v_theme'  => 'Tukar tema',
    'info'     => '%d folder, %d fail',
    'info_q'   => '%d hasil carian',

    // === Pilihan susunan ===
    's_name'  => 'Nama',
    's_size'  => 'Saiz',
    's_ctime' => 'Dicipta',
    's_mtime' => 'Diubah',
    's_ext'   => 'Jenis',

    // === Senarai / kad ===
    'lh_name' => 'Nama',
    'lh_size' => 'Saiz',
    'lh_mod'  => 'Diubah',
    'up'      => 'Atas',
    'dir'     => 'Folder',
    'save_as' => 'Simpan fail (Save As)',

    // === Keadaan kosong ===
    'e_search' => 'Tiada fail sepadan dengan carian.',
    'e_sub'    => 'Folder ini kosong.',
    'e_root'   => 'Folder kosong - tiada fail untuk dipaparkan.',

    // === Butang Lagi N ===
    'more'      => 'Lagi %d',
    'all'       => 'Semua',
    'more_aria' => 'Muat lebih item',
    'more_pick' => 'Pilih bilangan',

    // === Lain-lain ===
    'top'     => 'Ke atas',
    'die404'  => 'Direktori tidak ditemukan.',
    'die404b' => 'Tidak dapat membaca direktori.',

    // === Lightbox (pratonton inline) ===
    'lb_dl'   => '⬇ Muat turun',
    'lb_close'=> 'Tutup',
    'lb_load' => 'Memuat…',
    'lb_fail' => 'Gagal memuatkan fail untuk pratonton.',
    'lb_trim' => '…(dipangkas)',

    // === Amaran Zip (popup) ===
    'zw_title' => 'Folder terlalu besar untuk dimuat turun sebagai Zip',
    'zw_msg'   => 'Jumlah saiz folder ini ialah %1$s (%2$s fail). Had "Download Semua (Zip)" ialah %3$s.',
    'zw_hint'  => 'Sila muat turun fail satu persatu guna butang simpan (⬇) pada setiap item.',
    'zw_ok'    => 'Baiklah',

    // === Halaman amaran 413 (folder terlalu besar) ===
    'p_title' => 'Folder terlalu besar',
    'p_h1'    => 'Folder terlalu besar untuk dimuat turun sebagai Zip',
    'p_p1'    => 'Jumlah saiz folder ini ialah %1$s (%2$s fail).',
    'p_p2'    => 'Had "Download Semua (Zip)" ialah %3$s. Sila muat turun fail satu persatu menggunakan butang simpan pada setiap item.',
    'p_back'  => 'Kembali ke folder',
];
