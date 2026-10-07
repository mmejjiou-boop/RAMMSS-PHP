<?php
// Inscription d'un compte étudiant
require_once __DIR__ . '/includes/functions.php';

if (current_user()) {
    redirect('index.php');
}

$errors = [];
$old = ['first_name' => '', 'last_name' => '', 'email' => '', 'promo' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach ($old as $k => $_) {
        $old[$k] = trim($_POST[$k] ?? '');
    }
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';

    if ($old['first_name'] === '' || $old['last_name'] === '') {
        $errors[] = 'Le prénom et le nom sont obligatoires.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "L'adresse email n'est pas valide.";
    }
    if (mb_strlen($password) < 8) {
        $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Les deux mots de passe ne correspondent pas.';
    }
    if (!$errors) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $stmt->execute([$old['email']]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Un compte existe déjà avec cette adresse email.';
        }
    }

    if (!$errors) {
        $stmt = db()->prepare("INSERT INTO users (first_name, last_name, email, password_hash, role, promo) VALUES (?, ?, ?, ?, 'student', ?)");
        $stmt->execute([$old['first_name'], $old['last_name'], $old['email'], password_hash($password, PASSWORD_DEFAULT), $old['promo'] ?: null]);
        login_user(['id' => db()->lastInsertId()]);
        flash('success', 'Bienvenue sur Campus Life ! Votre compte étudiant a été créé.');
        redirect('index.php');
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Créer un compte étudiant | Campus Life RAMMSS</title>
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/app.css">
  <style>
    .auth-page {
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 2rem 1.5rem;
      background: radial-gradient(circle at top, var(--primary-light) 0%, var(--bg-body) 70%);
    }

    .auth-card {
      width: 100%;
      max-width: 520px;
      background-color: var(--bg-surface);
      border: 1px solid var(--border-color);
      border-radius: var(--radius-xl);
      padding: 2.5rem 2rem;
      box-shadow: var(--shadow-lg);
    }
  </style>
</head>
<body class="auth-page">

  <div class="auth-card">
    <div style="text-align: center; margin-bottom: 2rem;">
      <a href="index.php" aria-label="Retour à l'accueil">
        <img src="assets/logo.svg" alt="Logo Campus Life RAMMSS" width="220" height="52" style="margin-inline: auto; margin-bottom: 1.25rem;">
      </a>
      <h1 style="font-size: 1.5rem; font-weight: 800; color: var(--text-main);">Créer un compte étudiant</h1>
      <p style="font-size: 0.875rem; color: var(--text-muted); margin-top: 0.35rem;">Rejoignez la communauté pour voter et proposer des idées.</p>
    </div>

    <?php if ($errors): ?>
      <div class="alert alert-danger form-errors" role="alert">
        <div><strong>Impossible de créer le compte :</strong><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
      </div>
    <?php endif; ?>

    <form action="register.php" method="post">
      <?= csrf_field() ?>
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label for="first_name" class="form-label">Prénom <span style="color: var(--danger);">*</span></label>
          <input type="text" id="first_name" name="first_name" class="form-control" value="<?= e($old['first_name']) ?>" required maxlength="60">
        </div>
        <div class="form-group">
          <label for="last_name" class="form-label">Nom <span style="color: var(--danger);">*</span></label>
          <input type="text" id="last_name" name="last_name" class="form-control" value="<?= e($old['last_name']) ?>" required maxlength="60">
        </div>
      </div>
      <div class="form-group">
        <label for="email" class="form-label">Email campus <span style="color: var(--danger);">*</span></label>
        <input type="email" id="email" name="email" class="form-control" placeholder="prenom.nom@coda.school" value="<?= e($old['email']) ?>" required>
      </div>
      <div class="form-group">
        <label for="promo" class="form-label">Promotion</label>
        <input type="text" id="promo" name="promo" class="form-control" placeholder="Ex: Promo B1 Informatique" value="<?= e($old['promo']) ?>" maxlength="100">
      </div>
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label for="password" class="form-label">Mot de passe <span style="color: var(--danger);">*</span></label>
          <input type="password" id="password" name="password" class="form-control" required minlength="8" autocomplete="new-password">
        </div>
        <div class="form-group">
          <label for="password_confirm" class="form-label">Confirmation <span style="color: var(--danger);">*</span></label>
          <input type="password" id="password_confirm" name="password_confirm" class="form-control" required minlength="8" autocomplete="new-password">
        </div>
      </div>
      <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.85rem; font-size: 1rem;">Créer mon compte &rarr;</button>
    </form>

    <hr style="border: none; border-top: 1px solid var(--border-color); margin-block: 1.5rem;">
    <p style="text-align: center; font-size: 0.85rem; color: var(--text-muted);">
      Déjà inscrit ? <a href="login.php" style="font-weight: 600;">Se connecter</a>
    </p>
  </div>

</body>
</html>
