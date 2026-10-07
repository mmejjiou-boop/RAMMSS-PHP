<?php
// Inscription Administrateur / Staff / BDE (Story 6)
require_once __DIR__ . '/includes/functions.php';

$roles = [
    'direction'  => 'Direction du Campus & Commission Budgétaire',
    'bde'        => 'Bureau Des Étudiants (Présidence / Vice-présidence BDE)',
    'scolarite'  => 'Responsable Scolarité & Vie Étudiante',
    'logistique' => 'Responsable Logistique, Bâtiments & Matériel',
    'moderation' => 'Modérateur de la charte de bienveillance',
];

$errors = [];
$old = ['first_name' => '', 'last_name' => '', 'email' => '', 'admin_function' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach ($old as $k => $_) {
        $old[$k] = trim($_POST[$k] ?? '');
    }
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';

    if ($old['first_name'] === '' || $old['last_name'] === '') {
        $errors[] = 'Le prénom et le nom sont obligatoires.';
    }
    $domain = strtolower(substr(strrchr($old['email'], '@') ?: '', 1));
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || !in_array($domain, ADMIN_EMAIL_DOMAINS, true)) {
        $errors[] = "L'email doit être une adresse institutionnelle (@" . implode(' ou @', ADMIN_EMAIL_DOMAINS) . ').';
    }
    if (!hash_equals(ADMIN_SECRET_KEY, $token)) {
        $errors[] = "La clé secrète d'accréditation campus est invalide.";
    }
    if (!array_key_exists($old['admin_function'], $roles)) {
        $errors[] = 'Veuillez sélectionner votre fonction sur le campus.';
    }
    if (mb_strlen($password) < 8) {
        $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Les deux mots de passe ne correspondent pas.';
    }
    if (empty($_POST['charter'])) {
        $errors[] = 'Vous devez accepter la charte de modération démocratique.';
    }
    if (!$errors) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $stmt->execute([$old['email']]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Un compte existe déjà avec cette adresse email.';
        }
    }

    if (!$errors) {
        $stmt = db()->prepare("INSERT INTO users (first_name, last_name, email, password_hash, role, admin_function, promo) VALUES (?, ?, ?, ?, 'admin', ?, ?)");
        $stmt->execute([
            $old['first_name'], $old['last_name'], $old['email'],
            password_hash($password, PASSWORD_DEFAULT), $old['admin_function'], $roles[$old['admin_function']],
        ]);
        login_user(['id' => db()->lastInsertId()]);
        flash('success', 'Compte administrateur créé. Bienvenue dans l\'espace de modération !');
        redirect('admin.php');
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Campus Life - Inscription réservée aux membres de l'administration et du BDE.">
  <title>Inscription Administrateur | Campus Life RAMMSS</title>
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/app.css">
  <style>
    .admin-auth-page {
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 3rem 1.5rem;
      background: radial-gradient(circle at top, rgba(79, 70, 229, 0.12) 0%, var(--bg-body) 70%);
    }

    .admin-auth-card {
      width: 100%;
      max-width: 580px;
      background-color: var(--bg-surface);
      border: 1px solid var(--border-color);
      border-radius: var(--radius-xl);
      padding: 2.75rem 2.25rem;
      box-shadow: var(--shadow-lg);
    }
  </style>
</head>
<body class="admin-auth-page">

  <div class="admin-auth-card">
    <div style="text-align: center; margin-bottom: 2rem;">
      <a href="index.php" aria-label="Retour à l'accueil">
        <img src="assets/logo.svg" alt="Logo Campus Life RAMMSS" width="220" height="52" style="margin-inline: auto; margin-bottom: 1.25rem;">
      </a>
      <span class="badge badge-validated" style="margin-bottom: 0.5rem;">Espace Réservé &bull; Staff & BDE</span>
      <h1 style="font-size: 1.6rem; font-weight: 800; color: var(--text-main); margin-top: 0.25rem;">
        Création de Compte Administrateur
      </h1>
      <p style="font-size: 0.875rem; color: var(--text-muted); margin-top: 0.35rem;">
        Ce formulaire permet aux membres accrédités de créer un profil de modération et de validation officielle des requêtes (Story 6).
      </p>
    </div>

    <?php if ($errors): ?>
      <div class="alert alert-danger form-errors" role="alert">
        <div><strong>Inscription refusée :</strong><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
      </div>
    <?php endif; ?>

    <form action="admin-register.php" method="post">
      <?= csrf_field() ?>
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label for="admin-firstname" class="form-label">Prénom <span style="color: var(--danger);">*</span></label>
          <input type="text" id="admin-firstname" name="first_name" class="form-control" placeholder="Jean" value="<?= e($old['first_name']) ?>" required maxlength="60">
        </div>
        <div class="form-group">
          <label for="admin-lastname" class="form-label">Nom <span style="color: var(--danger);">*</span></label>
          <input type="text" id="admin-lastname" name="last_name" class="form-control" placeholder="Dupont" value="<?= e($old['last_name']) ?>" required maxlength="60">
        </div>
      </div>

      <div class="form-group">
        <label for="admin-email" class="form-label">
          Adresse email institutionnelle / staff <span style="color: var(--danger);">*</span>
          <span class="form-hint">Doit se terminer par @<?= e(implode(' ou @', ADMIN_EMAIL_DOMAINS)) ?></span>
        </label>
        <input type="email" id="admin-email" name="email" class="form-control" placeholder="direction@coda.school" value="<?= e($old['email']) ?>" required>
      </div>

      <div class="form-group">
        <label for="admin-token" class="form-label">
          Clé secrète d'accréditation campus <span style="color: var(--danger);">*</span>
          <span class="form-hint">Fournie par la direction informatique</span>
        </label>
        <input type="password" id="admin-token" name="token" class="form-control" placeholder="RAMMSS-ADMIN-XXXX" required>
      </div>

      <div class="form-group">
        <label for="admin-role" class="form-label">Fonction / Rôle sur le campus <span style="color: var(--danger);">*</span></label>
        <select id="admin-role" name="admin_function" class="form-control" required>
          <option value="">Sélectionnez votre attribution</option>
          <?php foreach ($roles as $value => $label): ?>
            <option value="<?= $value ?>"<?= $old['admin_function'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label for="admin-pwd" class="form-label">Mot de passe <span style="color: var(--danger);">*</span></label>
          <input type="password" id="admin-pwd" name="password" class="form-control" placeholder="••••••••••••" required minlength="8" autocomplete="new-password">
        </div>
        <div class="form-group">
          <label for="admin-pwd-confirm" class="form-label">Confirmation <span style="color: var(--danger);">*</span></label>
          <input type="password" id="admin-pwd-confirm" name="password_confirm" class="form-control" placeholder="••••••••••••" required minlength="8" autocomplete="new-password">
        </div>
      </div>

      <div class="alert alert-info" style="font-size: 0.8rem;">
        <span style="font-size: 1.2rem;">🛡️</span>
        <div>
          <strong>Responsabilité administrative :</strong>
          <p>En tant qu'administrateur, vous disposez des droits d'approbation, de modération et de validation officielle des projets du campus. Tout acte de modération est consigné dans le journal d'audit.</p>
        </div>
      </div>

      <div style="margin-bottom: 1.75rem;">
        <label style="display: flex; align-items: flex-start; gap: 0.6rem; font-size: 0.85rem; color: var(--text-muted); cursor: pointer;">
          <input type="checkbox" name="charter" value="1" required style="margin-top: 0.2rem;">
          <span>J'atteste faire partie de l'équipe encadrante ou du bureau du BDE et j'accepte la charte de modération démocratique.</span>
        </label>
      </div>

      <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.85rem; font-size: 1rem;">
        Finaliser l'inscription & Accéder au Dashboard &rarr;
      </button>
    </form>

    <hr style="border: none; border-top: 1px solid var(--border-color); margin-block: 1.5rem;">

    <div style="text-align: center; font-size: 0.85rem;">
      <p style="color: var(--text-muted);">
        Vous avez déjà un compte ?
        <a href="login.php" style="font-weight: 600;">Se connecter</a>
        <span> &bull; </span>
        <a href="index.php" style="font-weight: 600;">Retour au fil</a>
      </p>
    </div>
  </div>

</body>
</html>
