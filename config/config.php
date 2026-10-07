<?php
// Configuration de connexion MySQL (valeurs par défaut de MAMP Windows)
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '8889');
define('DB_NAME', 'rammss');
define('DB_USER', 'root');
define('DB_PASS', 'root');

// Règles métier de la plateforme
define('VOTE_THRESHOLD', 100);      // Nombre de votes « Pour » requis
define('THRESHOLD_MIN_PCT', 60);    // Pourcentage minimum d'avis favorables
define('ADMIN_SECRET_KEY', 'RAMMSS-ADMIN-2026'); // Clé d'accréditation staff
define('ADMIN_EMAIL_DOMAINS', ['coda.school', 'campus.fr']);
