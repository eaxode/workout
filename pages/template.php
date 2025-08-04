    <?php
    // --- GAST-BEREICH ---
    // Dieser Block wird angezeigt, wenn NIEMAND eingeloggt ist.
    if (!$current_username): ?>
        <div class="guest-section">
            <div class="section-title">Willkommen, Gast!</div>
            <p>Bitte melden Sie sich an oder registrieren Sie sich, um auf Ihr Profil zuzugreifen.</p>
            <p><a href="index.php?page=login">Anmelden</a> | <a href="index.php?page=register">Registrieren</a></p>
        </div>
    <?php endif; ?>

    <?php
    // --- BENUTZER-BEREICH ---
    // Dieser Block wird angezeigt, wenn IRGENDEIN Benutzer eingeloggt ist.
    if ($current_username): ?>
        <div class="user-section">
            <div class="section-title">Hallo, <?php echo htmlspecialchars($current_username); ?>!</div>
            <p>Dies ist Ihr persönlicher Bereich.</p>
            <p>Ihre Benutzer-ID: <?php echo htmlspecialchars($current_user_id); ?></p>
            <p>Sie sind eingeloggt seit: <?php echo date("d.m.Y H:i:s", $_SESSION['login_time'] ?? time()); ?></p>
        </div>
    <?php endif; ?>

    <?php
    // --- MICHAEL-BEREICH ---
    // Dieser Block wird NUR angezeigt, wenn Michael (user_id 1) eingeloggt ist.
    if ($is_michael): ?>
        <div class="michael-section">
            <div class="section-title">Spezielle Ansicht für Michael!</div>
            <p>Hier sind exklusive Informationen für dich, Michael.</p>
            <p>Dies könnte ein Admin-Bereich oder spezielle Statistiken sein.</p>
            <ul>
                <li><a href="index.php?page=create_exercise">Übungen verwalten</a></li>
                <!-- weitere spezifische Links für Michael -->
            </ul>
        </div>
    <?php endif; ?>

</div>
