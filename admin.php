<?php
// Espace de gestion des requêtes (Story 7) & validation officielle (Story 12)
require_once __DIR__ . '/includes/functions.php';

$admin = require_admin();
$pdo = db();

// --- Actions de modération ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int) ($_POST['request_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $r = find_request($id);
    $back = safe_redirect_target($_POST['redirect'] ?? null, 'admin.php');

    if (!$r) {
        flash('danger', 'Requête introuvable.');
        redirect($back);
    }
    $ref = request_ref($id);
    $short = mb_strimwidth($r['title'], 0, 45, '…');
    $adminName = $admin['first_name'] . ' ' . $admin['last_name'];

    switch ($action) {
        case 'approve':
            if ($r['status'] === 'pending') {
                $pdo->prepare("UPDATE requests SET status = 'voting', moderated_at = NOW() WHERE id = ?")->execute([$id]);
                notify((int) $r['user_id'], $id, 'moderation', 'Requête publiée', "« $short » a été approuvée et est ouverte au vote.");
                audit((int) $admin['id'], $id, 'approved', "Requête $ref ($short) approuvée pour mise au vote public par $adminName.");
                flash('success', "La requête $ref est désormais ouverte au vote.");
                check_threshold($id);
            }
            break;

        case 'reject':
            if ($r['status'] !== 'validated') {
                $reason = trim($_POST['reason'] ?? '') ?: null;
                $pdo->prepare("UPDATE requests SET status = 'rejected', moderated_at = NOW(), decision_comment = ? WHERE id = ?")->execute([$reason, $id]);
                notify((int) $r['user_id'], $id, 'moderation', 'Requête rejetée', "« $short » n'a pas été retenue par la modération.");
                audit((int) $admin['id'], $id, 'rejected', "Requête $ref ($short) rejetée et archivée par $adminName.");
                flash('success', "La requête $ref a été rejetée.");
            }
            break;

        case 'validate':
            if ($r['status'] === 'threshold') {
                $comment = trim($_POST['decision_comment'] ?? '') ?: 'La commission a approuvé la mise en œuvre de cette requête.';
                $pdo->prepare("UPDATE requests SET status = 'validated', validated_at = NOW(), decision_comment = ? WHERE id = ?")->execute([$comment, $id]);
                notify_participants($r, 'notif_validation', 'validated', 'Requête validée !', "« $short » a été approuvée par l'administration.");
                audit((int) $admin['id'], $id, 'validated', "Requête $ref ($short) validée par $adminName. Notification envoyée à l'auteur et aux votants.");
                flash('success', "La requête $ref est officiellement validée. L'auteur et les votants ont été notifiés.");
            }
            break;

        case 'precision':
            $message = trim($_POST['message'] ?? '');
            if ($message !== '') {
                notify((int) $r['user_id'], $id, 'info', 'Précisions demandées', mb_strimwidth("Admin : $message", 0, 255, '…'));
                audit((int) $admin['id'], $id, 'precision', "Précisions demandées à l'auteur de la requête $ref ($short).");
                flash('success', "Votre demande de précisions a été envoyée à l'auteur de $ref.");
            }
            break;

        case 'dismiss_report':
            $pdo->prepare('UPDATE requests SET is_reported = 0, report_reason = NULL WHERE id = ?')->execute([$id]);
            audit((int) $admin['id'], $id, 'approved', "Signalement de la requête $ref ($short) levé par $adminName.");
            flash('success', "Le signalement de $ref a été levé.");
            check_threshold($id);
            break;
    }
    redirect($back);
}

// --- Filtres du tableau ---
$states = [
    'all'       => 'Tous les états',
    'threshold' => 'Seuil atteint (Prêt commission)',
    'pending'   => 'En attente de modération',
    'reported'  => 'Signalées',
    'voting'    => 'Vote en cours',
    'validated' => 'Validées',
    'rejected'  => 'Rejetées',
];
$state = array_key_exists($_GET['state'] ?? '', $states) ? $_GET['state'] : 'all';
$q = trim($_GET['q'] ?? '');

$where = ['1 = 1'];
$params = [];
if ($state === 'reported') {
    $where[] = 'r.is_reported = 1 AND r.status <> \'rejected\'';
} elseif ($state !== 'all') {
    $where[] = 'r.status = :status';
    $params['status'] = $state;
}
if ($q !== '') {
    $where[] = '(r.title LIKE :q1 OR r.description LIKE :q2)';
    $params['q1'] = $params['q2'] = '%' . $q . '%';
}
$stmt = $pdo->prepare(REQUEST_SELECT . ' WHERE ' . implode(' AND ', $where) . "
    ORDER BY (r.is_reported = 1 AND r.status <> 'rejected') DESC, FIELD(r.status, 'threshold', 'pending', 'voting', 'validated', 'rejected'), r.created_at DESC");
$stmt->execute($params);
$requests = $stmt->fetchAll();

$kpi = $pdo->query("
    SELECT
        SUM(status = 'pending') AS pending,
        SUM(status = 'threshold') AS threshold,
        SUM(status = 'validated') AS validated,
        SUM(is_reported = 1 AND status <> 'rejected') AS reported
    FROM requests
")->fetch();

$audit = $pdo->query('
    SELECT a.*, u.first_name, u.last_name FROM audit_log a
    LEFT JOIN users u ON u.id = a.admin_id
    ORDER BY a.created_at DESC, a.id DESC LIMIT 10
')->fetchAll();
$auditBadges = [
    'approved'  => ['badge-voting', 'Approuvé'],
    'rejected'  => ['badge-rejected', 'Rejeté'],
    'validated' => ['badge-validated', 'Validé'],
    'threshold' => ['badge-threshold', 'Palier ' . VOTE_THRESHOLD],
    'reported'  => ['badge-rejected', 'Signalé'],
    'precision' => ['badge-pending', 'Précisions'],
];

$selfUrl = 'admin.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');

$pageTitle = 'Espace Administration & Modération';
$activeNav = 'admin';
require __DIR__ . '/includes/header.php';
?>

  <main class="container" style="padding-block: 2.5rem 4rem;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
      <div>
        <span class="badge badge-validated">Espace de Modération & Commission (Story 7)</span>
        <h1 style="font-size: 2rem; font-weight: 800; color: var(--text-main); margin-top: 0.25rem;">
          Gestion des requêtes & Décisions campus
        </h1>
        <p style="font-size: 0.925rem; color: var(--text-muted); margin-top: 0.25rem;">
          Examinez les propositions soumises, modérez les contenus inappropriés et validez officiellement les projets ayant atteint le seuil des <?= VOTE_THRESHOLD ?> votes.
        </p>
      </div>
      <div style="display: flex; align-items: center; gap: 0.75rem;">
        <a href="admin-register.php" class="btn btn-secondary btn-sm">+ Inscrire un administrateur</a>
        <a href="#audit-log" class="btn btn-secondary btn-sm">Journal d'audit</a>
      </div>
    </div>

    <!-- KPIs -->
    <section class="admin-stats-grid" aria-label="Statistiques de modération">
      <a href="admin.php?state=pending" class="admin-stat-card" style="text-decoration: none; color: inherit;">
        <div>
          <div class="stat-value" style="color: var(--warning);"><?= (int) $kpi['pending'] ?></div>
          <div class="stat-label">À modérer en priorité</div>
        </div>
        <div class="btn-icon" style="background-color: var(--warning-light); color: var(--warning);">⏳</div>
      </a>
      <a href="admin.php?state=threshold" class="admin-stat-card" style="text-decoration: none; color: inherit;">
        <div>
          <div class="stat-value" style="color: var(--primary);"><?= (int) $kpi['threshold'] ?></div>
          <div class="stat-label">Seuil <?= VOTE_THRESHOLD ?> votes franchi</div>
        </div>
        <div class="btn-icon" style="background-color: var(--primary-light); color: var(--primary);">🎯</div>
      </a>
      <a href="admin.php?state=validated" class="admin-stat-card" style="text-decoration: none; color: inherit;">
        <div>
          <div class="stat-value" style="color: var(--success);"><?= (int) $kpi['validated'] ?></div>
          <div class="stat-label">Requêtes validées</div>
        </div>
        <div class="btn-icon" style="background-color: var(--success-light); color: var(--success);">✅</div>
      </a>
      <a href="admin.php?state=reported" class="admin-stat-card" style="text-decoration: none; color: inherit;">
        <div>
          <div class="stat-value" style="color: var(--danger);"><?= (int) $kpi['reported'] ?></div>
          <div class="stat-label">Requêtes signalées</div>
        </div>
        <div class="btn-icon" style="background-color: var(--danger-light); color: var(--danger);">⚠️</div>
      </a>
    </section>

    <!-- Table de gestion -->
    <section class="form-card" style="padding: 1.5rem; margin-bottom: 2rem;">
      <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.25rem;">
        <h2 style="font-size: 1.25rem; font-weight: 700;">File des requêtes à traiter</h2>
        <form method="get" action="admin.php" style="display: flex; align-items: center; gap: 0.5rem;">
          <input type="search" name="q" value="<?= e($q) ?>" class="form-control" placeholder="Filtrer la table..." style="padding: 0.4rem 0.8rem; font-size: 0.85rem; width: 220px;">
          <select name="state" class="form-control" style="padding: 0.4rem 0.8rem; font-size: 0.85rem; width: auto;" onchange="this.form.submit()">
            <?php foreach ($states as $key => $label): ?>
              <option value="<?= $key ?>"<?= $state === $key ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-secondary btn-sm">Filtrer</button>
        </form>
      </div>

      <div class="table-container">
        <table class="data-table">
          <thead>
            <tr>
              <th>Réf & Date</th>
              <th>Titre de la requête</th>
              <th>Auteur (ou Anonymat)</th>
              <th>Catégorie</th>
              <th>Votes (Pour / Contre)</th>
              <th>Statut actuel</th>
              <th>Actions de modération</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$requests): ?>
              <tr><td colspan="7" class="empty-state">Aucune requête ne correspond à ce filtre.</td></tr>
            <?php endif; ?>
            <?php foreach ($requests as $r): $s = vote_stats($r); $rid = (int) $r['id']; $reported = $r['is_reported'] && $r['status'] !== 'rejected'; ?>
            <tr>
              <td>
                <strong><?= request_ref($rid) ?></strong><br>
                <span class="post-date"><?= e(format_date_fr($r['created_at'], true)) ?></span>
              </td>
              <td>
                <a href="detail.php?id=<?= $rid ?>" style="color: var(--text-main);"><strong><?= e($r['title']) ?></strong></a><br>
                <?php if ($reported): ?>
                  <span style="font-size: 0.775rem; color: var(--danger);">Signalée : <?= e($r['report_reason']) ?></span>
                <?php else: ?>
                  <span style="font-size: 0.775rem; color: var(--text-muted);"><?= e(mb_strimwidth($r['description'], 0, 80, '…')) ?></span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($r['is_anonymous']): ?>
                  <span class="badge badge-anon">🎭 Anonyme</span><br>
                  <span style="font-size: 0.75rem; color: var(--text-muted);">Identité masquée</span>
                <?php else: ?>
                  <span style="font-weight: 600;"><?= e(short_name($r)) ?></span><br>
                  <span style="font-size: 0.75rem; color: var(--text-muted);"><?= e($r['promo']) ?></span>
                <?php endif; ?>
              </td>
              <td><span class="badge badge-category"><?= e($r['category_name']) ?></span></td>
              <td>
                <strong style="color: var(--success);"><?= $s['pro'] ?> 👍</strong> / <span style="color: var(--danger);"><?= $s['con'] ?> 👎</span><br>
                <span style="font-size: 0.75rem; color: var(--text-muted);">(<?= $s['pro_pct'] ?>% favorable)</span>
              </td>
              <td>
                <?= $reported ? '<span class="badge badge-rejected">⚠️ Signalée</span>' : status_badge($r) ?>
              </td>
              <td>
                <div class="action-buttons">
                  <?php if ($reported): ?>
                    <a href="#modal-reject-<?= $rid ?>" class="btn btn-danger btn-sm">Rejeter & Archiver</a>
                    <form method="post" class="inline-form">
                      <?= csrf_field() ?>
                      <input type="hidden" name="request_id" value="<?= $rid ?>">
                      <input type="hidden" name="redirect" value="<?= e($selfUrl) ?>">
                      <button type="submit" name="action" value="dismiss_report" class="btn btn-secondary btn-sm">Lever le signalement</button>
                    </form>
                  <?php elseif ($r['status'] === 'pending'): ?>
                    <form method="post" class="inline-form">
                      <?= csrf_field() ?>
                      <input type="hidden" name="request_id" value="<?= $rid ?>">
                      <input type="hidden" name="redirect" value="<?= e($selfUrl) ?>">
                      <button type="submit" name="action" value="approve" class="btn btn-primary btn-sm" title="Approuver pour mise au vote public">Approuver</button>
                    </form>
                    <a href="#modal-reject-<?= $rid ?>" class="btn btn-danger btn-sm" title="Rejeter le contenu">Rejeter</a>
                  <?php elseif ($r['status'] === 'threshold'): ?>
                    <a href="#modal-validate-<?= $rid ?>" class="btn btn-success btn-sm" title="Valider officiellement et notifier l'auteur (Story 12)">Valider (Campus)</a>
                    <a href="#modal-precision-<?= $rid ?>" class="btn btn-secondary btn-sm" title="Demander des précisions à l'auteur">Précisions</a>
                  <?php elseif ($r['status'] === 'voting'): ?>
                    <a href="#modal-precision-<?= $rid ?>" class="btn btn-secondary btn-sm">Précisions</a>
                    <a href="#modal-reject-<?= $rid ?>" class="btn btn-danger btn-sm">Rejeter</a>
                  <?php else: ?>
                    <a href="detail.php?id=<?= $rid ?>" class="btn btn-secondary btn-sm">Consulter</a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Modales de décision via :target -->
    <?php foreach ($requests as $r): $rid = (int) $r['id']; $s = vote_stats($r); ?>
      <?php if ($r['status'] === 'threshold'): ?>
      <div id="modal-validate-<?= $rid ?>" class="modal-target">
        <form method="post" class="modal-box">
          <?= csrf_field() ?>
          <input type="hidden" name="request_id" value="<?= $rid ?>">
          <input type="hidden" name="action" value="validate">
          <input type="hidden" name="redirect" value="<?= e($selfUrl) ?>">
          <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
            <span style="font-size: 1.75rem;">🎉</span>
            <h3 style="font-size: 1.35rem; font-weight: 800;">Valider officiellement la requête</h3>
          </div>
          <p style="font-size: 0.9rem; color: var(--text-muted); line-height: 1.5; margin-bottom: 1.25rem;">
            <strong><?= request_ref($rid) ?> — <?= e($r['title']) ?></strong> (<?= $s['pro'] ?> votes Pour).<br>
            En validant cette requête, vous confirmez l'accord de la commission. L'auteur et tous les participants recevront une <strong>notification automatique</strong> (Story 12) et le statut passera en <strong>Validée par le Campus</strong>.
          </p>
          <div class="form-group">
            <label class="form-label" for="decision-comment-<?= $rid ?>">Commentaire officiel de la direction (public)</label>
            <textarea id="decision-comment-<?= $rid ?>" name="decision_comment" class="form-control" rows="3" placeholder="Ex: La commission a validé le budget de fonctionnement..."></textarea>
          </div>
          <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem;">
            <a href="#" class="btn btn-secondary">Annuler</a>
            <button type="submit" class="btn btn-success">Confirmer & Notifier l'auteur</button>
          </div>
        </form>
      </div>
      <?php endif; ?>

      <?php if (in_array($r['status'], ['threshold', 'voting'], true)): ?>
      <div id="modal-precision-<?= $rid ?>" class="modal-target">
        <form method="post" class="modal-box">
          <?= csrf_field() ?>
          <input type="hidden" name="request_id" value="<?= $rid ?>">
          <input type="hidden" name="action" value="precision">
          <input type="hidden" name="redirect" value="<?= e($selfUrl) ?>">
          <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 1rem;">Demander des précisions</h3>
          <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1rem;">L'auteur de <strong><?= request_ref($rid) ?></strong> recevra une notification (son anonymat est préservé).</p>
          <div class="form-group">
            <textarea name="message" class="form-control" rows="3" required maxlength="200" placeholder="Ex: Pouvez-vous préciser le budget estimé ?"></textarea>
          </div>
          <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
            <a href="#" class="btn btn-secondary">Annuler</a>
            <button type="submit" class="btn btn-primary">Envoyer</button>
          </div>
        </form>
      </div>
      <?php endif; ?>

      <?php if (!in_array($r['status'], ['validated', 'rejected'], true)): ?>
      <div id="modal-reject-<?= $rid ?>" class="modal-target">
        <form method="post" class="modal-box">
          <?= csrf_field() ?>
          <input type="hidden" name="request_id" value="<?= $rid ?>">
          <input type="hidden" name="action" value="reject">
          <input type="hidden" name="redirect" value="<?= e($selfUrl) ?>">
          <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 1rem;">Rejeter la requête <?= request_ref($rid) ?></h3>
          <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1rem;"><?= e($r['title']) ?></p>
          <div class="form-group">
            <label class="form-label" for="reject-reason-<?= $rid ?>">Motif du rejet (visible par l'auteur)</label>
            <textarea id="reject-reason-<?= $rid ?>" name="reason" class="form-control" rows="3" placeholder="Ex: Non-conforme au règlement intérieur du campus"><?= e($r['report_reason']) ?></textarea>
          </div>
          <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
            <a href="#" class="btn btn-secondary">Annuler</a>
            <button type="submit" class="btn btn-danger">Confirmer le rejet</button>
          </div>
        </form>
      </div>
      <?php endif; ?>
    <?php endforeach; ?>

    <!-- Journal d'audit -->
    <section class="form-card" id="audit-log" style="padding: 1.5rem;">
      <h2 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 1rem;">Journal des dernières décisions d'administration</h2>
      <div style="display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.85rem;">
        <?php if (!$audit): ?>
          <p style="color: var(--text-muted);">Aucune décision enregistrée.</p>
        <?php endif; ?>
        <?php foreach ($audit as $a): [$badgeClass, $badgeLabel] = $auditBadges[$a['action']]; ?>
          <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 0.75rem; border-radius: var(--radius-md); background-color: var(--bg-muted);">
            <span><strong><?= e(format_date_fr($a['created_at'], true)) ?> :</strong> <?= e($a['message']) ?></span>
            <span class="badge <?= $badgeClass ?>"><?= e($badgeLabel) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  </main>

<?php require __DIR__ . '/includes/footer.php'; ?>
