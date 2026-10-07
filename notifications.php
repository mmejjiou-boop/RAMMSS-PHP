<?php
// Notifications : ouverture d'une notification et « tout marquer comme lu » (Story 12)
require_once __DIR__ . '/includes/functions.php';

$user = require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (($_POST['action'] ?? '') === 'read_all') {
        $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$user['id']]);
        flash('success', 'Toutes vos notifications ont été marquées comme lues.');
    }
    redirect(safe_redirect_target($_POST['redirect'] ?? null, 'notifications.php'));
}

// Ouverture d'une notification : marquée comme lue puis redirection vers la requête concernée
if (isset($_GET['open'])) {
    $stmt = $pdo->prepare('SELECT * FROM notifications WHERE id = ? AND user_id = ?');
    $stmt->execute([(int) $_GET['open'], $user['id']]);
    $notif = $stmt->fetch();
    if (!$notif) {
        flash('danger', 'Notification introuvable.');
        redirect('notifications.php');
    }
    $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ?')->execute([$notif['id']]);
    redirect($notif['request_id'] ? 'detail.php?id=' . (int) $notif['request_id'] : 'notifications.php');
}

$stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY is_read ASC, created_at DESC, id DESC LIMIT 100');
$stmt->execute([$user['id']]);
$all = $stmt->fetchAll();
$unread = count(array_filter($all, fn($n) => !$n['is_read']));

$pageTitle = 'Mes notifications';
require __DIR__ . '/includes/header.php';
?>

  <main class="container-narrow" style="padding-block: 2.5rem 4rem;">
    <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-bottom: 2rem;">
      <div>
        <h1 style="font-size: 2rem; font-weight: 800; color: var(--text-main);">Mes notifications</h1>
        <p style="font-size: 0.95rem; color: var(--text-muted); margin-top: 0.35rem;">
          <?= $unread ?> non lue<?= $unread > 1 ? 's' : '' ?> · <a href="settings.php">Gérer mes préférences</a>
        </p>
      </div>
      <?php if ($unread > 0): ?>
        <form method="post" action="notifications.php">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="read_all">
          <button type="submit" class="btn btn-secondary">Tout marquer comme lu</button>
        </form>
      <?php endif; ?>
    </div>

    <section class="form-card" style="padding: 0; overflow: hidden;">
      <?php if (!$all): ?>
        <div class="notif-item"><div><p>Aucune notification pour le moment.</p></div></div>
      <?php endif; ?>
      <?php foreach ($all as $n): ?>
        <a href="notifications.php?open=<?= (int) $n['id'] ?>" class="notif-item<?= $n['type'] === 'validated' ? ' valid' : '' ?><?= $n['is_read'] ? ' is-read' : '' ?>">
          <span class="notif-icon"><?= notif_icon($n['type']) ?></span>
          <div>
            <strong><?= e($n['title']) ?></strong>
            <p><?= e($n['message']) ?></p>
            <span style="font-size: 0.75rem; color: var(--text-muted);"><?= e(time_ago($n['created_at'])) ?></span>
          </div>
        </a>
      <?php endforeach; ?>
    </section>
  </main>

<?php require __DIR__ . '/includes/footer.php'; ?>
