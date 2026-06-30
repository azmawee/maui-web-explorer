<?php
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
set_time_limit(0);
ini_set('memory_limit', '64M');

/**
 * Maui Web Explorer - single-file PHP file explorer.
 * Dark/light theme, symlink friendly, large-file streaming with resume,
 * server-side search + pagination, inline media preview, click-to-sort.
 *
 * Security model: the requested path is normalized lexically and any '..'
 * segment that would escape $BASE_DIR is rejected (on both '/' and '\', which
 * closes the Windows backslash hole). Symlinks inside the tree are resolved by
 * the OS to their real target (even when that target is outside $BASE_DIR),
 * BY DESIGN, so only place symlinks you intend to expose. For public use,
 * protect the entry with .htaccess + .htpasswd and serve over HTTPS (Basic Auth
 * over plain HTTP sends credentials in cleartext).
 */

// === Config ===
$SITE_NAME = 'Maui Web Explorer';
$BASE_DIR  = __DIR__;
$HIDDEN    = ['.htaccess', '.htpasswd', '.git', '.gitignore', '.env', 'index.php'];
$REVEAL    = 15;  // bilangan item awal; baki dimuat dengan butang "Lagi N" (client-side reveal)
$ZIP_MAX   = 10737418240; // had "Download Semua (Zip)" - 10 GB (streaming STORE, compress paling ringan)
$FOOTER    = 'Web Explorer ringan dan ringkas'; // ayat footer; kosongkan ('') untuk sembunyikan

$ICONS = [
    'dir' => '📁', 'dir_up' => '⬆️',
    'pdf' => '📕', 'doc' => '📘', 'docx' => '📘',
    'txt' => '📝', 'md' => '📝', 'rtf' => '📝',
    'jpg' => '🖼️', 'jpeg' => '🖼️', 'png' => '🖼️',
    'gif' => '🖼️', 'webp' => '🖼️', 'svg' => '🖼️', 'bmp' => '🖼️', 'ico' => '🖼️',
    'mp4' => '🎬', 'mkv' => '🎬', 'mov' => '🎬',
    'avi' => '🎬', 'webm' => '🎬', 'm4v' => '🎬',
    'mp3' => '🎵', 'wav' => '🎵', 'flac' => '🎵',
    'ogg' => '🎵', 'm4a' => '🎵', 'aac' => '🎵',
    'zip' => '📦', 'rar' => '📦', '7z' => '📦',
    'tar' => '📦', 'gz' => '📦', 'bz2' => '📦',
    'xls' => '📊', 'xlsx' => '📊', 'csv' => '📊',
    'php' => '🐘', 'js' => '📜', 'html' => '🌐',
    'css' => '🎨', 'py' => '🐍', 'sh' => '⚡',
    'json' => '📋', 'xml' => '📋', 'sql' => '🗄️',
    'exe' => '⚙️', 'apk' => '📱', 'dmg' => '💿',
    'iso' => '💿', 'default' => '📄',
];

// Extensions opened in the inline preview lightbox (everything else downloads).
$PREVIEW = [
    'img'   => ['jpg','jpeg','png','gif','webp','svg','bmp','ico'],
    'video' => ['mp4','mkv','mov','webm','m4v','avi'],
    'audio' => ['mp3','wav','flac','ogg','m4a','aac'],
    'pdf'   => ['pdf'],
    'text'  => ['txt','md','json','xml','html','htm','css','js','php','py','sh','csv','srt','ass','sub','ini','yml','yaml','log','rtf','sql'],
];

// --- Helpers ---

/**
 * Normalize a relative request path; reject '..' traversal that escapes $base.
 * Does NOT call realpath(), so symlinks are preserved and followed by the OS.
 * Returns the absolute path, or null on a traversal attempt.
 */
function resolve_safe(string $rel, string $base): ?string {
    $rel = trim(str_replace('\\', '/', $rel), '/'); // close the Windows backslash hole
    $parts = [];
    foreach (explode('/', $rel) as $seg) {
        if ($seg === '' || $seg === '.') continue;
        if ($seg === '..') return null;              // block escape from base
        $parts[] = $seg;
    }
    return $parts ? ($base . '/' . implode('/', $parts)) : $base;
}

/** Build a query string preserving current context (p, q, page) with overrides/drops. */
function qstr(array $overrides = [], array $drop = []): string {
    $out = [];
    foreach (['p', 'q', 'page'] as $k) {
        if (in_array($k, $drop, true) || array_key_exists($k, $overrides)) continue;
        if (isset($_GET[$k]) && $_GET[$k] !== '') $out[$k] = $_GET[$k];
    }
    foreach ($overrides as $k => $v) {
        if ($v !== null && $v !== '') $out[$k] = $v;
    }
    return $out ? '?' . http_build_query($out, '', '&', PHP_QUERY_RFC3986) : '';
}

/** filesize() with a safe 0 fallback. */
function filesize_s(string $path): int {
    $s = @filesize($path);
    return $s === false ? 0 : (int)$s;
}

/** Map an extension to a preview kind ('' | img | video | audio | pdf | text). */
function preview_kind(string $ext, array $preview): string {
    $ext = strtolower($ext);
    foreach ($preview as $kind => $exts) {
        if (in_array($ext, $exts, true)) return $kind;
    }
    return '';
}

// === Download / stream file (support resume + large file) ===
if (isset($_GET['d'])) {
    $file_path = resolve_safe((string)$_GET['d'], $BASE_DIR);
    if ($file_path === null || !is_file($file_path) || !is_readable($file_path)) {
        http_response_code(404);
        exit;
    }

    $name = basename($file_path);
    $size = filesize_s($file_path);
    $mime = get_mime($file_path);

    header("Content-Type: $mime");
    header("Accept-Ranges: bytes");
    header("Cache-Control: public, max-age=3600");
    header("X-Content-Type-Options: nosniff");

    $force_save = isset($_GET['save']);
    if ($force_save || !preg_match('/^(image|video|audio|text|application\/pdf)/i', $mime)) {
        header("Content-Disposition: attachment; filename=\"$name\"");
    } else {
        header("Content-Disposition: inline; filename=\"$name\"");
    }

    $start = 0;
    $end   = $size > 0 ? $size - 1 : 0;

    if (isset($_SERVER['HTTP_RANGE'])
        && preg_match('/^bytes=(\d+)-(\d*)$/', trim($_SERVER['HTTP_RANGE']), $m)) {
        $start = (int)$m[1];
        $end   = ($m[2] !== '') ? (int)$m[2] : $end;
        if ($size > 0 && $start <= $end && $start < $size && $end < $size) {
            http_response_code(206);
            header("Content-Range: bytes $start-$end/$size");
        } else {
            http_response_code(416); // Range Not Satisfiable
            header("Content-Range: bytes */$size");
            exit;
        }
    }
    header('Content-Length: ' . ($end - $start + 1));

    $fp = @fopen($file_path, 'rb');
    if (!$fp) { http_response_code(500); exit; }

    $chunk = 8192;
    $sent  = 0;
    $total = $end - $start + 1;

    if ($start > 0) fseek($fp, $start);

    while (!feof($fp) && $sent < $total && connection_status() === CONNECTION_NORMAL) {
        $read = min($chunk, $total - $sent);
        echo fread($fp, $read);
        $sent += $read;
        ob_flush();
        flush();
    }
    fclose($fp);
    exit;
}

function get_mime(string $path): string {
    if (function_exists('finfo_open')) {
        $f = @finfo_open(FILEINFO_MIME_TYPE);
        if ($f) {
            $m = @finfo_file($f, $path);
            @finfo_close($f);
            if ($m) return $m;
        }
    }
    if (function_exists('mime_content_type')) {
        $m = @mime_content_type($path);
        if ($m && $m !== 'application/octet-stream') return $m;
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $map = [
        'pdf'=>'application/pdf','zip'=>'application/zip','rar'=>'application/vnd.rar',
        '7z'=>'application/x-7z-compressed','gz'=>'application/gzip',
        'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png',
        'gif'=>'image/gif','webp'=>'image/webp','svg'=>'image/svg+xml',
        'bmp'=>'image/bmp','ico'=>'image/x-icon',
        'mp4'=>'video/mp4','mkv'=>'video/x-matroska','mov'=>'video/quicktime',
        'avi'=>'video/x-msvideo','webm'=>'video/webm','m4v'=>'video/mp4',
        'mp3'=>'audio/mpeg','wav'=>'audio/wav','flac'=>'audio/flac',
        'ogg'=>'audio/ogg','m4a'=>'audio/mp4','aac'=>'audio/aac',
        'txt'=>'text/plain','md'=>'text/plain','csv'=>'text/csv',
        'html'=>'text/html','htm'=>'text/html','css'=>'text/css',
        'js'=>'application/javascript','json'=>'application/json','xml'=>'application/xml',
        'php'=>'application/x-httpd-php','sh'=>'text/x-shellscript',
        'doc'=>'application/msword',
        'docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'=>'application/vnd.ms-excel',
        'xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'srt'=>'text/plain','ass'=>'text/plain','sub'=>'text/plain',
    ];
    return $map[$ext] ?? 'application/octet-stream';
}

// === Browse directory ===
$req        = isset($_GET['p']) ? (string)$_GET['p'] : '';
$target_dir = resolve_safe($req, $BASE_DIR);
if ($target_dir === null || !is_dir($target_dir) || !is_readable($target_dir)) {
    http_response_code(404);
    die('Directory not found.');
}

// Normalized forward-slash path relative to base (drives breadcrumbs + child links).
$rel_path = ltrim(substr($target_dir, strlen($BASE_DIR)), '/\\');

// === Download whole folder as a streaming ZIP (STORE / lightest compression) ===
// Saiz disemak SEKARANG (bila user tekan butang), bukan pre-check. Melebihi $ZIP_MAX
// atau 65534 fail -> halaman amaran (413). Symlink diikuti (ciri sedia ada); kitaran
// symlink dihindarkan dengan hash realpath pada folder yang telah dilawati.
if (isset($_GET['zip'])) {
    $zip_files = [];   // [['abs'=>..., 'rel'=>..., 'size'=>...], ...]
    $zip_total = 0;    // jumlah saiz fail ( uncompressed, sama dengan STORE)
    $zip_seen  = [];   // realpath folder => true (penghad kitaran)

    $zip_collect = function (string $dir, int $base_len) use (&$zip_collect, &$zip_files, &$zip_total, &$zip_seen, $HIDDEN) {
        $real = @realpath($dir);
        if ($real === false || isset($zip_seen[$real])) return;
        $zip_seen[$real] = true;
        $entries = @scandir($dir);
        if ($entries === false) return;
        foreach ($entries as $name) {
            if ($name === '.' || $name === '..') continue;
            if ($name[0] === '.' || in_array($name, $HIDDEN, true)) continue; // sembunyi dotfile / $HIDDEN
            $full = $dir . '/' . $name;
            if (is_dir($full)) { $zip_collect($full, $base_len); continue; }
            if (is_file($full) && is_readable($full)) {
                $rel = str_replace('\\', '/', ltrim(substr($full, $base_len), '/\\'));
                $sz  = filesize_s($full);
                $zip_files[] = ['abs' => $full, 'rel' => $rel, 'size' => $sz];
                $zip_total  += $sz;
            }
        }
    };
    $zip_collect($target_dir, strlen($target_dir));

    // Mode "check": pulangkan JSON ringan sahaja (jalan atas tree, tak baca fail).
    // Dipanggil oleh JS butang sebelum download bermula -> popup kalau melebihi had.
    $zip_mode = is_string($_GET['zip']) ? $_GET['zip'] : '1';
    if ($zip_mode === 'check') {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        echo json_encode([
            'ok'    => ($zip_total <= $ZIP_MAX && count($zip_files) <= 65534),
            'total' => $zip_total,
            'count' => count($zip_files),
            'max'   => $ZIP_MAX,
        ]);
        exit;
    }

    if ($zip_total > $ZIP_MAX || count($zip_files) > 65534) {
        http_response_code(413);
        header('Content-Type: text/html; charset=utf-8');
        $gb   = function ($b) { return number_format($b / 1073741824, 2) . ' GB'; };
        $zself = htmlspecialchars(basename($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
        $back  = $zself . qstr([], ['zip']);
        echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
           . '<title>Folder terlalu besar</title><style>'
           . 'body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;'
           . 'background:#0f172a;color:#e2e8f0;font-family:system-ui,Segoe UI,Roboto,sans-serif}'
           . '.box{max-width:560px;padding:32px 28px;text-align:center}'
           . '.big{font-size:60px;margin-bottom:10px}h1{font-size:20px;margin:0 0 14px}'
           . 'p{color:#94a3b8;line-height:1.6;margin:8px 0}b{color:#e2e8f0}'
           . '.a{display:inline-block;margin-top:20px;padding:10px 18px;border-radius:8px;'
           . 'background:#3b82f6;color:#fff;text-decoration:none;font-weight:600}'
           . '</style><div class="box"><div class="big">📦</div>'
           . '<h1>Folder terlalu besar untuk dimuat turun sebagai Zip</h1>'
           . '<p>Jumlah saiz folder ini ialah <b>' . $gb($zip_total) . '</b> '
           . '(' . number_format(count($zip_files)) . ' fail).</p>'
           . '<p>Had "Download Semua (Zip)" ialah <b>' . $gb($ZIP_MAX) . '</b>. '
           . 'Sila muat turun fail satu persatu menggunakan butang simpan pada setiap item.</p>'
           . '<a class="a" href="' . $back . '">Kembali ke folder</a></div>';
        exit;
    }

    // Kosongkan sebarang output buffer, buang had masa (zip besar mengambil masa).
    while (@ob_get_level()) @ob_end_clean();
    @set_time_limit(0);

    $zip_name = ((basename($target_dir) !== '') ? basename($target_dir) : 'files') . '.zip';
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zip_name . '"');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');

    $cd = '';       // central directory terkumpul
    $offset = 0;    // offset local header setiap fail

    foreach ($zip_files as $f) {
        $name  = $f['rel'];
        $nlen  = strlen($name);
        $mt    = @filemtime($f['abs']) ?: time();
        $tt    = getdate($mt);
        $dtime = ($tt['hours'] << 11) | ($tt['minutes'] << 5) | (($tt['seconds'] >> 1) & 0x1F);
        $ddate = ((max(1980, $tt['year']) - 1980) << 9) | ($tt['mon'] << 5) | $tt['mday'];

        // Local file header: flag 0x0008 -> saiz & CRC ditangguh ke data descriptor.
        $lfh = pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0008, 0, $dtime, $ddate, 0, 0, 0, $nlen, 0) . $name;
        echo $lfh;

        // Stream fail + kira CRC32 secara incremental (memori flat, tak load seluruh fail).
        $ctx    = hash_init('crc32b');
        $usize  = 0;
        $fp     = @fopen($f['abs'], 'rb');
        if ($fp) {
            while (!feof($fp)) {
                $chunk = fread($fp, 65536);
                if ($chunk === false || $chunk === '') break;
                hash_update($ctx, $chunk);
                $usize += strlen($chunk);
                echo $chunk;
                flush();
            }
            fclose($fp);
        }
        $crc = hexdec(hash_final($ctx));

        // Data descriptor (dengan tandatangan) -> CRC + saiz sebenar selepas data.
        echo pack('VVVV', 0x08074b50, $crc, $usize, $usize);

        // Central directory record untuk fail ini.
        $cd .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0008, 0, $dtime, $ddate,
                    $crc, $usize, $usize, $nlen, 0, 0, 0, 0, 0, $offset) . $name;

        $offset += strlen($lfh) + $usize + 16; // 16 = saiz data descriptor (4x V)
    }

    echo $cd;
    // End of central directory record.
    echo pack('VvvvvVVv', 0x06054b50, 0, 0, count($zip_files), count($zip_files), strlen($cd), $offset, 0);
    flush();
    exit;
}

$folders = [];
$files   = [];
$entries = @scandir($target_dir);

if ($entries === false) {
    http_response_code(404);
    die('Cannot read directory.');
}

foreach ($entries as $name) {
    if ($name[0] === '.' || in_array($name, $HIDDEN, true)) continue;

    $full = $target_dir . '/' . $name;
    if (!is_readable($full)) continue;

    $child = ($rel_path !== '' ? $rel_path . '/' : '') . $name;

    if (is_dir($full)) {
        $folders[] = [
            'name' => $name, 'path' => $child, 'type' => 'dir',
            'ext' => '', 'size' => 0, 'mtime' => @filemtime($full) ?: 0,
            'ctime' => @filectime($full) ?: 0,
        ];
    } else {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $files[] = [
            'name' => $name, 'path' => $child, 'type' => 'file',
            'ext' => $ext, 'size' => filesize_s($full), 'mtime' => @filemtime($full) ?: 0,
            'ctime' => @filectime($full) ?: 0,
        ];
    }
}

usort($folders, fn($a, $b) => strcasecmp($a['name'], $b['name']));
usort($files,   fn($a, $b) => strcasecmp($a['name'], $b['name']));
$items = array_merge($folders, $files);

$total_folders = count($folders);
$total_files   = count($files);

// --- Search (server-side, scoped to this directory) ---
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
if ($q !== '') {
    $items = array_values(array_filter($items, fn($i) => stripos($i['name'], $q) !== false));
}

// --- Reveal batch: semua item dirender; JS dedahkan secara berperingkat (butang "Lagi N") ---
$total_items = count($items);

function icon(array $item): string {
    global $ICONS;
    if ($item['type'] === 'dir') return $ICONS['dir'];
    return $ICONS[$item['ext'] ?? ''] ?? $ICONS['default'];
}

function fmt_size(int $b): string {
    if ($b >= 1073741824) return number_format($b / 1073741824, 1) . ' GB';
    if ($b >= 1048576)    return number_format($b / 1048576, 1) . ' MB';
    if ($b >= 1024)       return number_format($b / 1024, 1) . ' KB';
    return $b . ' B';
}

$crumbs = [['label' => '🏠', 'path' => '']];
if ($rel_path !== '') {
    $cum = '';
    foreach (explode('/', $rel_path) as $seg) {
        $cum .= ($cum !== '' ? '/' : '') . $seg;
        $crumbs[] = ['label' => $seg, 'path' => $cum];
    }
}

$self         = htmlspecialchars(basename($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
$has_parent   = ($rel_path !== '');
$parent_parts = explode('/', $rel_path);
array_pop($parent_parts);
$parent_path  = implode('/', $parent_parts);

if ($q !== '') {
    $info = $total_items . ' hasil carian';
} else {
    $info = $total_folders . ' folder, ' . $total_files . ' fail';
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Explorer - <?= htmlspecialchars($SITE_NAME) ?></title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0f172a;--surface:#1e293b;--header:#020617;--th-bg:#0d1525;
  --text:#e2e8f0;--text2:#94a3b8;--accent:#3b82f6;--border:#334155;
  --hover:#334155;--radius:10px;--shadow:0 1px 3px rgba(0,0,0,.3)
}
html.light{
  --bg:#eef2f7;--surface:#ffffff;--header:#1e293b;--th-bg:#e9eef5;
  --text:#0f172a;--text2:#64748b;--accent:#2563eb;--border:#e2e8f0;
  --hover:#f1f5f9;--shadow:0 1px 3px rgba(0,0,0,.08)
}
html,body{background:var(--bg);color:var(--text)}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;min-height:100vh}
.header{background:var(--header);color:#fff;padding:0}
.header-inner{max-width:1200px;margin:auto;padding:16px 20px}
.site{font-size:20px;font-weight:700;letter-spacing:-.3px}
.breadcrumb{display:flex;flex-wrap:wrap;gap:4px;margin-top:8px;font-size:13px;align-items:center}
.breadcrumb a{color:#94a3b8;text-decoration:none;padding:2px 6px;border-radius:4px;transition:.15s}
.breadcrumb a:hover{color:#fff;background:rgba(255,255,255,.1)}
.breadcrumb .sep{color:#475569;font-size:11px}
.breadcrumb .current{color:#e2e8f0;padding:2px 6px}
.toolbar{max-width:1200px;margin:auto;padding:12px 20px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;font-size:13px;color:var(--text2)}
.search{display:flex;align-items:center;gap:6px;flex:1 1 200px;max-width:380px}
.search input{flex:1;background:var(--surface);color:var(--text);border:1px solid var(--border);border-radius:6px;padding:7px 10px;font-size:13px}
.search input:focus{outline:none;border-color:var(--accent)}
.search-clear{color:var(--text2);text-decoration:none;font-size:14px;padding:4px 6px;border-radius:4px}
.search-clear:hover{color:var(--accent)}
.toolbar .spacer{flex:1 1 auto}
.toolbar .info{white-space:nowrap}
.view-btn{background:var(--surface);color:var(--text);border:1px solid var(--border);padding:6px 10px;border-radius:6px;cursor:pointer;font-size:14px;transition:.15s;line-height:1;text-decoration:none;display:inline-flex;align-items:center;justify-content:center}
.view-btn:hover,.view-btn.active{background:var(--accent);color:#fff;border-color:var(--accent)}
.grid-wrap{max-width:1200px;margin:auto;padding:0 20px 20px}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px;padding-top:4px}
.card{background:var(--surface);color:var(--text);border:1px solid var(--border);border-radius:var(--radius);padding:16px 12px;text-align:center;
  transition:.2s;cursor:pointer;text-decoration:none;display:flex;flex-direction:column;align-items:center;gap:8px;position:relative}
.card:hover{border-color:var(--accent);box-shadow:var(--shadow);transform:translateY(-2px)}
.card .icon{font-size:36px;line-height:1}
.card .fname{font-size:12px;word-break:break-all;line-height:1.3;max-height:2.6em;overflow:hidden;color:var(--text)}
.card .meta{font-size:11px;color:var(--text2)}
.card.up{border-color:#fbbf24;background:rgba(251,191,36,.1)}
.card.dir{border-left:3px solid var(--accent)}
.dl-btn{position:absolute;right:5px;bottom:5px;width:24px;height:24px;line-height:22px;text-align:center;font-size:13px;border-radius:5px;background:rgba(2,6,23,.6);color:#fff;border:1px solid rgba(255,255,255,.18);cursor:pointer;opacity:.62;transition:.15s;user-select:none}
.card:hover .dl-btn,.dl-btn:focus{opacity:1}
.dl-btn:hover{background:var(--accent);border-color:var(--accent);opacity:1}
.dl-list{position:static;display:inline-block;float:right;margin-left:8px;font-size:15px;line-height:1;color:var(--text2);opacity:.6;cursor:pointer}
.dl-list:hover{color:var(--accent);opacity:1}
.list-wrap{max-width:1200px;margin:auto;padding:0 20px 20px}
table.list{width:100%;border-collapse:collapse;background:var(--surface);color:var(--text);border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow)}
.list th{text-align:left;padding:10px 14px;font-size:12px;font-weight:600;color:var(--text2);
  background:var(--th-bg);border-bottom:1px solid var(--border);text-transform:uppercase;letter-spacing:.3px;user-select:none;white-space:nowrap}
.list th[data-sort]{cursor:pointer}
.list th[data-sort]:hover{color:var(--accent)}
.list th.sort-asc::after{content:" \25B4";color:var(--accent)}
.list th.sort-desc::after{content:" \25BE";color:var(--accent)}
.list td{padding:10px 14px;border-bottom:1px solid var(--border);font-size:13px;white-space:nowrap}
.list tr:last-child td{border-bottom:0}
.list tr:hover td{background:var(--hover)}
.list tbody tr{cursor:pointer}
.list tr.dir td{font-weight:600;background:rgba(59,130,246,.08)}
.list tr.dir:hover td{background:var(--hover)}
.list .icon{font-size:20px}
.list .name{word-break:break-all;white-space:normal;max-width:300px}
.list a{color:inherit;text-decoration:none}
.list a:hover{color:var(--accent)}
.list .sz,.list .dt{color:var(--text2);font-size:12px}
.more-bar{max-width:1200px;margin:auto;padding:0 20px 30px;display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-end;align-items:center}
.more-wrap{position:relative;display:flex}
.more-btn{background:var(--surface);color:var(--text);border:1px solid var(--border);border-right:0;border-radius:6px 0 0 6px;padding:7px 12px;font-size:13px;cursor:pointer;transition:.15s}
.more-btn:hover{border-color:var(--accent);color:var(--accent)}
.more-caret{background:var(--surface);color:var(--text);border:1px solid var(--border);border-radius:0 6px 6px 0;padding:7px 8px;font-size:11px;line-height:1;cursor:pointer;transition:.15s}
.more-caret:hover{border-color:var(--accent);color:var(--accent)}
.more-menu{position:absolute;bottom:calc(100% + 4px);right:0;min-width:128px;background:var(--surface);border:1px solid var(--border);border-radius:6px;box-shadow:var(--shadow);overflow:hidden;z-index:50}
.more-menu button{display:block;width:100%;text-align:left;background:transparent;color:var(--text);border:0;padding:8px 12px;font-size:13px;cursor:pointer}
.more-menu button:hover{background:var(--hover);color:var(--accent)}
.sort-wrap{position:relative;display:inline-flex}
.sort-wrap .sort-btn{border-radius:6px 0 0 6px;border-right:0;font-size:13px;white-space:nowrap}
.sort-wrap .sort-caret{border-radius:0 6px 6px 0;padding:6px 8px;font-size:11px;line-height:1}
.sort-wrap:hover .sort-btn,.sort-wrap:hover .sort-caret{border-color:var(--accent);color:var(--accent)}
.sort-menu{position:absolute;top:calc(100% + 4px);right:0;min-width:140px;background:var(--surface);border:1px solid var(--border);border-radius:6px;box-shadow:var(--shadow);overflow:hidden;z-index:60}
.sort-menu button{display:block;width:100%;text-align:left;background:transparent;color:var(--text);border:0;padding:8px 12px;font-size:13px;cursor:pointer}
.sort-menu button:hover{background:var(--hover);color:var(--accent)}
.sort-menu button.active{color:var(--accent);font-weight:600}
.top-fab{position:fixed;right:20px;bottom:20px;z-index:200;width:46px;height:46px;border-radius:50%;background:var(--accent);color:#fff;border:0;font-size:18px;line-height:1;cursor:pointer;box-shadow:0 4px 14px rgba(0,0,0,.35);opacity:0;visibility:hidden;transform:translateY(10px);transition:opacity .2s,transform .2s,visibility .2s}
.top-fab.show{opacity:1;visibility:visible;transform:translateY(0)}
.top-fab:hover{filter:brightness(1.1)}
.empty{text-align:center;padding:60px 20px;color:var(--text2);font-size:15px}
.empty .icon{font-size:48px;display:block;margin-bottom:12px;color:#475569}
.footer{max-width:1200px;margin:auto;padding:16px 20px;text-align:center;font-size:12px;color:var(--text2);border-top:1px solid var(--border)}
.lb{position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:9999;display:flex;flex-direction:column}
.lb-bar{display:flex;align-items:center;gap:10px;padding:10px 16px;background:#020617;color:#fff}
.lb-title{flex:1;font-size:14px;word-break:break-all}
.lb-btn{color:#fff;text-decoration:none;background:rgba(255,255,255,.1);border:1px solid #334155;border-radius:6px;padding:6px 12px;font-size:13px;cursor:pointer}
.lb-btn:hover{background:var(--accent);border-color:var(--accent)}
.lb-body{flex:1;overflow:auto;display:flex;align-items:center;justify-content:center;padding:20px}
.lb-body img{max-width:100%;max-height:100%;object-fit:contain;border-radius:6px}
.lb-body video{max-width:100%;max-height:90vh}
.lb-body iframe{width:100%;height:100%;border:0;background:#fff;border-radius:6px}
.lb-body .lb-pre{width:100%;max-height:100%;overflow:auto;background:#0f172a;color:#e2e8f0;padding:16px;border-radius:6px;
  font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:13px;white-space:pre-wrap;word-break:break-word;text-align:left}
.zw-box{margin:auto;max-width:480px;background:var(--surface);color:var(--text);border:1px solid var(--border);border-radius:12px;padding:28px 26px;text-align:center;box-shadow:var(--shadow)}
.zw-icon{font-size:48px;line-height:1;margin-bottom:10px}
.zw-box h2{font-size:18px;margin:0 0 12px}
.zw-msg{color:var(--text);line-height:1.6;margin:8px 0;font-size:14px}
.zw-hint{color:var(--text2);line-height:1.6;margin:8px 0;font-size:13px}
.zw-ok{margin-top:14px;background:var(--accent);color:#fff;border:0;border-radius:8px;padding:9px 22px;font-size:14px;font-weight:600;cursor:pointer}
.zw-ok:hover{filter:brightness(1.1)}
@media(max-width:600px){
  .grid{grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:8px}
  .card{padding:12px 8px}.card .icon{font-size:28px}.card .fname{font-size:11px}
  .list .name{max-width:120px}
  .header-inner,.toolbar,.grid-wrap,.list-wrap,.more-bar,.footer{padding-left:12px;padding-right:12px}
  .search{max-width:none;flex:1 1 100%}
}
.hidden{display:none!important}
::-webkit-scrollbar{width:10px;height:10px}
::-webkit-scrollbar-track{background:var(--bg)}
::-webkit-scrollbar-thumb{background:#334155;border-radius:5px}
::-webkit-scrollbar-thumb:hover{background:#475569}
</style>
</head>
<body>

<header class="header">
  <div class="header-inner">
    <div class="site"><?= htmlspecialchars($SITE_NAME) ?></div>
    <nav class="breadcrumb">
      <?php foreach ($crumbs as $i => $c): ?>
        <?php if ($i > 0): ?><span class="sep">›</span><?php endif; ?>
        <?php if ($i < count($crumbs) - 1): ?>
          <a href="<?= $self . qstr(['p' => $c['path']], ['q', 'page']) ?>"><?= htmlspecialchars($c['label']) ?></a>
        <?php else: ?>
          <span class="current"><?= htmlspecialchars($c['label']) ?></span>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>
  </div>
</header>

<div class="toolbar">
  <form class="search" method="get" action="<?= $self ?>">
    <input type="hidden" name="p" value="<?= htmlspecialchars($rel_path) ?>">
    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Cari dalam folder ini…" autocomplete="off">
    <?php if ($q !== ''): ?>
      <a class="search-clear" href="<?= $self . qstr([], ['q', 'page']) ?>" title="Kosongkan carian">✕</a>
    <?php endif; ?>
  </form>
  <span class="spacer"></span>
  <?php if ($total_items > 0): ?>
  <a class="view-btn zip-btn" href="<?= $self . qstr(['zip' => '1']) ?>" data-check="<?= $self . qstr(['zip' => 'check']) ?>" title="Download Semua (Zip)" aria-label="Download Semua (Zip)">📦</a>
  <span class="sort-wrap">
    <button class="view-btn sort-btn" id="sort-btn" type="button" title="Tokok arah susunan" aria-label="Tokok arah susunan">Nama ▴</button>
    <button class="view-btn sort-caret" id="sort-caret" type="button" title="Pilih jenis susunan" aria-label="Pilih jenis susunan">▾</button>
    <div class="sort-menu hidden" id="sort-menu" role="menu" aria-label="Pilih jenis susunan">
      <button type="button" role="menuitem" data-key="name" data-label="Nama">Nama</button>
      <button type="button" role="menuitem" data-key="size" data-label="Saiz">Saiz</button>
      <button type="button" role="menuitem" data-key="ctime" data-label="Dicipta">Dicipta</button>
      <button type="button" role="menuitem" data-key="mtime" data-label="Diubah">Diubah</button>
      <button type="button" role="menuitem" data-key="ext" data-label="Jenis">Jenis</button>
    </div>
  </span>
  <?php endif; ?>
  <button class="view-btn active" id="btn-grid" onclick="setView('grid')" title="Paparan grid">⊞</button>
  <button class="view-btn" id="btn-list" onclick="setView('list')" title="Paparan senarai">☰</button>
  <button class="view-btn" id="btn-theme" title="Tukar tema">◐</button>
  <span class="info"><?= htmlspecialchars($info) ?></span>
</div>

<?php if (empty($items)): ?>
<div class="empty">
  <span class="icon"><?= $q !== '' ? '🔍' : '📂' ?></span>
  <?= $q !== '' ? 'Tiada fail sepadan dengan carian.' : ($has_parent ? 'Folder ini kosong.' : 'Folder kosong - tiada fail untuk dipaparkan.') ?>
</div>
<?php endif; ?>

<div class="grid-wrap" id="view-grid">
  <div class="grid">
    <?php if ($has_parent): ?>
    <a class="card up" href="<?= $self . qstr(['p' => $parent_path], ['q', 'page']) ?>">
      <span class="icon"><?= $ICONS['dir_up'] ?></span>
      <span class="fname">..</span>
      <span class="meta">Atas</span>
    </a>
    <?php endif; ?>
    <?php foreach ($items as $idx => $item):
      $is_dir = $item['type'] === 'dir';
      $href   = $is_dir ? $self . qstr(['p' => $item['path']], ['q', 'page'])
                        : $self . '?d=' . rawurlencode($item['path']);
      $kind   = $is_dir ? '' : preview_kind($item['ext'], $PREVIEW);
    ?>
    <a class="card<?= $is_dir ? ' dir' : '' ?>" href="<?= $href ?>"
       data-idx="<?= $idx ?>" data-name="<?= htmlspecialchars($item['name']) ?>"
       data-size="<?= (int)$item['size'] ?>" data-mtime="<?= (int)$item['mtime'] ?>"
       data-ctime="<?= (int)$item['ctime'] ?>" data-ext="<?= htmlspecialchars($item['ext']) ?>"
       data-type="<?= $is_dir ? 'dir' : 'file' ?>"<?= $kind !== '' ? ' data-preview="' . htmlspecialchars($kind) . '"' : '' ?>>
      <span class="icon"><?= icon($item) ?></span>
      <span class="fname"><?= htmlspecialchars($item['name']) ?></span>
      <span class="meta"><?= $is_dir ? 'Folder' : fmt_size($item['size']) ?></span>
      <?php if (!$is_dir): ?><span class="dl-btn" data-href="<?= $self ?>?d=<?= rawurlencode($item['path']) ?>&amp;save=1" data-name="<?= htmlspecialchars($item['name']) ?>" title="Simpan fail (Save As)" role="button" tabindex="0">⬇</span><?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<div class="list-wrap hidden" id="view-list">
  <table class="list">
    <thead>
      <tr>
        <th style="width:36px"></th>
        <th data-sort="name">Name</th>
        <th data-sort="size" style="width:100px">Size</th>
        <th data-sort="mtime" style="width:160px">Modified</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($has_parent): ?>
      <tr class="dir">
        <td class="icon"><?= $ICONS['dir_up'] ?></td>
        <td class="name"><a href="<?= $self . qstr(['p' => $parent_path], ['q', 'page']) ?>">..</a></td>
        <td class="sz">-</td><td class="dt">-</td>
      </tr>
      <?php endif; ?>
      <?php foreach ($items as $idx => $item):
        $is_dir = $item['type'] === 'dir';
        $href   = $is_dir ? $self . qstr(['p' => $item['path']], ['q', 'page'])
                          : $self . '?d=' . rawurlencode($item['path']);
        $kind   = $is_dir ? '' : preview_kind($item['ext'], $PREVIEW);
      ?>
      <tr class="<?= $is_dir ? 'dir' : '' ?>" data-idx="<?= $idx ?>"
          data-name="<?= htmlspecialchars($item['name']) ?>" data-size="<?= (int)$item['size'] ?>"
          data-mtime="<?= (int)$item['mtime'] ?>" data-ctime="<?= (int)$item['ctime'] ?>"
          data-ext="<?= htmlspecialchars($item['ext']) ?>" data-type="<?= $is_dir ? 'dir' : 'file' ?>">
        <td class="icon"><?= icon($item) ?></td>
        <td class="name"><a href="<?= $href ?>"<?= $kind !== '' ? ' data-preview="' . htmlspecialchars($kind) . '"' : '' ?> data-name="<?= htmlspecialchars($item['name']) ?>"><?= htmlspecialchars($item['name']) ?></a><?php if (!$is_dir): ?><span class="dl-btn dl-list" data-href="<?= $self ?>?d=<?= rawurlencode($item['path']) ?>&amp;save=1" data-name="<?= htmlspecialchars($item['name']) ?>" title="Simpan fail (Save As)" role="button" tabindex="0">⬇</span><?php endif; ?></td>
        <td class="sz"><?= $is_dir ? '-' : fmt_size($item['size']) ?></td>
        <td class="dt"><?= date('d M Y, H:i', (int)$item['mtime']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script>(function(){var n=<?= (int)$REVEAL ?>;function h(s){for(var i=0;i<s.length;i++){if(i>=n)s[i].classList.add('hidden');}}h(document.querySelectorAll('#view-grid .card[data-idx]'));h(document.querySelectorAll('#view-list tbody tr[data-idx]'));})();</script>

<?php if ($total_items > $REVEAL): ?>
<div class="more-bar" id="more-bar">
  <div class="more-wrap">
    <button class="more-btn" id="more-btn" type="button">Lagi <?= (int)$REVEAL ?></button>
    <button class="more-caret" id="more-caret" type="button" aria-label="Pilih bilangan">▾</button>
    <div class="more-menu hidden" id="more-menu">
      <button type="button" data-step="15">Lagi 15</button>
      <button type="button" data-step="20">Lagi 20</button>
      <button type="button" data-step="30">Lagi 30</button>
      <button type="button" data-step="40">Lagi 40</button>
      <button type="button" data-step="50">Lagi 50</button>
      <button type="button" data-step="all">Semua</button>
    </div>
  </div>
</div>
<?php endif; ?>

<button class="top-fab" id="top-fab" type="button" title="Ke atas (muka pertama)" aria-label="Ke atas">⇈</button>

<?php if ($FOOTER !== ''): ?>
<div class="footer"><?= htmlspecialchars($FOOTER) ?></div>
<?php endif; ?>

<div id="lightbox" class="lb hidden" role="dialog" aria-modal="true" aria-label="Pratonton fail">
  <div class="lb-bar">
    <span id="lb-title" class="lb-title"></span>
    <a id="lb-dl" class="lb-btn" href="#" download="">⬇ Muat turun</a>
    <button id="lb-close" class="lb-btn" type="button" aria-label="Tutup">✕</button>
  </div>
  <div id="lb-body" class="lb-body"></div>
</div>

<div id="zip-warn" class="lb hidden" role="alertdialog" aria-modal="true" aria-label="Amaran saiz folder">
  <div class="zw-box">
    <div class="zw-icon">📦</div>
    <h2>Folder terlalu besar untuk dimuat turun sebagai Zip</h2>
    <p id="zw-msg" class="zw-msg"></p>
    <p class="zw-hint">Sila muat turun fail satu persatu guna butang simpan (⬇) pada setiap item.</p>
    <button type="button" id="zw-close" class="zw-ok">Baiklah</button>
  </div>
</div>

<script>
function setView(v) {
  document.getElementById('view-grid').classList.toggle('hidden', v !== 'grid');
  document.getElementById('view-list').classList.toggle('hidden', v !== 'list');
  document.getElementById('btn-grid').classList.toggle('active', v === 'grid');
  document.getElementById('btn-list').classList.toggle('active', v === 'list');
  try { localStorage.setItem('explorer-view', v); } catch(e) {}
}
(function() {
  try {
    var saved = localStorage.getItem('explorer-view');
    if (saved === 'list' || saved === 'grid') setView(saved);
  } catch(e) {}
})();

// --- Theme (dark/light) ---
function setTheme(t) {
  document.documentElement.classList.toggle('light', t === 'light');
  try { localStorage.setItem('explorer-theme', t); } catch(e) {}
}
(function() {
  var saved;
  try { saved = localStorage.getItem('explorer-theme'); } catch(e) {}
  if (saved !== 'light' && saved !== 'dark') {
    saved = (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) ? 'light' : 'dark';
  }
  setTheme(saved);
})();
document.getElementById('btn-theme').addEventListener('click', function() {
  setTheme(document.documentElement.classList.contains('light') ? 'dark' : 'light');
});

// --- Susunan (sort): butang split + dropdown; folder sentiasa dulukan ---
var SORT_LABELS = { name: 'Nama', size: 'Saiz', ctime: 'Dicipta', mtime: 'Diubah', ext: 'Jenis' };
var sortState = { key: 'name', dir: 'asc' };
function sortItems(key, dir) {
  var cards = Array.prototype.slice.call(document.querySelectorAll('#view-grid .card[data-idx]'));
  var rows  = Array.prototype.slice.call(document.querySelectorAll('#view-list tbody tr[data-idx]'));
  var grid  = document.querySelector('#view-grid .grid');
  var tbody = document.querySelector('#view-list tbody');
  var map = {};
  cards.forEach(function(c) {
    var d = c.dataset;
    map[d.idx] = { card: c, name: d.name || '', size: +d.size || 0, mtime: +d.mtime || 0, ctime: +d.ctime || 0, ext: d.ext || '', type: d.type || 'file' };
  });
  rows.forEach(function(r) { if (map[r.dataset.idx]) map[r.dataset.idx].row = r; });
  var mul = dir === 'desc' ? -1 : 1;
  Object.keys(map).sort(function(a, b) {
    var x = map[a], y = map[b];
    if (x.type !== y.type) return x.type === 'dir' ? -1 : 1;   // folder sentiasa dulukan
    var cmp = 0;
    if (key === 'size')         cmp = x.size - y.size;
    else if (key === 'mtime')   cmp = x.mtime - y.mtime;
    else if (key === 'ctime')   cmp = x.ctime - y.ctime;
    else if (key === 'ext')     cmp = (x.ext || '').localeCompare(y.ext || '', undefined, { sensitivity: 'base' });
    else                        cmp = x.name.localeCompare(y.name, undefined, { numeric: true, sensitivity: 'base' });
    if (cmp === 0) cmp = x.name.localeCompare(y.name, undefined, { sensitivity: 'base' });
    return cmp * mul;
  }).forEach(function(k) {
    if (grid && map[k].card) grid.appendChild(map[k].card);
    if (tbody && map[k].row) tbody.appendChild(map[k].row);
  });
  if (window.MWE_reveal) window.MWE_reveal();
}
function applySortUI() {
  var arrow = sortState.dir === 'asc' ? ' ▴' : ' ▾';
  var btn = document.getElementById('sort-btn');
  if (btn) btn.textContent = (SORT_LABELS[sortState.key] || 'Nama') + arrow;
  Array.prototype.slice.call(document.querySelectorAll('#view-list th[data-sort]')).forEach(function(t) {
    t.classList.remove('sort-asc', 'sort-desc');
    if (t.getAttribute('data-sort') === sortState.key) t.classList.add(sortState.dir === 'asc' ? 'sort-asc' : 'sort-desc');
  });
  Array.prototype.slice.call(document.querySelectorAll('#sort-menu button[data-key]')).forEach(function(b) {
    var active = b.getAttribute('data-key') === sortState.key;
    b.classList.toggle('active', active);
    b.textContent = b.getAttribute('data-label') + (active ? arrow : '');
  });
}
function doSort(key, dir) {
  sortState.key = key; sortState.dir = dir;
  sortItems(key, dir);
  applySortUI();
  try { localStorage.setItem('explorer-sort', key + '|' + dir); } catch(e) {}
}
// Header senarai (klik untuk susun) - berkongsi sortState dengan butang sort
Array.prototype.slice.call(document.querySelectorAll('#view-list th[data-sort]')).forEach(function(th) {
  th.addEventListener('click', function() {
    var key = th.getAttribute('data-sort');
    var dir = (sortState.key === key) ? (sortState.dir === 'asc' ? 'desc' : 'asc') : 'asc';
    doSort(key, dir);
  });
});
// Butang sort: badan = tokok arah; caret = buka menu jenis susunan
(function() {
  var wrap = document.querySelector('.sort-wrap');
  if (!wrap) return;
  var btn = document.getElementById('sort-btn');
  var caret = document.getElementById('sort-caret');
  var menu = document.getElementById('sort-menu');
  if (btn) btn.addEventListener('click', function(e) {
    e.stopPropagation();
    doSort(sortState.key, sortState.dir === 'asc' ? 'desc' : 'asc');
  });
  if (caret) caret.addEventListener('click', function(e) {
    e.stopPropagation();
    if (menu) menu.classList.toggle('hidden');
  });
  if (menu) Array.prototype.slice.call(menu.querySelectorAll('button[data-key]')).forEach(function(it) {
    it.addEventListener('click', function(e) {
      e.stopPropagation();
      var key = it.getAttribute('data-key');
      var dir = (key === sortState.key) ? (sortState.dir === 'asc' ? 'desc' : 'asc') : 'asc';
      menu.classList.add('hidden');
      doSort(key, dir);
    });
  });
  document.addEventListener('click', function() { if (menu) menu.classList.add('hidden'); });
})();
// Init: guna susunan tersimpan (default Nama menaik)
(function() {
  try {
    var saved = localStorage.getItem('explorer-sort');
    if (saved) {
      var p = saved.split('|');
      if (SORT_LABELS[p[0]]) sortState.key = p[0];
      if (p[1] === 'desc') sortState.dir = 'desc';
    }
  } catch(e) {}
  sortItems(sortState.key, sortState.dir);
  applySortUI();
})();

// --- Inline preview lightbox ---
function openLb(href, name, kind) {
  var body = document.getElementById('lb-body');
  body.innerHTML = '';
  if (kind === 'img')        body.innerHTML = '<img src="' + href + '" alt="">';
  else if (kind === 'video') body.innerHTML = '<video controls autoplay src="' + href + '"></video>';
  else if (kind === 'audio') body.innerHTML = '<audio controls autoplay src="' + href + '"></audio>';
  else if (kind === 'pdf')   body.innerHTML = '<iframe src="' + href + '"></iframe>';
  else if (kind === 'text') {
    body.innerHTML = '<pre class="lb-pre">Memuat…</pre>';
    fetch(href).then(function(r) { return r.text(); }).then(function(t) {
      var max = 1048576; // cap ~1MB
      body.querySelector('.lb-pre').textContent = t.length > max ? t.slice(0, max) + '\n\n…(dipangkas)' : t;
    }).catch(function() {
      body.querySelector('.lb-pre').textContent = 'Gagal memuatkan fail untuk pratonton.';
    });
  }
  document.getElementById('lb-title').textContent = name;
  var dl = document.getElementById('lb-dl');
  dl.setAttribute('href', href);
  dl.setAttribute('download', name);
  document.getElementById('lightbox').classList.remove('hidden');
  document.body.style.overflow = 'hidden';
}
function closeLb() {
  document.getElementById('lightbox').classList.add('hidden');
  document.getElementById('lb-body').innerHTML = '';
  document.body.style.overflow = '';
}
document.getElementById('lb-close').addEventListener('click', closeLb);
document.getElementById('lightbox').addEventListener('click', function(e) { if (e.target === this) closeLb(); });
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeLb(); });
Array.prototype.slice.call(document.querySelectorAll('[data-preview]')).forEach(function(el) {
  el.addEventListener('click', function(e) {
    e.preventDefault();
    e.stopPropagation();
    openLb(el.getAttribute('href'), el.getAttribute('data-name') || el.textContent.trim(), el.getAttribute('data-preview'));
  });
});

// --- Per-item Save As (download button on each file) ---
Array.prototype.slice.call(document.querySelectorAll('.dl-btn')).forEach(function(el) {
  function saveAs(e) {
    e.preventDefault();
    e.stopPropagation();
    var a = document.createElement('a');
    a.href = el.getAttribute('data-href');
    a.download = el.getAttribute('data-name') || '';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
  }
  el.addEventListener('click', saveAs);
  el.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); saveAs(e); }
  });
});

// --- List mode: klik mana-mana baris ikut link utamanya (folder / fail / ".." kembali) ---
(function(){
  var tbody = document.querySelector('#view-list tbody');
  if (!tbody) return;
  tbody.addEventListener('click', function(e){
    if (e.target.closest('a, .dl-btn, th')) return;   // biar link & butang buat kerja sendiri
    var tr = e.target.closest('tr');
    if (!tr) return;
    var a = tr.querySelector('td.name a');
    if (!a) return;
    var href = a.getAttribute('href');
    if (!href) return;
    var prev = a.getAttribute('data-preview');
    if (prev) openLb(href, a.getAttribute('data-name') || a.textContent.trim(), prev);
    else window.location.href = href;
  });
})();

// --- Load more (progressive reveal) ---
(function(){
  var STEP = <?= (int)$REVEAL ?>;
  var step = STEP;
  var total = document.querySelectorAll('#view-grid .card[data-idx]').length;
  var shown = Math.min(step, total);
  var bar = document.getElementById('more-bar');
  var btn = document.getElementById('more-btn');
  var caret = document.getElementById('more-caret');
  var menu = document.getElementById('more-menu');

  function applyReveal(){
    var gc = document.querySelectorAll('#view-grid .card[data-idx]');
    var lr = document.querySelectorAll('#view-list tbody tr[data-idx]');
    for (var i = 0; i < gc.length; i++) gc[i].classList.toggle('hidden', i >= shown);
    for (var j = 0; j < lr.length; j++) lr[j].classList.toggle('hidden', j >= shown);
    if (bar) bar.classList.toggle('hidden', shown >= total);
  }
  function loadMore(n){
    if (n === 'all') shown = total;
    else shown = Math.min(total, shown + n);
    applyReveal();
  }
  if (btn) btn.addEventListener('click', function(){ loadMore(step); });
  if (caret) caret.addEventListener('click', function(e){
    e.stopPropagation();
    if (menu) menu.classList.toggle('hidden');
  });
  if (menu) Array.prototype.slice.call(menu.querySelectorAll('button[data-step]')).forEach(function(it){
    it.addEventListener('click', function(e){
      e.stopPropagation();
      var raw = it.getAttribute('data-step');
      step = (raw === 'all') ? 'all' : parseInt(raw, 10);
      if (btn) btn.textContent = it.textContent;
      menu.classList.add('hidden');
      loadMore(step === 'all' ? 'all' : step);
    });
  });
  document.addEventListener('click', function(){ if (menu) menu.classList.add('hidden'); });
  // Reset paparan ke muka pertama (dipanggil oleh butang terapung scroll-to-top).
  window.MWE_reveal_top = function(){
    shown = Math.min(STEP, total);
    step = STEP;
    if (btn) btn.textContent = 'Lagi ' + STEP;
    applyReveal();
  };
  window.MWE_reveal = applyReveal;
  applyReveal();
})();

// --- Butang terapung scroll-to-top (sentiasa ada bila halaman boleh scroll ke atas) ---
(function(){
  var fab = document.getElementById('top-fab');
  if (!fab) return;
  function onScroll(){
    if (window.scrollY > 50) fab.classList.add('show');
    else fab.classList.remove('show');
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  window.addEventListener('resize', onScroll);
  fab.addEventListener('click', function(){
    if (window.MWE_reveal_top) window.MWE_reveal_top();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });
  onScroll();
})();

// --- Download Semua (Zip): semak saiz dulu, popup kalau melebihi had ---
(function(){
  var zb = document.querySelector('.zip-btn');
  if (!zb) return;
  var modal  = document.getElementById('zip-warn');
  var closeB = document.getElementById('zw-close');
  var msgEl  = document.getElementById('zw-msg');
  function gb(b){ return (b / 1073741824).toFixed(2) + ' GB'; }
  function open(msg){ if (msgEl) msgEl.textContent = msg; if (modal){ modal.classList.remove('hidden'); document.body.style.overflow = 'hidden'; } }
  function close(){ if (modal){ modal.classList.add('hidden'); document.body.style.overflow = ''; } }
  zb.addEventListener('click', function(e){
    e.preventDefault();
    var dl    = zb.getAttribute('href');
    var check = zb.getAttribute('data-check');
    fetch(check, { headers: { 'Accept': 'application/json' } })
      .then(function(r){ return r.ok ? r.json() : { ok: false }; })
      .then(function(d){
        if (d && d.ok === true) {
          window.location.href = dl;  // saiz OK -> stream download
        } else {
          var total = (d && typeof d.total === 'number') ? gb(d.total) : '?';
          var max   = (d && typeof d.max === 'number') ? gb(d.max) : '10 GB';
          var cnt   = (d && typeof d.count === 'number') ? d.count.toLocaleString() : '0';
          open('Jumlah saiz folder ini ialah ' + total + ' (' + cnt + ' fail). Had "Download Semua (Zip)" ialah ' + max + '.');
        }
      })
      .catch(function(){ window.location.href = dl; });  // fallback: terus download
  });
  if (closeB) closeB.addEventListener('click', close);
  if (modal) modal.addEventListener('click', function(e){ if (e.target === modal) close(); });
  document.addEventListener('keydown', function(e){ if (e.key === 'Escape') close(); });
})();
</script>
</body>
</html>
