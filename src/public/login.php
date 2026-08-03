<?php
require_once __DIR__ . '/../app/Core/bootstrap.php';

// Reject state-changing (POST) requests without a valid CSRF token.
require_once __DIR__ . '/../app/Middleware/csrf.php';

use App\Core\Auth;

$error  = null;
$notice = null;

// Set by bootstrap.php when it drops a session past its idle/absolute limit.
if (!empty($_SESSION['expired'])) {
    unset($_SESSION['expired']);
    $notice = 'Your session expired. Please sign in again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Throttle before verifying: unlimited guesses against a bcrypt hash is
    // still unlimited guesses. Keyed per session + email so one attacker cannot
    // lock out an unrelated user by hammering their address.
    if (Auth::isThrottled($email)) {
        $error = 'Too many failed attempts. Please wait a minute and try again.';
    } elseif (Auth::attempt($email, $password)) {
        Auth::clearFailures($email);
        header('Location: /dashboard.php');
        exit;
    } else {
        Auth::recordFailure($email);
        // Deliberately identical whether the address exists or not.
        $error = 'Invalid login credentials.';
    }
}

include __DIR__ . '/../app/Shared/header.php';
?>

<main class="login-shell">
    <section class="login-card">
        <h1>Typhon Cath CRM</h1>
        <p class="text-muted">Sign in to continue.</p>

        <?php if ($notice): ?>
            <div class="alert alert-info"><?= htmlspecialchars($notice) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= App\Core\Csrf::field() ?>
            <label>Email</label>
            <input class="form-control" type="email" name="email" required>

            <label>Password</label>
            <input class="form-control" type="password" name="password" required>

            <button class="btn btn-primary w-100 mt-3" type="submit">Login</button>
        </form>
    </section>
</main>

<?php include __DIR__ . '/../app/Shared/footer.php'; ?>
