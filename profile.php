<?php include __DIR__ . '/config.php'; ?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(($appSettings['language'] ?? 'en') === 'ur' ? 'ur' : 'en', ENT_QUOTES, 'UTF-8') ?>"<?= ($appSettings['language'] ?? 'en') === 'ur' ? ' dir="rtl"' : '' ?>>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Profile</title>
  <?php $pageTitle = 'Profile'; ?>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">

  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="theme-<?= htmlspecialchars($appSettings['theme'] ?? 'luxury', ENT_QUOTES, 'UTF-8') ?>" data-bs-theme="<?= ($appSettings['theme'] ?? '') === 'dark' ? 'dark' : 'light' ?>">

<div class="app">

  <!-- ================= SIDEBAR ================= -->
  <?php include "layouts/sidebar.php"; ?>


  <!-- ================= MAIN ================= -->
  <main class="app-main">

    <?php include "layouts/topbar.php"; ?>

    <!-- Page title -->
    <section class="page-head">
      <h1>Profile</h1>
      <p>Your personal details, settings and conversations.</p>
    </section>

    <!-- Profile header -->
    <section class="card mb-4">
      <div class="card-body d-flex flex-wrap align-items-center gap-3">
        <span class="avatar avatar-lg bg-a">RD</span>
        <div class="me-auto">
          <h5 class="mb-0">Richard Davis</h5>
          <p class="text-muted mb-0">CEO / Co-Founder</p>
        </div>
        <ul class="nav nav-pills profile-tabs">
          <li class="nav-item"><a class="nav-link active" href="#"><i class="bi bi-grid me-1"></i>App</a></li>
          <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-chat-dots me-1"></i>Message</a></li>
          <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-gear me-1"></i>Settings</a></li>
        </ul>
      </div>
    </section>

    <section class="row g-3 mb-4">

      <!-- Platform settings -->
      <div class="col-12 col-xl-4">
        <div class="card h-100">
          <div class="card-body">
            <h6 class="card-title mb-3">Platform settings</h6>

            <p class="group-label">Account</p>
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" role="switch" id="accFollow" checked>
              <label class="form-check-label" for="accFollow">Email me when someone follows me</label>
            </div>
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" role="switch" id="accAnswer">
              <label class="form-check-label" for="accAnswer">Email me when someone answers my post</label>
            </div>
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" role="switch" id="accMention" checked>
              <label class="form-check-label" for="accMention">Email me when someone mentions me</label>
            </div>

            <p class="group-label mt-3">Application</p>
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" role="switch" id="appLaunch">
              <label class="form-check-label" for="appLaunch">New launches and projects</label>
            </div>
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" role="switch" id="appUpdate" checked>
              <label class="form-check-label" for="appUpdate">Monthly product updates</label>
            </div>
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" role="switch" id="appNews">
              <label class="form-check-label" for="appNews">Subscribe to newsletter</label>
            </div>
          </div>
        </div>
      </div>

      <!-- Profile information -->
      <div class="col-12 col-xl-4">
        <div class="card h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h6 class="card-title mb-0">Profile information</h6>
              <a href="#" class="reply-link" aria-label="Edit profile"><i class="bi bi-pencil"></i></a>
            </div>

            <p class="card-sub">Hi, I'm Richard. I build products that help teams work better together and I enjoy turning ideas into simple designs.</p>

            <ul class="info-list">
              <li><b>Full name:</b> Richard Davis</li>
              <li><b>Mobile:</b> (44) 123 1234 123</li>
              <li><b>Email:</b> richard@mail.com</li>
              <li><b>Location:</b> USA</li>
              <li class="social-links">
                <b>Social:</b>
                <a href="#" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
                <a href="#" aria-label="X"><i class="bi bi-twitter-x"></i></a>
                <a href="#" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Conversations -->
      <div class="col-12 col-xl-4">
        <div class="card h-100">
          <div class="card-body">
            <h6 class="card-title mb-3">Conversations</h6>

            <ul class="list-rows">
              <li>
                <div class="person">
                  <span class="avatar bg-b">SB</span>
                  <div><strong>Sophie B.</strong><small>Hi! I need more information...</small></div>
                </div>
                <a href="#" class="reply-link">Reply</a>
              </li>
              <li>
                <div class="person">
                  <span class="avatar bg-c">AM</span>
                  <div><strong>Anne Marie</strong><small>Awesome work, can you...</small></div>
                </div>
                <a href="#" class="reply-link">Reply</a>
              </li>
              <li>
                <div class="person">
                  <span class="avatar bg-d">IV</span>
                  <div><strong>Ivanna</strong><small>About files I can...</small></div>
                </div>
                <a href="#" class="reply-link">Reply</a>
              </li>
              <li>
                <div class="person">
                  <span class="avatar bg-e">PT</span>
                  <div><strong>Peterson</strong><small>Have a great afternoon...</small></div>
                </div>
                <a href="#" class="reply-link">Reply</a>
              </li>
            </ul>
          </div>
        </div>
      </div>

    </section>

  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
