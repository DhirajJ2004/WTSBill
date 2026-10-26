<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password &mdash; WTSBill ERP</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0f172a;
            color: #f8fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 1rem;
            width: 100%;
            max-width: 420px;
            padding: 2.25rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
        }
        .header { text-align: center; margin-bottom: 2rem; }
        h1 { font-size: 1.5rem; font-weight: 700; color: #f8fafc; margin-bottom: 0.35rem; }
        .subtitle { font-size: 0.875rem; color: #94a3b8; }
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            margin-bottom: 1.25rem;
        }
        .alert-error { background: #451a1a; border: 1px solid #7f1d1d; color: #fca5a5; }
        .alert-success { background: #143525; border: 1px solid #14532d; color: #86efac; }
        .form-group { margin-bottom: 1.25rem; }
        label { display: block; font-size: 0.875rem; font-weight: 500; color: #cbd5e1; margin-bottom: 0.5rem; }
        input[type="email"] {
            width: 100%;
            padding: 0.75rem 1rem;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 0.5rem;
            color: #f8fafc;
            font-size: 0.95rem;
            outline: none;
        }
        input[type="email"]:focus { border-color: #38bdf8; }
        .btn-submit {
            width: 100%;
            padding: 0.85rem;
            background: #0284c7;
            color: #ffffff;
            border: none;
            border-radius: 0.5rem;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 0.5rem;
        }
        .btn-submit:hover { background: #0369a1; }
        .links { margin-top: 1.5rem; text-align: center; font-size: 0.875rem; }
        .links a { color: #38bdf8; text-decoration: none; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1>Reset Password</h1>
            <p class="subtitle">Enter your registered email to request a reset token</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?= e($success) ?></div>
        <?php endif; ?>

        <form action="/forgot-password" method="POST">
            <input type="hidden" name="_csrf_token" value="<?= e($csrf_token ?? '') ?>">

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="name@company.in" required autofocus>
            </div>

            <button type="submit" class="btn-submit">Request Reset Token &rarr;</button>

            <div class="links">
                <a href="/login">&larr; Back to Login</a>
            </div>
        </form>
    </div>
</body>
</html>
