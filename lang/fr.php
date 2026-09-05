<?php
/**
 * Maui Web Explorer - fichier de langue : Français
 *
 * COMMENT AJOUTER UNE NOUVELLE LANGUE :
 *   1. Copiez ce fichier et renommez-le avec le code de la langue
 *      (ex. : de.php = allemand).
 *   2. Modifiez uniquement les VALEURS (côté droit) - ne changez PAS les clés
 *      (côté gauche).
 *   3. Enregistrez la langue dans la liste $LANGS de index.php (code => nom affiché).
 *
 * NOTES :
 *   - %d et %s sont des espaces réservés (nombres / tailles de fichiers) -
 *     CONSERVEZ-LES dans les traductions.
 *   - %1$s, %2$s, %3$s sont des espaces réservés numérotés - conservez-les tous
 *     et réordonnez-les selon la grammaire de votre langue.
 *   - Les clés manquantes retombent automatiquement en malais.
 */
return [
    // === Menu de langue et recherche ===
    'lang_title'   => 'Langue',
    'search_ph'    => 'Rechercher dans ce dossier…',
    'clear_search' => 'Effacer la recherche',

    // === Barre d'outils ===
    'zip_dl'   => 'Tout télécharger (Zip)',
    'sort_inc' => 'Inverser le sens du tri',
    'sort_pick'=> 'Choisir le type de tri',
    'v_grid'   => 'Vue grille',
    'v_list'   => 'Vue liste',
    'v_theme'  => 'Changer de thème',
    'info'     => '%d dossiers, %d fichiers',
    'info_q'   => '%d résultats',

    // === Options de tri ===
    's_name'  => 'Nom',
    's_size'  => 'Taille',
    's_ctime' => 'Créé',
    's_mtime' => 'Modifié',
    's_ext'   => 'Type',

    // === Liste / cartes ===
    'lh_name' => 'Nom',
    'lh_size' => 'Taille',
    'lh_mod'  => 'Modifié',
    'up'      => 'Haut',
    'dir'     => 'Dossier',
    'save_as' => 'Enregistrer le fichier (Save As)',

    // === États vides ===
    'e_search' => 'Aucun fichier ne correspond à la recherche.',
    'e_sub'    => 'Ce dossier est vide.',
    'e_root'   => 'Dossier vide - rien à afficher.',

    // === Bouton charger plus ===
    'more'      => '%d de plus',
    'all'       => 'Tout',
    'more_aria' => 'Charger plus d\'éléments',
    'more_pick' => 'Choisir le nombre',

    // === Divers ===
    'top'     => 'Haut de page',
    'die404'  => 'Répertoire introuvable.',
    'die404b' => 'Impossible de lire le répertoire.',

    // === Visionneuse (aperçu intégré) ===
    'lb_dl'   => '⬇ Télécharger',
    'lb_close'=> 'Fermer',
    'lb_load' => 'Chargement…',
    'lb_fail' => 'Échec du chargement du fichier pour l\'aperçu.',
    'lb_trim' => '…(tronqué)',

    // === Avertissement Zip (fenêtre) ===
    'zw_title' => 'Dossier trop volumineux pour être téléchargé en Zip',
    'zw_msg'   => 'Ce dossier totalise %1$s (%2$s fichiers). La limite de « Tout télécharger (Zip) » est %3$s.',
    'zw_hint'  => 'Veuillez télécharger les fichiers un par un avec le bouton d\'enregistrement (⬇) de chaque élément.',
    'zw_ok'    => 'D\'accord',

    // === Page d'avertissement 413 (dossier trop volumineux) ===
    'p_title' => 'Dossier trop volumineux',
    'p_h1'    => 'Dossier trop volumineux pour être téléchargé en Zip',
    'p_p1'    => 'Ce dossier totalise %1$s (%2$s fichiers).',
    'p_p2'    => 'La limite de « Tout télécharger (Zip) » est %3$s. Veuillez télécharger les fichiers un par un avec le bouton d\'enregistrement de chaque élément.',
    'p_back'  => 'Retour au dossier',
];
