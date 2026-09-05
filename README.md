# Maui Web Explorer

Single-file PHP file explorer and media browser. **Zero dependencies. Zero database. Zero build step.** Drop in one `index.php` and done.

<img src="Screenshots/001.png?v=3" alt="Maui Web Explorer grid view" width="600">

## Features

- Browse any server folder: grid/list view, breadcrumbs, dark/light theme (saved in browser)
- Streaming download with HTTP Range support (seek/resume), per-item Save As button
- "Download Semua (Zip)": streaming ZIP (STORE), refused above 10 GB / 65,534 files with a friendly warning
- Inline lightbox preview: images, video, audio, PDF, text/code
- Server-side search, progressive "Lagi N" reveal, click-to-sort (name/size/created/modified/type)
- Symlink friendly: symlinks inside the base folder are followed; `..` traversal is blocked (both `/` and `\`)
- Hidden by default: `.htaccess`, `.htpasswd`, `.git`, `.gitignore`, `.env`, and the script itself

## Requirements

PHP 7.4+ (core only, `fileinfo` optional). Any server that runs PHP.

## Install

Place `index.php` in the folder to share. Easiest way to try:

```bash
php -S localhost:8000
```

For Apache/Nginx/LiteSpeed/IIS/Caddy, drop `index.php` in the document root and make sure `index.php` is the directory index. For large-file streaming, use a generous read timeout (e.g. Nginx `fastcgi_read_timeout 600s`).

## Configuration

Edit the `// === Config ===` block at the top of `index.php`:

```php
$SITE_NAME = 'Maui Web Explorer'; // header/footer name
$BASE_DIR  = __DIR__;             // folder to browse
$HIDDEN    = [...];               // entries never shown
$REVEAL    = 15;                  // items shown before the "Lagi N" button
$ZIP_MAX   = 10737418240;         // zip download limit (10 GB)
```

## Security (public use)

No PHP login layer by design. Protect the entry at the web-server level (see `htaccess.example` for Basic Auth) and serve over HTTPS. Symlinks are followed to their real target, so only symlink content you intend to share.

## License

BSD 2-Clause - see [LICENSE](LICENSE). Author: [@azmawee](https://github.com/azmawee).
