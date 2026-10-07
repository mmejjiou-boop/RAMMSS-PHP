<?php
// Page de connexion étudiante (Story 1)
require_once __DIR__ . '/includes/functions.php';

$redirectTo = safe_redirect_target($_POST['redirect'] ?? $_GET['redirect'] ?? null);
if (current_user()) {
    redirect($redirectTo);
}

$error = false;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $account = $stmt->fetch();

    if ($account && password_verify($password, $account['password_hash'])) {
        login_user($account);
        if (!empty($_POST['remember'])) {
            // Prolonge le cookie de session à 30 jours
            $params = session_get_cookie_params();
            setcookie(session_name(), session_id(), [
                'expires' => time() + 60 * 60 * 24 * 30,
                'path' => $params['path'],
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        flash('success', 'Bienvenue ' . $account['first_name'] . ' ! Vous êtes connecté.');
        redirect($account['role'] === 'admin' && $redirectTo === 'index.php' ? 'admin.php' : $redirectTo);
    }
    $error = true;
}

$flashes = get_flashes();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Campus Life - Page de connexion à l'espace étudiant et démocratique.">
  <title>Connexion Étudiante | Campus Life RAMMSS</title>
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
      max-width: 440px;
      background-color: var(--bg-surface);
      border: 1px solid var(--border-color);
      border-radius: var(--radius-xl);
      padding: 2.5rem 2rem;
      box-shadow: var(--shadow-lg);
    }

    .auth-header {
      text-align: center;
      margin-bottom: 2rem;
    }

    .auth-header img {
      margin-inline: auto;
      margin-bottom: 1.25rem;
    }
  </style>
</head>
<body class="auth-page">

  <div class="auth-card">
    <div class="auth-header">
      <a href="index.php" aria-label="Retour à l'accueil">
        <img src="assets/logo.svg" alt="Logo Campus Life RAMMSS" width="220" height="52">
      </a>
      <h1 style="font-size: 1.5rem; font-weight: 800; color: var(--text-main);">Espace Étudiant</h1>
      <p style="font-size: 0.875rem; color: var(--text-muted); margin-top: 0.35rem;">
        Connectez-vous pour participer aux votes et soumettre des idées
      </p>
      <P>ADMIN : direction@coda.school / admin2026!.</P>
      <p>ETUDIENT : clara.dupont@coda.school / campus2026!secret</p>
    </div>

    <?php foreach ($flashes as $f): ?>
      <div class="alert alert-<?= e($f['type']) ?>" role="alert"><div><?= e($f['message']) ?></div></div>
    <?php endforeach; ?>

    <?php if ($error): ?>
    <!-- Message d'erreur (Story 1 - Critère 3) -->
    <div class="alert alert-danger" role="alert">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
      <div>
        <strong>Échec de la connexion :</strong>
        <p style="font-size: 0.825rem; margin-top: 0.15rem;">Identifiant ou mot de passe incorrect. Vérifiez vos accès campus.</p>
      </div>
    </div>
    <?php endif; ?>

    <form action="login.php" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="redirect" value="<?= e($redirectTo) ?>">
      <div class="form-group">
        <label for="login-id" class="form-label">Email campus</label>
        <input type="email" id="login-id" name="email" class="form-control" placeholder="ex: fdp@exemple.com" value="<?= e($email) ?>" required autocomplete="username" autofocus>
      </div>

      <div class="form-group">
        <div class="form-label">
          <label for="login-pass">Mot de passe</label>
        </div>
        <input type="password" id="login-pass" name="password" class="form-control" placeholder="••••••••••••" required autocomplete="current-password">
      </div>

      <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; font-size: 0.85rem;">
        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; color: var(--text-muted);">
          <input type="checkbox" name="remember" value="1" checked>
          Rester connecté sur ce poste
        </label>
      </div>

      <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.85rem; font-size: 1rem;">
        Se connecter &rarr;
      </button>

      <div style="margin-top: 1.25rem; text-align: center; padding: 0.75rem; border-radius: var(--radius-md); background-color: var(--bg-muted); font-size: 0.775rem; color: var(--text-muted);">
        Comptes de démo : <strong>fdp@exemple.com</strong> / campus2026!secret
        &bull; Admin : <strong>direction@coda.school</strong> / admin2026!
      </div>
    </form>

    <hr style="border: none; border-top: 1px solid var(--border-color); margin-block: 1.5rem;">

    <div style="display: flex; flex-direction: column; gap: 0.75rem; text-align: center; font-size: 0.85rem;">
      <p style="color: var(--text-muted);">
        Pas encore de compte ?
        <a href="register.php" style="font-weight: 600;">Créer un compte étudiant &rarr;</a>
      </p>
      <p style="color: var(--text-muted);">
        Vous êtes membre de la direction ou du BDE ?
        <a href="admin-register.php" style="font-weight: 600;">Accès Inscription Administrateur &rarr;</a>
      </p>
      <a href="index.php" style="color: var(--text-muted); font-size: 0.8rem;">
        &larr; Continuer la navigation sans se connecter
      </a>
    </div>
  </div>

</body>
</html>
