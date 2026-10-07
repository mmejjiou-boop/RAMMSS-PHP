<?php
// Paramètres personnels : thème, notifications et anonymat par défaut (Story 4)
require_once __DIR__ . '/includes/functions.php';

$user = require_login();

$themes = [
    'auto'  => ['label' => 'Automatique', 'desc' => 'Suit le réglage de votre système', 'preview' => 'Auto 🖥️', 'class' => 'preview-auto'],
    'light' => ['label' => 'Clair', 'desc' => 'Contraste optimal de jour', 'preview' => 'Mode Clair ☀️', 'class' => 'preview-light'],
    'dark'  => ['label' => 'Sombre', 'desc' => 'Idéal pour la nuit et le repos visuel', 'preview' => 'Mode Sombre 🌙', 'class' => 'preview-dark'],
];
$adminFunctions = [
    'direction'  => 'Direction du Campus & Commission Budgétaire',
    'bde'        => 'Bureau Des Étudiants',
    'scolarite'  => 'Scolarité & Vie Étudiante',
    'logistique' => 'Logistique, Bâtiments & Matériel',
    'moderation' => 'Modération de la charte',
];
$toggles = ['notif_validation', 'notif_threshold', 'notif_comments', 'default_anon'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $theme = array_key_exists($_POST['theme'] ?? '', $themes) ? $_POST['theme'] : 'auto';
    $values = ['theme' => $theme];
    foreach ($toggles as $t) {
        $values[$t] = empty($_POST[$t]) ? 0 : 1;
    }
    $promo = trim($_POST['promo'] ?? '');
    $values['promo'] = $promo === '' ? null : mb_substr($promo, 0, 100);

    $stmt = db()->prepare('
        UPDATE users SET theme = :theme, notif_validation = :notif_validation, notif_threshold = :notif_threshold,
               notif_comments = :notif_comments, default_anon = :default_anon, promo = :promo
        WHERE id = :id
    ');
    $stmt->execute($values + ['id' => $user['id']]);
    flash('success', 'Vos préférences ont été enregistrées avec succès.');
    redirect('settings.php');
}

$pageTitle = 'Paramètres & Profil';
$pageDescription = 'Campus Life - Paramètres personnels, personnalisation du thème et notifications.';
$activeNav = 'settings';
$extraHead = <<<'HTML'
  <style>
    .theme-picker-group { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 1rem; margin-top: 0.5rem; }
    .theme-card-option { position: relative; border: 2px solid var(--border-color); border-radius: var(--radius-lg); padding: 1rem; cursor: pointer; display: flex; flex-direction: column; gap: 0.5rem; transition: all var(--transition-fast); background-color: var(--bg-surface); }
    .theme-card-option:hover, .theme-card-option:has(input:checked) { border-color: var(--primary); }
    .theme-card-option input[type="radio"] { position: absolute; top: 0.75rem; right: 0.75rem; }
    .theme-card-option input[type="radio"]:checked ~ .theme-name { color: var(--primary); font-weight: 700; }
    .theme-preview-box { height: 60px; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; }
    .preview-light { background-color: #f8fafc; border: 1px solid #cbd5e1; color: #0f172a; }
    .preview-dark { background-color: #0b0f19; border: 1px solid #334155; color: #f3f4f6; }
    .preview-auto { background: linear-gradient(135deg, #f8fafc 50%, #0b0f19 50%); border: 1px solid #94a3b8; color: #64748b; }
    .settings-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem; }
    @media (max-width: 640px) { .settings-grid { grid-template-columns: 1fr; } }
  </style>
HTML;
require __DIR__ . '/includes/header.php';

$isAdmin = $user['role'] === 'admin';
$switch = function (string $name, string $icon, string $title, string $desc) use ($user): void { ?>
        <label class="switch-container">
          <div class="switch-details">
            <span class="switch-icon"><?= $icon ?></span>
            <div>
              <strong class="switch-title"><?= e($title) ?></strong>
              <span class="switch-desc"><?= e($desc) ?></span>
            </div>
          </div>
          <div class="switch-toggle">
            <input type="checkbox" name="<?= e($name) ?>" value="1"<?= (int) $user[$name] === 1 ? ' checked' : '' ?>>
            <span class="slider"></span>
          </div>
        </label>
<?php };
?>

  <main class="container-narrow" style="padding-block: 2.5rem 4rem;">
    <div style="margin-bottom: 2rem;">
      <h1 style="font-size: 2rem; font-weight: 800; color: var(--text-main);">Paramètres de l'application</h1>
      <p style="font-size: 0.95rem; color: var(--text-muted); margin-top: 0.35rem;">
        Personnalisez votre interface, vos notifications de vote et consultez vos accréditations campus.
      </p>
    </div>

    <form action="settings.php" method="post" style="display: flex; flex-direction: column; gap: 2rem;">
      <?= csrf_field() ?>

      <!-- Thème de l'application -->
      <section class="form-card" aria-labelledby="theme-heading">
        <h2 id="theme-heading" style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem;">🎨 Thème et Apparence</h2>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.25rem;">
          Choisissez le mode visuel de l'application. L'aperçu est appliqué immédiatement, pensez à enregistrer.
        </p>
        <div class="theme-picker-group">
          <?php foreach ($themes as $key => $t): ?>
            <label class="theme-card-option">
              <input type="radio" name="theme" value="<?= e($key) ?>"<?= $user['theme'] === $key ? ' checked' : '' ?>>
              <div class="theme-preview-box <?= e($t['class']) ?>"><?= e($t['preview']) ?></div>
              <span class="theme-name" style="font-size: 0.9rem; font-weight: 600;"><?= e($t['label']) ?></span>
              <span style="font-size: 0.75rem; color: var(--text-muted);"><?= e($t['desc']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- Notifications -->
      <section class="form-card" aria-labelledby="notif-heading">
        <h2 id="notif-heading" style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem;">🔔 Notifications et Alertes</h2>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem;">
          Gérez les alertes reçues lors des événements clés du campus.
        </p>
        <?php
          $switch('notif_validation', '🎉', "Notification lors de la validation d'une requête", "Être prévenu dès qu'une requête que vous avez proposée ou soutenue est officiellement validée par le campus.");
          $switch('notif_threshold', '🎯', 'Alerte palier des ' . VOTE_THRESHOLD . ' votes', "Être prévenu dès qu'une requête suivie atteint le seuil requis pour l'examen en commission.");
          $switch('notif_comments', '💬', "Commentaires et contributions d'étudiants", "Avertissement lorsqu'un camarade commente une de vos requêtes.");
        ?>
      </section>

      <!-- Informations du compte -->
      <section class="form-card" aria-labelledby="account-heading">
        <h2 id="account-heading" style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem;">👤 Droits et Informations du Compte</h2>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem;">
          Consultez vos accréditations sur la plateforme.
        </p>

        <div class="settings-grid">
          <div class="form-group">
            <label class="form-label" for="user-fullname">Nom complet</label>
            <input type="text" id="user-fullname" class="form-control" value="<?= e($user['first_name'] . ' ' . $user['last_name']) ?>" readonly style="background-color: var(--bg-muted);">
          </div>
          <div class="form-group">
            <label class="form-label" for="user-email">Email</label>
            <input type="email" id="user-email" class="form-control" value="<?= e($user['email']) ?>" readonly style="background-color: var(--bg-muted);">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="promo">Promotion</label>
          <input type="text" id="promo" name="promo" class="form-control" placeholder="Ex: Promo B1 Informatique" maxlength="100" value="<?= e($user['promo']) ?>">
        </div>

        <div class="settings-grid">
          <div class="form-group">
            <label class="form-label">Statut et Droits de vote</label>
            <div style="padding: 0.75rem 1rem; border-radius: var(--radius-md); background-color: var(--success-light); border: 1px solid var(--success-border); color: var(--success); font-weight: 600; font-size: 0.875rem;">
              ✅ Droit de vote actif<?= $user['promo'] ? ' (' . e($user['promo']) . ')' : '' ?>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Rôle sur la plateforme</label>
            <div style="padding: 0.75rem 1rem; border-radius: var(--radius-md); background-color: var(--primary-light); border: 1px solid var(--primary-border); color: var(--primary); font-weight: 600; font-size: 0.875rem;">
              <?php if ($isAdmin): ?>
                🛡️ Administrateur<?= $user['admin_function'] ? ' · ' . e($adminFunctions[$user['admin_function']] ?? $user['admin_function']) : '' ?>
              <?php else: ?>
                🎓 Étudiant contributeur
              <?php endif; ?>
            </div>
          </div>
        </div>

        <?php $switch('default_anon', '🎭', 'Mode Anonymat par défaut', "Cocher automatiquement l'anonymat à chaque nouvelle proposition de requête."); ?>
      </section>

      <div style="display: flex; align-items: center; justify-content: flex-end; gap: 1rem;">
        <a href="index.php" class="btn btn-secondary">Annuler</a>
        <button type="submit" class="btn btn-primary btn-lg">Enregistrer les modifications</button>
      </div>
    </form>
  </main>

  <script>
    // Aperçu immédiat du thème choisi
    document.querySelectorAll('input[name="theme"]').forEach(function (radio) {
      radio.addEventListener('change', function () {
        if (this.value === 'auto') {
          document.documentElement.removeAttribute('data-theme');
        } else {
          document.documentElement.setAttribute('data-theme', this.value);
        }
      });
    });
  </script>

<?php require __DIR__ . '/includes/footer.php'; ?>
