<?php
/* ═══════════════════════════════════════════════════════════════════
   DAN ATELIER 3D — Synchronisation admin <-> site vitrine
   ───────────────────────────────────────────────────────────────────
   À placer sur le serveur, DANS LE MÊME DOSSIER que index.html
   et admin.html.

   ⚠️ IMPORTANT : changez la clé ci-dessous avant la mise en ligne.
   C'est cette même clé que l'admin vous demandera au premier clic
   sur « Publier en ligne ».

   Ce script gère 3 actions :
   1. publish      (clé requise)  -> écrit data.json (œuvres + contenu du site)
   2. event        (sans clé)     -> un visiteur passe commande / réserve :
                                     ajouté à events.json
   3. pull_events  (clé requise)  -> l'admin relève les commandes et
                                     events.json est vidé
   ═══════════════════════════════════════════════════════════════════ */

$CLE_DE_PUBLICATION = 'CHANGEZ-MOI-danatelier-2026';

// ───────────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée — POST uniquement']);
    exit;
}

$brut  = file_get_contents('php://input');
$corps = json_decode($brut, true);
if (!is_array($corps)) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON invalide']);
    exit;
}

$action  = $corps['action'] ?? 'publish';
$dossier = __DIR__;

/* ── 2. Événement visiteur (commande, réservation, client) ───────── */
if ($action === 'event') {
    $type = $corps['type'] ?? '';
    if (!in_array($type, ['order', 'reservation', 'client'], true) || !isset($corps['data'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Événement invalide']);
        exit;
    }
    $fichier = $dossier . '/events.json';
    $liste = [];
    if (is_file($fichier)) {
        $liste = json_decode(file_get_contents($fichier), true);
        if (!is_array($liste)) $liste = [];
    }
    // Garde-fou : maximum 500 événements en attente
    if (count($liste) >= 500) array_shift($liste);
    $liste[] = ['type' => $type, 'data' => $corps['data'], 'recu_le' => date('c')];
    file_put_contents($fichier, json_encode($liste, JSON_UNESCAPED_UNICODE), LOCK_EX);
    echo json_encode(['ok' => true]);
    exit;
}

/* ── Actions protégées par la clé ────────────────────────────────── */
$cle = $_SERVER['HTTP_X_PUBLISH_KEY'] ?? '';
if (!hash_equals($CLE_DE_PUBLICATION, $cle)) {
    http_response_code(403);
    echo json_encode(['error' => 'Clé de publication invalide']);
    exit;
}

/* ── 3. Relever les événements visiteurs ─────────────────────────── */
if ($action === 'pull_events') {
    $fichier = $dossier . '/events.json';
    $liste = '[]';
    if (is_file($fichier)) {
        $liste = file_get_contents($fichier) ?: '[]';
        file_put_contents($fichier, '[]', LOCK_EX); // vidé une fois relevé
    }
    echo $liste;
    exit;
}

/* ── 1. Publication des données du site ──────────────────────────── */
$donnees = [
    'publishedAt' => $corps['publishedAt'] ?? date('c'),
    'artworks'    => is_array($corps['artworks'] ?? null) ? $corps['artworks'] : [],
    'siteConfig'  => $corps['siteConfig'] ?? new stdClass(),
];
$ok = file_put_contents(
    $dossier . '/data.json',
    json_encode($donnees, JSON_UNESCAPED_UNICODE),
    LOCK_EX
);
if ($ok === false) {
    http_response_code(500);
    echo json_encode(['error' => "Écriture impossible — vérifiez les droits du dossier (chmod 755, fichiers 644)"]);
    exit;
}
echo json_encode(['ok' => true, 'publishedAt' => $donnees['publishedAt']]);
