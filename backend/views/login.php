<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Micro-Task System</title>
        <script>
            (function(){
                try {
                    const t = localStorage.getItem('theme');
                    const theme = (t === 'light' || t === 'dark') ? t : ((window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light');
                    document.documentElement.setAttribute('data-bs-theme', theme);
                } catch (e) {}
            })();
        </script>
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <script src="/assets/js/theme.js" defer></script>
</head>
<body class="bg-body d-flex align-items-center justify-content-center" style="height: 100vh;">

        <div class="position-fixed top-0 end-0 p-3">
            <button type="button" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center" style="width: 2.25rem; height: 2.25rem;" data-theme-toggle aria-label="Toggle theme">☾</button>
        </div>

    <div class="card p-4 shadow-sm" style="width: 100%; max-width: 400px;">
        <h3 class="text-center mb-4">Login</h3>
        <form action="/login" method="POST">
            <div class="mb-3">
                <label for="email" class="form-label">Email address</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Sign In</button>
        </form>
        <div class="mt-3 text-center">
            <small>Demo Accounts:<br>alice@example.com (Manager)<br>bob@example.com (Member)</small>
        </div>
    </div>

</body>
</html>
