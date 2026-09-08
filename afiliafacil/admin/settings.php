<?php
require_once __DIR__ . '/../lib/Config.php';
require_once Config::getLibDir() . '/Auth.php';
Auth::requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $theme = $_POST['theme'] ?? 'light';
    $_SESSION['theme'] = $theme === 'dark' ? 'dark' : 'light';
    header('Location: /admin/settings.php?saved=1');
    exit;
}

$theme = $_SESSION['theme'] ?? 'light';
$saved = isset($_GET['saved']);
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - AfiliaFacil</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/theme-<?= $theme ?>.css">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <div class="layout">
        <?php include __DIR__ . '/sidebar.php'; ?>
        <div class="main-content">
            <div class="topbar">
                <div class="topbar-title">Configurações</div>
                <div class="topbar-actions">
                    <button class="theme-toggle" onclick="toggleTheme()">
                        <i class="fas fa-<?= $theme === 'dark' ? 'sun' : 'moon' ?>"></i>
                    </button>
                </div>
            </div>
            <div class="page-content">
                <?php if ($saved): ?>
                <div class="alert alert-success"><i class="fas fa-check"></i> Configurações salvas com sucesso!</div>
                <?php endif; ?>

                <div class="grid-2">
                    <div class="card">
                        <div class="card-header"><h3><i class="fas fa-palette"></i> Tema</h3></div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="form-group">
                                    <label>Aparência do painel</label>
                                    <div style="display:flex;gap:12px;margin-top:8px;">
                                        <label style="flex:1;cursor:pointer;">
                                            <input type="radio" name="theme" value="light" <?= $theme === 'light' ? 'checked' : '' ?> style="display:none;">
                                            <div style="border:2px solid <?= $theme === 'light' ? 'var(--accent)' : 'var(--border-color)' ?>;border-radius:var(--radius);padding:16px;text-align:center;transition:all .2s;">
                                                <i class="fas fa-sun" style="font-size:1.5rem;color:#ffc107;"></i>
                                                <div style="margin-top:8px;font-weight:500;">Claro</div>
                                            </div>
                                        </label>
                                        <label style="flex:1;cursor:pointer;">
                                            <input type="radio" name="theme" value="dark" <?= $theme === 'dark' ? 'checked' : '' ?> style="display:none;">
                                            <div style="border:2px solid <?= $theme === 'dark' ? 'var(--accent)' : 'var(--border-color)' ?>;border-radius:var(--radius);padding:16px;text-align:center;transition:all .2s;">
                                                <i class="fas fa-moon" style="font-size:1.5rem;color:#3d8bfd;"></i>
                                                <div style="margin-top:8px;font-weight:500;">Escuro</div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><h3><i class="fas fa-user"></i> Conta</h3></div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>Nome</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($_SESSION['user_name'] ?? '') ?>" disabled>
                            </div>
                            <div class="form-group">
                                <label>E-mail</label>
                                <input type="email" class="form-control" value="<?= htmlspecialchars($_SESSION['user_email'] ?? '') ?>" disabled>
                            </div>
                            <div class="form-group">
                                <label>Plano</label>
                                <input type="text" class="form-control" value="<?= ucfirst($_SESSION['user_plan'] ?? 'free') ?>" disabled>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="/assets/js/app.js"></script>
</body>
</html>
