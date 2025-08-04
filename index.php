<?php
require_once 'lib/functions.lib.php';
start_session();
$page = basename($_GET['page'] ?? 'home');
$page_file = '';
if (file_exists("pages/{$page}.php")) {
    $page_file = "pages/{$page}.php";
} elseif (file_exists("pages/process/{$page}_p.php")) {
    $page_file = "pages/process/{$page}_p.php";
} else {
    $page_file = "pages/404.php"; // Standard, wenn Seite nicht gefunden
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#008080">
    <title>Workout</title>
    <link rel="manifest" href="manifest.json">
    <link rel="stylesheet" href="style.css">
</head>
<body>
  <div id="app">
    <header>
        <div class="top-bar">
            <div class="logo">Workout</div>
            <div class="burger" id="burger-menu">&#9776;</div>
        </div>
        <nav id="nav-menu" class="hidden">
            <ul>
                <li><a href="?page=home">Home</a></li>
<!--                <li><a href="?page=groesse">Größe</a></li>
                <li><a href="?page=about">Über</a></li>
                <li><a href="?page=contact">Kontakt</a></li>-->
            </ul>
        </nav>
    </header>

    <main>
        <?php include $page_file; ?>
    </main>

    <footer>
        <p>&copy; 2025 Workout</p>
    </footer>
  </div>

  <script>
      const burger = document.getElementById('burger-menu');
      const navMenu = document.getElementById('nav-menu');

      burger.addEventListener('click', () => {
          navMenu.classList.toggle('hidden');
      });
  </script>
</body>
</html>

