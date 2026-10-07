<?php
// Consultation détaillée d'une requête (Stories 5, 8, 9, 10, 12)
require_once __DIR__ . '/includes/functions.php';

$user = current_user();
$id = (int) ($_GET['id'] ?? 0);
$r = find_request($id);

$isAuthor = $r && $user && (int) $r['user_id'] === (int) $user['id'];
$isPublic = $r && in_array($r['status'], ['voting', 'threshold', 'validated'], true) && !$r['is_reported'];
if (!$r || (!$isPublic && !$isAuthor && !is_admin())) {
    flash('danger', "Cette requête n'existe pas ou n'est pas encore publiée.");
    redirect('index.php');
}

// --- Actions POST : commentaire ou signalement ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$user) {
        flash('info', 'Connectez-vous pour participer au débat.');
        redirect('login.php?redirect=' . urlencode('detail.php?id=' . $id));
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'comment') {
        $content = trim($_POST['content'] ?? '');
        if ($content === '' || mb_strlen($content) > 2000) {
            flash('danger', 'Le commentaire doit contenir entre 1 et 2000 caractères.');
        } else {
            $anon = empty($_POST['is_anonymous']) ? 0 : 1;
            db()->prepare('INSERT INTO comments (request_id, user_id, content, is_anonymous) VALUES (?, ?, ?, ?)')
                ->execute([$id, $user['id'], $content, $anon]);

            // Notification à l'auteur si la préférence est active
            if (!$isAuthor) {
                $stmt = db()->prepare('SELECT notif_comments FROM users WHERE id = ?');
                $stmt->execute([$r['user_id']]);
                if ((int) $stmt->fetchColumn() === 1) {
                    $who = $anon ? 'Un étudiant anonyme' : short_name($user);
                    notify((int) $r['user_id'], $id, 'comment', 'Nouveau débat', "$who a commenté « " . mb_strimwidth($r['title'], 0, 45, '…') . ' ».');
                }
            }
            flash('success', 'Votre avis a été publié. Merci pour votre contribution !');
        }
        redirect('detail.php?id=' . $id . '#comments');
    }

    if ($action === 'report' && !$r['is_reported']) {
        $reason = trim($_POST['reason'] ?? '') ?: 'Contenu signalé par un étudiant';
        db()->prepare('UPDATE requests SET is_reported = 1, report_reason = ? WHERE id = ?')
            ->execute([mb_substr($reason, 0, 255), $id]);
        audit(null, $id, 'reported', 'Requête ' . request_ref($id) . ' signalée par un étudiant : ' . mb_substr($reason, 0, 150));
        flash('info', 'Merci, la requête a été signalée à l\'équipe de modération.');
        redirect($isAuthor || is_admin() ? 'detail.php?id=' . $id : 'index.php');
    }
    redirect('detail.php?id=' . $id);
}

$s = vote_stats($r);
$author = author_display($r);
$myVote = $user ? user_vote($id, (int) $user['id']) : null;
$canVote = in_array($r['status'], ['voting', 'threshold'], true) && !$r['is_reported'];

$stmt = db()->prepare('
    SELECT cm.*, u.first_name, u.last_name, u.promo, u.role
    FROM comments cm JOIN users u ON u.id = cm.user_id
    WHERE cm.request_id = ? ORDER BY (u.role = \'admin\') DESC, cm.created_at DESC
');
$stmt->execute([$id]);
$comments = $stmt->fetchAll();

// --- Stepper du cycle de validation (Story 9) ---
$st = $r['status'];
$order = ['pending' => 1, 'voting' => 2, 'threshold' => 3, 'validated' => 5, 'rejected' => 1];
$reached = $order[$st];
$steps = [
    ['label' => '1. Soumission', 'sub' => format_date_fr($r['created_at'])],
    ['label' => '2. Modération', 'sub' => $st === 'pending' ? 'En cours d\'examen' : ($st === 'rejected' ? 'Rejetée' : 'Conforme à la charte')],
    ['label' => '3. Seuil ' . VOTE_THRESHOLD . ' votes', 'sub' => $r['threshold_at'] ? 'Atteint le ' . format_date_fr($r['threshold_at']) : $s['pro'] . ' / ' . VOTE_THRESHOLD . ' votes Pour'],
    ['label' => '4. Commission', 'sub' => $st === 'validated' ? 'Avis favorable' : ($st === 'threshold' ? 'En examen' : 'En attente')],
    ['label' => '5. Validée Campus', 'sub' => $r['validated_at'] ? format_date_fr($r['validated_at']) : 'En attente'],
];
$stepClass = function (int $i) use ($reached, $st): string {
    if ($st === 'rejected') {
        return $i === 0 ? 'completed' : ($i === 1 ? 'rejected' : '');
    }
    if ($i < $reached) {
        return 'completed';
    }
    return $i === $reached ? 'current' : '';
};

$pageTitle = 'Détail de la Requête : ' . $r['title'];
$pageDescription = "Campus Life - Consultation détaillée d'une requête citoyenne du campus.";
require __DIR__ . '/includes/header.php';
?>

  <div class="container detail-header">
    <nav class="breadcrumb" aria-label="Fil d'Ariane">
      <a href="index.php">&larr; Retour au fil</a>
      <span>/</span>
      <a href="index.php">Requêtes</a>
      <span>/</span>
      <span><?= e($r['category_name']) ?></span>
      <span>/</span>
      <span>Détail n° <?= request_ref($id) ?></span>
    </nav>
  </div>

  <main class="container">
    <div class="detail-layout">
      <section class="detail-main-content">
        <div class="form-card" style="margin-bottom: 2rem;">
          <div class="card-top" style="margin-bottom: 1rem;">
            <div class="card-author-meta">
              <div class="avatar<?= $author['anon'] ? ' avatar-anon' : '' ?>" style="width: 2.75rem; height: 2.75rem; font-size: 1rem;"><?= e($author['initials']) ?></div>
              <div class="author-info">
                <span class="author-name" style="font-size: 1rem;"><?= e($author['name']) ?><?= $author['anon'] && $isAuthor ? ' (vous)' : '' ?></span>
                <span class="post-date">Déposée le <?= e(format_date_fr($r['created_at'])) ?> &bull; <?= e($author['promo']) ?></span>
              </div>
            </div>
            <div class="card-badges">
              <span class="badge badge-category"><?= e($r['category_name']) ?></span>
              <?php if ($author['anon']): ?><span class="badge badge-anon">🎭 Requête Anonyme</span><?php endif; ?>
              <?= status_badge($r) ?>
              <?php if ($r['is_reported']): ?><span class="badge badge-rejected">⚠️ Signalée</span><?php endif; ?>
            </div>
          </div>

          <h1 style="font-size: 1.85rem; font-weight: 800; line-height: 1.3; margin-bottom: 1.25rem;"><?= e($r['title']) ?></h1>

          <?php if ($st === 'validated'): ?>
          <!-- Alerte Officielle de Validation (Story 12) -->
          <div class="alert alert-success">
            <span style="font-size: 1.5rem;">🎉</span>
            <div>
              <strong>Décision Officielle de l'Administration du Campus (<?= e(format_date_fr($r['validated_at'])) ?>) :</strong>
              <p>Cette requête a recueilli <?= $s['pro'] ?> votes favorables (seuil fixé à <?= VOTE_THRESHOLD ?>). <?= e($r['decision_comment']) ?></p>
            </div>
          </div>
          <?php elseif ($st === 'pending'): ?>
          <div class="alert alert-warning">
            <span style="font-size: 1.5rem;">⏳</span>
            <div><strong>En attente de modération :</strong><p>Cette requête n'est pas encore visible publiquement. Elle sera ouverte au vote dès son approbation.</p></div>
          </div>
          <?php elseif ($st === 'rejected'): ?>
          <div class="alert alert-danger">
            <span style="font-size: 1.5rem;">❌</span>
            <div><strong>Requête rejetée par la modération.</strong><?php if ($r['decision_comment']): ?><p><?= e($r['decision_comment']) ?></p><?php endif; ?></div>
          </div>
          <?php elseif ($st === 'threshold'): ?>
          <div class="alert alert-info">
            <span style="font-size: 1.5rem;">🎯</span>
            <div><strong>Seuil atteint !</strong><p>Cette requête a franchi le palier des <?= VOTE_THRESHOLD ?> votes et est transmise à la commission Direction / BDE.</p></div>
          </div>
          <?php endif; ?>

          <!-- Stepper Horizontal (Story 9) -->
          <div style="margin-top: 2rem; margin-bottom: 2rem;">
            <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.5rem;">Cycle de validation de la requête</h3>
            <div class="stepper-horizontal">
              <?php foreach ($steps as $i => $step): $cls = $stepClass($i); ?>
                <div class="step-item <?= $cls ?>">
                  <div class="step-circle"><?= $cls === 'completed' ? '✓' : ($cls === 'rejected' ? '✕' : $i + 1) ?></div>
                  <div class="step-label"><?= e($step['label']) ?></div>
                  <div class="step-subtext"><?= e($step['sub']) ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <article style="display: flex; flex-direction: column; gap: 1.25rem; font-size: 1rem; line-height: 1.7; color: var(--text-main);">
            <h3 style="font-size: 1.15rem; font-weight: 700; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
              Description et justification du besoin
            </h3>
            <p class="request-description"><?= e($r['description']) ?></p>
            <?php if ($r['impact']): ?>
              <h3 style="font-size: 1.15rem; font-weight: 700; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
                Impact attendu sur la vie du campus
              </h3>
              <p><?= e($r['impact']) ?></p>
            <?php endif; ?>
          </article>
        </div>

        <!-- Commentaires et débats -->
        <section class="form-card" id="comments" aria-label="Commentaires et avis des étudiants">
          <h2 style="font-size: 1.35rem; font-weight: 700; margin-bottom: 1rem;">
            Avis et Débats de la communauté (<?= count($comments) ?> contribution<?= count($comments) > 1 ? 's' : '' ?>)
          </h2>

          <?php if ($user): ?>
          <form action="detail.php?id=<?= $id ?>" method="post" style="margin-bottom: 2rem;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="comment">
            <div class="form-group">
              <label for="comment-text" class="form-label">
                Ajouter un commentaire constructif
                <span class="form-hint">Respectez la charte de bienveillance</span>
              </label>
              <textarea id="comment-text" name="content" class="form-control" required maxlength="2000" placeholder="Donnez votre avis, une précision ou une suggestion pour enrichir cette requête..."></textarea>
            </div>
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
              <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: var(--text-muted); cursor: pointer;">
                <input type="checkbox" name="is_anonymous" value="1"<?= $user['default_anon'] ? ' checked' : '' ?>>
                Publier cet avis de façon anonyme 🎭
              </label>
              <button type="submit" class="btn btn-primary">Publier mon avis</button>
            </div>
          </form>
          <?php else: ?>
            <div class="alert alert-info" style="margin-bottom: 2rem;">
              <div><a href="login.php?redirect=<?= e(urlencode('detail.php?id=' . $id)) ?>" style="font-weight: 600;">Connectez-vous</a> pour donner votre avis.</div>
            </div>
          <?php endif; ?>

          <div class="comment-list">
            <?php if (!$comments): ?>
              <p style="color: var(--text-muted); font-size: 0.9rem;">Aucun avis pour le moment. Soyez le premier à réagir !</p>
            <?php endif; ?>
            <?php foreach ($comments as $c): ?>
              <?php $isStaff = $c['role'] === 'admin' && !$c['is_anonymous']; ?>
              <div class="comment-item"<?= $isStaff ? ' style="border-left: 4px solid var(--primary);"' : '' ?>>
                <div class="comment-header">
                  <div class="card-author-meta">
                    <?php if ($c['is_anonymous']): ?>
                      <div class="avatar avatar-anon">🎭</div>
                      <div>
                        <strong>Étudiant Anonyme</strong>
                        <span class="badge badge-anon" style="margin-left: 0.4rem;">Anonymat préservé</span>
                      </div>
                    <?php else: ?>
                      <div class="avatar"<?= $isStaff ? ' style="background-color: var(--primary); color: #fff;"' : '' ?>><?= e(initials($c['first_name'], $c['last_name'])) ?></div>
                      <div>
                        <strong><?= e($isStaff ? $c['first_name'] . ' ' . $c['last_name'] : short_name($c)) ?></strong>
                        <?php if ($isStaff): ?>
                          <span class="badge badge-category" style="margin-left: 0.4rem;">Staff Officiel</span>
                        <?php else: ?>
                          <span class="post-date">&bull; <?= e($c['promo']) ?></span>
                        <?php endif; ?>
                      </div>
                    <?php endif; ?>
                  </div>
                  <span class="post-date"><?= e(format_date_fr($c['created_at'])) ?></span>
                </div>
                <p style="font-size: 0.9rem; line-height: 1.5; white-space: pre-line;"><?= e($c['content']) ?></p>
              </div>
            <?php endforeach; ?>
          </div>
        </section>
      </section>

      <!-- Colonne latérale : vote (Story 10) -->
      <aside class="detail-sidebar">
        <div class="sidebar-card" style="position: sticky; top: 5.5rem;">
          <h2 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 1rem;">Participer au vote</h2>
          <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.25rem;">
            <?php if (!$canVote): ?>
              Le vote est clôturé ou pas encore ouvert pour cette requête.
            <?php elseif ($myVote): ?>
              Vous avez déjà voté. Vous pouvez modifier votre choix à tout moment.
            <?php else: ?>
              Votre vote est unique, vérifié par votre compte étudiant et comptabilisé immédiatement.
            <?php endif; ?>
          </p>

          <form method="post" action="vote.php" style="display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1.5rem;">
            <?= csrf_field() ?>
            <input type="hidden" name="request_id" value="<?= $id ?>">
            <input type="hidden" name="redirect" value="detail.php?id=<?= $id ?>">
            <?php
              $choices = [
                  'pro' => ['👍', 'Je vote POUR', $s['pro'], 'btn btn-success', '', ''],
                  'neutral' => ['⚖️', 'Je vote NEUTRE', $s['neutral'], 'btn btn-secondary', 'border-color: var(--warning-border);', 'color: #d97706;'],
                  'con' => ['👎', 'Je vote CONTRE', $s['con'], 'btn btn-secondary', 'border-color: var(--danger-border);', 'color: var(--danger);'],
              ];
            ?>
            <?php foreach ($choices as $value => [$icon, $label, $count, $cls, $style, $labelStyle]): ?>
              <button type="submit" name="value" value="<?= $value ?>" class="<?= $cls ?> vote-choice<?= $myVote === $value ? ' is-selected' : '' ?>"
                      style="width: 100%; justify-content: flex-start; padding: 0.75rem 1rem; <?= $style ?>"<?= $canVote ? '' : ' disabled' ?>>
                <span style="font-size: 1.2rem;"><?= $icon ?></span>
                <div style="text-align: left;">
                  <div style="font-weight: 700; <?= $labelStyle ?>"><?= $label ?><?= $myVote === $value ? ' ✓' : '' ?></div>
                  <div style="font-size: 0.75rem; opacity: 0.9;"><?= $count ?> vote<?= $count > 1 ? 's' : '' ?> enregistré<?= $count > 1 ? 's' : '' ?></div>
                </div>
              </button>
            <?php endforeach; ?>
          </form>

          <div class="vote-gauge-container">
            <div class="vote-gauge-header">
              <span>Répartition (<?= $s['total'] ?> votes)</span>
              <strong style="color: var(--success);"><?= $s['pro_pct'] ?>% Pour</strong>
            </div>
            <div class="vote-ratio-bar">
              <div class="bar-pro" style="width: <?= $s['pro_pct'] ?>%;"></div>
              <div class="bar-neutral" style="width: <?= $s['neutral_pct'] ?>%;"></div>
              <div class="bar-con" style="width: <?= $s['con_pct'] ?>%;"></div>
            </div>
          </div>

          <hr style="border: none; border-top: 1px solid var(--border-color); margin-block: 1.25rem;">

          <div style="display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.85rem;">
            <div style="display: flex; justify-content: space-between;">
              <span style="color: var(--text-muted);">Identifiant :</span>
              <strong><?= request_ref($id) ?></strong>
            </div>
            <?php if ($r['location']): ?>
            <div style="display: flex; justify-content: space-between; gap: 1rem;">
              <span style="color: var(--text-muted);">Localisation :</span>
              <strong style="text-align: right;"><?= e($r['location']) ?></strong>
            </div>
            <?php endif; ?>
            <div style="display: flex; justify-content: space-between;">
              <span style="color: var(--text-muted);">Seuil de passage :</span>
              <strong><?= VOTE_THRESHOLD ?> votes <?= $s['pro'] >= VOTE_THRESHOLD ? '(Atteint ✅)' : '(' . $s['missing'] . ' restants)' ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between;">
              <span style="color: var(--text-muted);">Statut officiel :</span>
              <?= status_badge($r) ?>
            </div>
          </div>

          <?php if ($user && !$r['is_reported'] && $isPublic && !$isAuthor): ?>
            <hr style="border: none; border-top: 1px solid var(--border-color); margin-block: 1.25rem;">
            <details>
              <summary style="cursor: pointer; font-size: 0.8rem; color: var(--text-muted);">⚠️ Signaler cette requête</summary>
              <form method="post" action="detail.php?id=<?= $id ?>" style="margin-top: 0.75rem;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="report">
                <input type="text" name="reason" class="form-control" placeholder="Motif du signalement" maxlength="255" style="font-size: 0.85rem; margin-bottom: 0.5rem;">
                <button type="submit" class="btn btn-danger btn-sm" style="width: 100%;">Envoyer le signalement</button>
              </form>
            </details>
          <?php endif; ?>
        </div>
      </aside>
    </div>
  </main>

<?php require __DIR__ . '/includes/footer.php'; ?>
