  <!-- Pied de page -->
  <footer class="site-footer">
    <div class="container footer-inner">
      <div>
        <strong>Campus Life &bull; Groupe RAMMSS</strong>
        <p style="font-size: 0.8rem; margin-top: 0.25rem;">Projet collaboratif de démocratie étudiante et amélioration du campus.</p>
      </div>
      <div class="footer-links">
        <a href="index.php">Fil des requêtes</a>
        <a href="create.php">Nouvelle requête</a>
        <?php if (is_admin()): ?><a href="admin.php">Espace Admin</a><?php endif; ?>
        <a href="settings.php">Paramètres</a>
        <?php if (current_user()): ?>
          <a href="logout.php">Déconnexion</a>
        <?php else: ?>
          <a href="login.php">Connexion</a>
        <?php endif; ?>
      </div>
    </div>
  </footer>

</body>
</html>
