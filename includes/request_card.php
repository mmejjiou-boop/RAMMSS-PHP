<?php
/**
 * Carte d'une requête dans le fil (Stories 2, 8, 10).
 * Variables attendues : $r (ligne de requête), $user (utilisateur courant ou null), $myVotes (request_id => valeur)
 */
$s = vote_stats($r);
$author = author_display($r);
$myVote = $myVotes[$r['id']] ?? null;
$canVote = in_array($r['status'], ['voting', 'threshold'], true);

if ($r['status'] === 'validated') {
    $summary = '<strong style="color: var(--success);">' . $s['pro_pct'] . '% Favorable &bull; Seuil largement atteint</strong>';
} elseif ($r['status'] === 'threshold') {
    $summary = '<strong style="color: var(--primary);">' . $s['pro_pct'] . '% Favorable &bull; En examen commission</strong>';
} elseif ($s['missing'] > 0 && $s['missing'] <= 10) {
    $summary = '<strong style="color: var(--primary);">' . $s['pro_pct'] . '% Favorable &bull; Plus que ' . $s['missing'] . ' vote' . ($s['missing'] > 1 ? 's' : '') . ' pour le seuil !</strong>';
} else {
    $summary = '<strong>' . $s['pro_pct'] . '% Favorable (' . $s['pro'] . '/' . VOTE_THRESHOLD . ' requis)</strong>';
}
?>
<article class="request-card">
  <div class="card-top">
    <div class="card-author-meta">
      <div class="avatar<?= $author['anon'] ? ' avatar-anon' : '' ?>"><?= e($author['initials']) ?></div>
      <div class="author-info">
        <span class="author-name"><?= e($author['name']) ?></span>
        <span class="post-date">Publié <?= e(time_ago($r['created_at'])) ?> &bull; <?= e($author['promo']) ?></span>
      </div>
    </div>
    <div class="card-badges">
      <span class="badge badge-category"><?= e($r['category_name']) ?></span>
      <?php if ($author['anon']): ?><span class="badge badge-anon">🎭 Requête Anonyme</span><?php endif; ?>
      <?= status_badge($r) ?>
    </div>
  </div>

  <h2 class="card-title">
    <a href="detail.php?id=<?= (int) $r['id'] ?>"><?= e($r['title']) ?></a>
  </h2>
  <p class="card-desc"><?= e(mb_strimwidth($r['description'], 0, 300, '…')) ?></p>

  <!-- Jauge de Vote Tripartite (Story 10) -->
  <div class="vote-gauge-container">
    <div class="vote-gauge-header">
      <span>Résultats du sondage (<?= $s['total'] ?> vote<?= $s['total'] > 1 ? 's' : '' ?>)</span>
      <?= $summary ?>
    </div>
    <div class="vote-ratio-bar" role="progressbar" aria-valuenow="<?= $s['pro_pct'] ?>" aria-valuemin="0" aria-valuemax="100">
      <div class="bar-pro" style="width: <?= $s['pro_pct'] ?>%;" title="<?= $s['pro'] ?> Pour"></div>
      <div class="bar-neutral" style="width: <?= $s['neutral_pct'] ?>%;" title="<?= $s['neutral'] ?> Neutre"></div>
      <div class="bar-con" style="width: <?= $s['con_pct'] ?>%;" title="<?= $s['con'] ?> Contre"></div>
    </div>
    <div class="vote-stats-text">
      <span class="vote-stat-item pro">👍 <?= $s['pro'] ?> Pour (<?= $s['pro_pct'] ?>%)</span>
      <span class="vote-stat-item neutral">⚖️ <?= $s['neutral'] ?> Neutre (<?= $s['neutral_pct'] ?>%)</span>
      <span class="vote-stat-item con">👎 <?= $s['con'] ?> Contre (<?= $s['con_pct'] ?>%)</span>
    </div>
  </div>

  <div class="vote-actions-row">
    <form method="post" action="vote.php" class="vote-buttons-group">
      <?= csrf_field() ?>
      <input type="hidden" name="request_id" value="<?= (int) $r['id'] ?>">
      <input type="hidden" name="redirect" value="index.php<?= !empty($_SERVER['QUERY_STRING']) ? '?' . e($_SERVER['QUERY_STRING']) : '' ?>">
      <?php foreach (['pro' => '👍 Pour', 'neutral' => '⚖️ Neutre', 'con' => '👎 Contre'] as $value => $label): ?>
        <button type="submit" name="value" value="<?= $value ?>"
                class="btn-vote btn-vote-<?= $value ?><?= $myVote === $value ? ' is-selected' : '' ?>"
                aria-label="Voter <?= e(strip_tags($label)) ?>"
                <?= $canVote ? '' : 'disabled title="Le vote est clôturé pour cette requête"' ?>><?= $label ?></button>
      <?php endforeach; ?>
    </form>
    <div class="card-footer-links">
      <span class="card-meta-link">💬 <?= (int) $r['comment_count'] ?> avis</span>
      <a href="detail.php?id=<?= (int) $r['id'] ?>" class="btn btn-secondary btn-sm">Détails & Avis &rarr;</a>
    </div>
  </div>
</article>
