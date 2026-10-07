<?php
// Créer une nouvelle requête (Stories 3 & 8)
require_once __DIR__ . '/includes/functions.php';

$user = require_login();
$categories = get_categories();
$categoryIds = array_column($categories, 'id');

$errors = [];
$old = [
    'title' => '',
    'category_id' => '',
    'location' => '',
    'description' => '',
    'impact' => '',
    'is_anonymous' => (int) $user['default_anon'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach (['title', 'category_id', 'location', 'description', 'impact'] as $k) {
        $old[$k] = trim($_POST[$k] ?? '');
    }
    $old['is_anonymous'] = empty($_POST['is_anonymous']) ? 0 : 1;

    if ($old['title'] === '' || mb_strlen($old['title']) > 100) {
        $errors[] = 'Le titre est obligatoire (100 caractères maximum).';
    }
    if (!in_array((int) $old['category_id'], array_map('intval', $categoryIds), true)) {
        $errors[] = 'Veuillez sélectionner une catégorie.';
    }
    if (mb_strlen($old['description']) < 20) {
        $errors[] = 'La description doit contenir au moins 20 caractères.';
    }
    if (mb_strlen($old['location']) > 150 || mb_strlen($old['impact']) > 255) {
        $errors[] = 'Le lieu ou l\'impact estimé est trop long.';
    }

    if (!$errors) {
        $stmt = db()->prepare('
            INSERT INTO requests (user_id, category_id, title, description, location, impact, is_anonymous, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, \'pending\')
        ');
        $stmt->execute([
            $user['id'], (int) $old['category_id'], $old['title'], $old['description'],
            $old['location'] ?: null, $old['impact'] ?: null, $old['is_anonymous'],
        ]);
        $id = (int) db()->lastInsertId();
        flash('success', 'Votre requête ' . request_ref($id) . ' a été soumise ! Elle sera visible dans le fil dès sa validation par la modération.');
        redirect('detail.php?id=' . $id);
    }
}

$pageTitle = 'Proposer une Requête';
$activeNav = 'create';
require __DIR__ . '/includes/header.php';
?>

  <div class="container" style="padding-top: 1.5rem;">
    <nav class="breadcrumb" aria-label="Fil d'Ariane">
      <a href="index.php">&larr; Retour au fil</a>
      <span>/</span>
      <span>Proposer une nouvelle requête</span>
    </nav>
  </div>

  <main class="container" style="padding-bottom: 3.5rem;">
    <div class="feed-layout">
      <section class="form-card">
        <div style="margin-bottom: 1.75rem;">
          <span class="badge badge-voting" style="margin-bottom: 0.5rem;">Initiative Étudiante</span>
          <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main);">Proposer une amélioration pour le campus</h1>
          <p style="font-size: 0.95rem; color: var(--text-muted); margin-top: 0.25rem;">
            Partagez votre idée avec la communauté. Si elle recueille plus de <?= VOTE_THRESHOLD ?> votes favorables, elle sera officiellement soumise à la commission du campus.
          </p>
        </div>

        <?php if ($errors): ?>
          <div class="alert alert-danger form-errors" role="alert">
            <div><strong>Veuillez corriger les points suivants :</strong><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
          </div>
        <?php endif; ?>

        <form action="create.php" method="post">
          <?= csrf_field() ?>
          <div class="form-group">
            <label for="req-title" class="form-label">
              Titre de la requête <span style="color: var(--danger);">*</span>
              <span class="form-hint">Clair et concis (max. 100 car.)</span>
            </label>
            <input type="text" id="req-title" name="title" class="form-control" placeholder="Ex: Installation d'un abri vélo sécurisé au bâtiment A" required maxlength="100" value="<?= e($old['title']) ?>">
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
              <label for="req-category" class="form-label">Catégorie <span style="color: var(--danger);">*</span></label>
              <select id="req-category" name="category_id" class="form-control" required>
                <option value="">Sélectionner une catégorie</option>
                <?php foreach ($categories as $c): ?>
                  <option value="<?= (int) $c['id'] ?>"<?= (string) $c['id'] === (string) $old['category_id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="req-location" class="form-label">Lieu concerné</label>
              <input type="text" id="req-location" name="location" class="form-control" placeholder="Ex: Bâtiment A, Cafétéria, Extérieur..." maxlength="150" value="<?= e($old['location']) ?>">
            </div>
          </div>

          <div class="form-group">
            <label for="req-desc" class="form-label">
              Description et justification du besoin <span style="color: var(--danger);">*</span>
              <span class="form-hint">Expliquez le problème actuel et votre solution</span>
            </label>
            <textarea id="req-desc" name="description" class="form-control" rows="6" placeholder="Décrivez en détail la situation actuelle, les difficultés rencontrées par les étudiants et la proposition concrète d'amélioration..." required minlength="20"><?= e($old['description']) ?></textarea>
          </div>

          <div class="form-group">
            <label for="req-impact" class="form-label">
              Impact estimé sur le campus
              <span class="form-hint">Combien d'étudiants en bénéficieront ?</span>
            </label>
            <input type="text" id="req-impact" name="impact" class="form-control" placeholder="Ex: Toute la promo B1/B2, cyclistes réguliers (environ 50 étudiants/jour)..." maxlength="255" value="<?= e($old['impact']) ?>">
          </div>

          <!-- Option Anonymisation des requêtes (Story 8) -->
          <label class="switch-container">
            <div class="switch-details">
              <span class="switch-icon">🎭</span>
              <div>
                <strong class="switch-title">Publier cette requête de manière 100% anonyme</strong>
                <span class="switch-desc">Votre nom, prénom et promo ne seront affichés ni publiquement, ni aux autres étudiants.</span>
              </div>
            </div>
            <div class="switch-toggle">
              <input type="checkbox" id="anon-switch" name="is_anonymous" value="1"<?= $old['is_anonymous'] ? ' checked' : '' ?>>
              <span class="slider"></span>
            </div>
          </label>

          <div class="alert alert-info">
            <span style="font-size: 1.25rem;">ℹ️</span>
            <div>
              <strong>Charte de bienveillance du Campus RAMMSS :</strong>
              <p>Votre requête sera visible dans le fil dès sa validation par l'équipe de modération. Vous recevrez une notification dès que les votes commenceront.</p>
            </div>
          </div>

          <div style="display: flex; align-items: center; justify-content: flex-end; gap: 1rem; margin-top: 2rem;">
            <a href="index.php" class="btn btn-secondary">Annuler</a>
            <button type="submit" class="btn btn-primary btn-lg">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
              Soumettre ma requête au vote
            </button>
          </div>
        </form>
      </section>

      <!-- Sidebar d'aide (Story 9) -->
      <aside class="feed-sidebar">
        <div class="sidebar-card">
          <h2 class="sidebar-title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            Conseils pour une requête réussie
          </h2>
          <ul style="padding-left: 1.25rem; font-size: 0.875rem; color: var(--text-muted); display: flex; flex-direction: column; gap: 0.75rem;">
            <li><strong>Soyez précis dans le titre :</strong> Évitez les formules vagues et nommez directement l'action souhaitée.</li>
            <li><strong>Chiffrez l'utilité :</strong> Montrez à l'administration pourquoi cet investissement est rentable ou indispensable.</li>
            <li><strong>Proposez des solutions réalistes :</strong> Tenez compte des contraintes de sécurité et d'espace du campus.</li>
            <li><strong>Mobilisez votre promo :</strong> Dès publication, partagez le lien du sondage pour atteindre rapidement le seuil des <?= VOTE_THRESHOLD ?> votes.</li>
          </ul>
        </div>

        <div class="sidebar-card">
          <h2 class="sidebar-title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>
            Calendrier des commissions
          </h2>
          <p style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.5;">
            La commission consultative Direction / BDE se réunit chaque premier jeudi du mois pour examiner les requêtes ayant franchi le seuil.
          </p>
          <?php
            // Prochain premier jeudi du mois
            $next = new DateTime('first thursday of this month');
            if ($next < new DateTime('today')) {
                $next = new DateTime('first thursday of next month');
            }
          ?>
          <div style="margin-top: 1rem; padding: 0.75rem; border-radius: var(--radius-md); background-color: var(--primary-light); color: var(--primary); font-weight: 600; font-size: 0.825rem; text-align: center;">
            Prochaine commission : <?= e(format_date_fr($next->format('Y-m-d'))) ?>
          </div>
        </div>
      </aside>
    </div>
  </main>

<?php require __DIR__ . '/includes/footer.php'; ?>
