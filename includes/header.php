<?php
/**
 * En-tête commun.
 * Variables attendues : $pageTitle, $pageDescription (optionnel), $activeNav (optionnel), $extraHead (optionnel)
 */
$user = current_user();
$activeNav = $activeNav ?? '';
$theme = $user['theme'] ?? 'auto';
$navClass = fn(string $key) => 'nav-link' . ($activeNav === $key ? ' active' : '');
?>
<!DOCTYPE html>
<html lang="fr"<?= $theme !== 'auto' ? ' data-theme="' . e($theme) . '"' : '' ?>>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= e($pageDescription ?? 'Campus Life - Groupe RAMMSS : Plateforme démocratique pour soumettre, consulter et voter pour les requêtes étudiantes.') ?>">
  <title><?= e($pageTitle) ?> | Campus Life RAMMSS</title>
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/app.css">
  <?= $extraHead ?? '' ?>
</head>
<body>

  <?php if (!empty($toast)): ?>
  <!-- Alerte Notification en tête (Story 12 - Notification de validation) -->
  <aside class="toast-bar" role="status" aria-live="polite">
    <div class="container toast-inner">
      <div class="toast-content">
        <span aria-hidden="true">🎉</span>
        <p><strong>Excellente nouvelle !</strong> <?= e($toast['message']) ?> <strong>officiellement validée</strong> par l'administration du campus.</p>
      </div>
      <a href="notifications.php?open=<?= (int) $toast['id'] ?>" class="toast-link">Voir la validation &rarr;</a>
    </div>
  </aside>
  <?php endif; ?>

  <!-- En-tête Principal & Navigation -->
  <header class="site-header">
    <div class="container header-inner">
      <a href="index.php" class="brand-link" aria-label="Accueil Campus Life">
        <img src="assets/logo.svg" alt="Logo Campus Life RAMMSS" width="200" height="48">
      </a>

      <nav class="main-nav" aria-label="Navigation principale">
        <a href="index.php" class="<?= $navClass('index') ?>">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
          Fil des requêtes
        </a>
        <a href="create.php" class="<?= $navClass('create') ?>">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
          Nouvelle requête
        </a>
        <?php if (is_admin()): ?>
        <a href="admin.php" class="<?= $navClass('admin') ?>">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
          Espace Admin
        </a>
        <?php endif; ?>
        <a href="settings.php" class="<?= $navClass('settings') ?>">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
          Paramètres
        </a>
      </nav>

      <div class="header-actions">
        <?php if ($user): ?>
          <?php $unread = unread_notifications_count((int) $user['id']); $notifs = latest_notifications((int) $user['id']); ?>
          <!-- Notification Dropdown (Story 12) -->
          <div class="notification-wrapper" tabindex="0">
            <button class="btn-icon" aria-label="Afficher les notifications (<?= $unread ?> non lues)">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
              <?php if ($unread > 0): ?><span class="unread-badge"><?= $unread ?></span><?php endif; ?>
            </button>
            <div class="notification-dropdown">
              <div class="notification-header">
                <span>Notifications Campus</span>
                <span class="badge badge-validated"><?= $unread ?> non lue<?= $unread > 1 ? 's' : '' ?></span>
              </div>
              <?php if (!$notifs): ?>
                <div class="notif-item"><div><p>Aucune notification pour le moment.</p></div></div>
              <?php endif; ?>
              <?php foreach ($notifs as $n): ?>
                <a href="notifications.php?open=<?= (int) $n['id'] ?>" class="notif-item<?= $n['type'] === 'validated' ? ' valid' : '' ?><?= $n['is_read'] ? ' is-read' : '' ?>">
                  <span class="notif-icon"><?= notif_icon($n['type']) ?></span>
                  <div>
                    <strong><?= e($n['title']) ?></strong>
                    <p><?= e($n['message']) ?></p>
                  </div>
                </a>
              <?php endforeach; ?>
              <?php if ($unread > 0): ?>
                <form method="post" action="notifications.php" class="notif-footer">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="read_all">
                  <button type="submit" class="btn btn-secondary btn-sm" style="width: 100%;">Tout marquer comme lu</button>
                </form>
              <?php endif; ?>
            </div>
          </div>

          <a href="settings.php" class="user-profile-badge" title="Connecté en tant que <?= e(short_name($user)) ?>">
            <div class="avatar"><?= e(initials($user['first_name'], $user['last_name'])) ?></div>
            <span><?= e(short_name($user)) ?></span>
          </a>
          <a href="logout.php" class="btn btn-secondary btn-sm" title="Se déconnecter">Déconnexion</a>
        <?php else: ?>
          <a href="login.php" class="btn btn-primary btn-sm">Se connecter</a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <?php $flashes = get_flashes(); if ($flashes): ?>
  <div class="container flash-container">
    <?php foreach ($flashes as $f): ?>
      <div class="alert alert-<?= e($f['type']) ?>" role="alert"><div><?= e($f['message']) ?></div></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
