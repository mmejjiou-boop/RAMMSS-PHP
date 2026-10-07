<?php
require_once __DIR__ . '/includes/functions.php';

$user = current_user();
$pdo = db();

// --- Filtres, recherche et tri (Story 11) ---
$filters = [
    'all'       => 'Toutes les requêtes',
    'popular'   => '🔥 Plus populaires',
    'voting'    => '⏳ Vote en cours',
    'threshold' => '🎯 Seuil atteint',
    'validated' => '✅ Validées',
];
$sorts = [
    'votes'   => 'Popularité (Votes)',
    'recent'  => 'Plus récentes',
    'urgency' => 'Urgence validation',
    'pct'     => 'Pourcentage Pour',
];
$filter = array_key_exists($_GET['filter'] ?? '', $filters) ? $_GET['filter'] : 'all';
$sort = array_key_exists($_GET['sort'] ?? '', $sorts) ? $_GET['sort'] : 'votes';
$q = trim($_GET['q'] ?? '');

// Seules les requêtes approuvées par la modération et non signalées sont publiques
$where = ["r.status IN ('voting', 'threshold', 'validated')", 'r.is_reported = 0'];
$params = [];
if (in_array($filter, ['voting', 'threshold', 'validated'], true)) {
    $where[] = 'r.status = :status';
    $params['status'] = $filter;
}
if ($q !== '') {
    $where[] = '(r.title LIKE :q1 OR r.description LIKE :q2)';
    $params['q1'] = $params['q2'] = '%' . $q . '%';
}

$orderBy = match ($filter === 'popular' ? 'votes' : $sort) {
    'recent'  => 'r.created_at DESC',
    'urgency' => "FIELD(r.status, 'threshold', 'voting', 'validated'), pro DESC",
    'pct'     => '(COALESCE(v.pro, 0) / GREATEST(COALESCE(v.total, 0), 1)) DESC, pro DESC',
    default   => 'pro DESC, r.created_at DESC',
};

$stmt = $pdo->prepare(REQUEST_SELECT . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $orderBy);
$stmt->execute($params);
$requests = $stmt->fetchAll();

// Votes de l'utilisateur courant
$myVotes = [];
if ($user) {
    $stmt = $pdo->prepare('SELECT request_id, value FROM votes WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    $myVotes = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

// --- Statistiques du campus ---
$stats = $pdo->query("
    SELECT
        (SELECT COUNT(*) FROM requests) AS total_requests,
        (SELECT COUNT(*) FROM requests WHERE status IN ('voting','threshold','validated') AND is_reported = 0) AS public_requests,
        (SELECT COUNT(*) FROM requests WHERE status = 'validated') AS validated,
        (SELECT COUNT(*) FROM requests WHERE status = 'threshold') AS threshold,
        (SELECT COUNT(*) FROM votes) AS votes
")->fetch();

// --- Bandeau de notification de validation (Story 12) ---
$toast = null;
if ($user) {
    $stmt = $pdo->prepare("
        SELECT n.id, r.title, r.user_id FROM notifications n JOIN requests r ON r.id = n.request_id
        WHERE n.user_id = ? AND n.type = 'validated' AND n.is_read = 0
        ORDER BY n.created_at DESC LIMIT 1
    ");
    $stmt->execute([$user['id']]);
    if ($row = $stmt->fetch()) {
        $prefix = (int) $row['user_id'] === (int) $user['id'] ? 'Votre requête' : 'La requête que vous avez soutenue';
        $toast = ['id' => $row['id'], 'message' => $prefix . ' « ' . $row['title'] . ' » a atteint le seuil requis et a été'];
    }
}

$filterUrl = fn(string $f) => 'index.php?' . http_build_query(array_filter(['filter' => $f, 'sort' => $sort, 'q' => $q]));

$pageTitle = "Fil d'actualité des requêtes";
$activeNav = 'index';
require __DIR__ . '/includes/header.php';
?>

  <!-- Bannière Hero & Présentation Product Goal -->
  <section class="hero-section">
    <div class="container hero-grid">
      <div class="hero-content">
        <span class="badge badge-voting" style="margin-bottom: 0.75rem;">Plateforme Démocratique du Campus</span>
        <h1 class="hero-title">Votre voix façonne la vie étudiante sur le <span class="hero-highlight">Campus RAMMSS</span>.</h1>
        <p class="hero-desc">
          Offrir à la communauté une plateforme collaborative permettant de <strong>soumettre</strong>, de <strong>consulter</strong> et de <strong>valider des requêtes</strong> sous forme de sondage démocratique afin d'améliorer concrètement notre quotidien.
        </p>
        <div class="hero-actions">
          <a href="create.php" class="btn btn-primary btn-lg">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Proposer une idée
          </a>
          <a href="#feed" class="btn btn-secondary btn-lg">Explorer les sondages en cours</a>
        </div>
      </div>

      <!-- Statistiques en direct du Campus -->
      <div class="hero-stats-card">
        <div class="stat-box">
          <div class="stat-value"><?= (int) $stats['total_requests'] ?></div>
          <div class="stat-label">Requêtes déposées</div>
        </div>
        <div class="stat-box">
          <div class="stat-value" style="color: var(--success);"><?= (int) $stats['validated'] ?></div>
          <div class="stat-label">Validées par le Campus</div>
        </div>
        <div class="stat-box">
          <div class="stat-value" style="color: var(--primary);"><?= number_format((int) $stats['votes'], 0, ',', ' ') ?></div>
          <div class="stat-label">Votes étudiants exprimés</div>
        </div>
        <div class="stat-box">
          <div class="stat-value" style="color: var(--warning);"><?= VOTE_THRESHOLD ?></div>
          <div class="stat-label">Seuil de votes requis</div>
        </div>
      </div>
    </div>
  </section>

  <main class="container" id="feed">
    <!-- Barre de Contrôles, Filtres et Tri (Story 11) -->
    <div class="feed-controls">
      <div class="controls-card">
        <nav class="filter-pills" aria-label="Filtres des requêtes">
          <?php foreach ($filters as $key => $label): ?>
            <?php
              $count = match ($key) {
                  'all' => ' (' . $stats['public_requests'] . ')',
                  'threshold' => ' (' . $stats['threshold'] . ')',
                  'validated' => ' (' . $stats['validated'] . ')',
                  default => '',
              };
            ?>
            <a href="<?= e($filterUrl($key)) ?>#feed" class="filter-pill<?= $filter === $key ? ' active' : '' ?>"<?= $filter === $key ? ' aria-current="true"' : '' ?>><?= e($label . $count) ?></a>
          <?php endforeach; ?>
        </nav>

        <form method="get" action="index.php#feed" class="sort-and-search">
          <input type="hidden" name="filter" value="<?= e($filter) ?>">
          <div class="search-input-wrapper">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            <input type="search" name="q" class="search-input" placeholder="Rechercher une requête..." aria-label="Rechercher une requête" value="<?= e($q) ?>">
          </div>
          <select name="sort" class="select-sort" aria-label="Trier les résultats" onchange="this.form.submit()">
            <?php foreach ($sorts as $key => $label): ?>
              <option value="<?= $key ?>"<?= $sort === $key ? ' selected' : '' ?>>Trier par : <?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <noscript><button type="submit" class="btn btn-secondary btn-sm">OK</button></noscript>
        </form>
      </div>
    </div>

    <div class="feed-layout">
      <!-- Flux de requêtes (Story 2) -->
      <section class="feed-stream" aria-label="Liste des requêtes du campus">
        <?php if (!$requests): ?>
          <div class="request-card empty-state">
            <p>Aucune requête ne correspond à votre recherche.</p>
            <a href="create.php" class="btn btn-primary" style="margin-top: 1rem;">+ Proposer une requête</a>
          </div>
        <?php endif; ?>
        <?php foreach ($requests as $r): ?>
          <?php require __DIR__ . '/includes/request_card.php'; ?>
        <?php endforeach; ?>
      </section>

      <!-- Sidebar : Processus Démocratique & Raccourcis (Story 9) -->
      <aside class="feed-sidebar">
        <div class="sidebar-card">
          <h3 class="sidebar-title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
            Cycle de validation démocratique
          </h3>
          <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.25rem;">
            Chaque proposition suit une charte transparente pour garantir la faisabilité et le respect des règles du campus :
          </p>
          <div class="process-steps">
            <div class="process-step">
              <span class="step-num">1</span>
              <div class="step-content">
                <strong>Soumission de l'idée</strong>
                <p>Création avec titre, description et choix d'anonymat optionnel.</p>
              </div>
            </div>
            <div class="process-step">
              <span class="step-num">2</span>
              <div class="step-content">
                <strong>Vote communautaire</strong>
                <p>Ouvert à tous les étudiants sous forme de sondage Pour / Neutre / Contre.</p>
              </div>
            </div>
            <div class="process-step">
              <span class="step-num">3</span>
              <div class="step-content">
                <strong>Palier des <?= VOTE_THRESHOLD ?> votes</strong>
                <p>Dès que le seuil de <?= VOTE_THRESHOLD ?> votes et <?= THRESHOLD_MIN_PCT ?>% d'avis favorables est atteint, passage en commission.</p>
              </div>
            </div>
            <div class="process-step">
              <span class="step-num">4</span>
              <div class="step-content">
                <strong>Validation Campus</strong>
                <p>L'administration et le BDE valident le budget et la mise en œuvre.</p>
              </div>
            </div>
          </div>
        </div>

        <div class="sidebar-card">
          <h3 class="sidebar-title">Espaces et Accès</h3>
          <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <a href="create.php" class="btn btn-primary" style="width: 100%;">+ Proposer une requête</a>
            <?php if (is_admin()): ?>
              <a href="admin.php" class="btn btn-secondary" style="width: 100%;">Accéder à l'Espace Admin</a>
            <?php endif; ?>
            <a href="settings.php" class="btn btn-secondary" style="width: 100%;">Mes Paramètres</a>
            <?php if (!$user): ?>
              <a href="login.php" class="btn btn-secondary" style="width: 100%;">Page de Connexion</a>
              <a href="register.php" class="btn btn-secondary" style="width: 100%;">Créer un compte étudiant</a>
            <?php endif; ?>
            <a href="admin-register.php" class="btn btn-secondary" style="width: 100%;">Inscription Staff / Admin</a>
          </div>
        </div>
      </aside>
    </div>
  </main>

<?php require __DIR__ . '/includes/footer.php'; ?>
