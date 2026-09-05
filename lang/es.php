<?php
/**
 * Maui Web Explorer - archivo de idioma: Español
 *
 * CÓMO AÑADIR UN NUEVO IDIOMA:
 *   1. Copie este archivo y cámbiele el nombre por el código del idioma
 *      (p. ej.: de.php = alemán).
 *   2. Cambie solo los VALORES (lado derecho) - NO cambie las claves (lado izquierdo).
 *   3. Registre el idioma en la lista $LANGS de index.php (código => nombre visible).
 *
 * NOTAS:
 *   - %d y %s son marcadores de posición (números / tamaños de archivo) - CONSÉRVELOS.
 *   - %1$s, %2$s, %3$s son marcadores numerados - consérvelos todos y reordénelos
 *     según la gramática de su idioma.
 *   - Las claves que falten volverán automáticamente al malayo.
 */
return [
    // === Menú de idioma y búsqueda ===
    'lang_title'   => 'Idioma',
    'search_ph'    => 'Buscar en esta carpeta…',
    'clear_search' => 'Borrar búsqueda',

    // === Barra de herramientas ===
    'zip_dl'   => 'Descargar todo (Zip)',
    'sort_inc' => 'Cambiar dirección de ordenación',
    'sort_pick'=> 'Elegir tipo de ordenación',
    'v_grid'   => 'Vista de cuadrícula',
    'v_list'   => 'Vista de lista',
    'v_theme'  => 'Cambiar tema',
    'info'     => '%d carpetas, %d archivos',
    'info_q'   => '%d resultados',

    // === Opciones de ordenación ===
    's_name'  => 'Nombre',
    's_size'  => 'Tamaño',
    's_ctime' => 'Creado',
    's_mtime' => 'Modificado',
    's_ext'   => 'Tipo',

    // === Lista / tarjetas ===
    'lh_name' => 'Nombre',
    'lh_size' => 'Tamaño',
    'lh_mod'  => 'Modificado',
    'up'      => 'Arriba',
    'dir'     => 'Carpeta',
    'save_as' => 'Guardar archivo (Save As)',

    // === Estados vacíos ===
    'e_search' => 'Ningún archivo coincide con la búsqueda.',
    'e_sub'    => 'Esta carpeta está vacía.',
    'e_root'   => 'Carpeta vacía - no hay nada que mostrar.',

    // === Botón cargar más ===
    'more'      => '%d más',
    'all'       => 'Todos',
    'more_aria' => 'Cargar más elementos',
    'more_pick' => 'Elegir cantidad',

    // === Varios ===
    'top'     => 'Volver arriba',
    'die404'  => 'Directorio no encontrado.',
    'die404b' => 'No se puede leer el directorio.',

    // === Lightbox (vista previa) ===
    'lb_dl'   => '⬇ Descargar',
    'lb_close'=> 'Cerrar',
    'lb_load' => 'Cargando…',
    'lb_fail' => 'Error al cargar el archivo para la vista previa.',
    'lb_trim' => '…(recortado)',

    // === Advertencia Zip (emergente) ===
    'zw_title' => 'Carpeta demasiado grande para descargarla como Zip',
    'zw_msg'   => 'Esta carpeta suma %1$s (%2$s archivos). El límite de "Descargar todo (Zip)" es %3$s.',
    'zw_hint'  => 'Descargue los archivos uno por uno con el botón guardar (⬇) de cada elemento.',
    'zw_ok'    => 'Aceptar',

    // === Página de advertencia 413 (carpeta demasiado grande) ===
    'p_title' => 'Carpeta demasiado grande',
    'p_h1'    => 'Carpeta demasiado grande para descargarla como Zip',
    'p_p1'    => 'Esta carpeta suma %1$s (%2$s archivos).',
    'p_p2'    => 'El límite de "Descargar todo (Zip)" es %3$s. Descargue los archivos uno por uno con el botón guardar de cada elemento.',
    'p_back'  => 'Volver a la carpeta',
];
