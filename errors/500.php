<?php
http_response_code(500);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Error - Rosabella</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <main style="min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem;">
        <div style="max-width: 520px; text-align: center;">
            <h1 style="font-size: 3rem; font-weight: 700; margin-bottom: .75rem;">500</h1>
            <h2 style="font-size: 1.5rem; font-weight: 600; margin-bottom: .75rem;">Something went wrong</h2>
            <p style="color: var(--color-text-light); margin-bottom: 1.25rem;">
                The server hit an unexpected error. Please try again in a few minutes.
            </p>
            <a href="<?= BASE_URL ?>/" class="btn btn-primary">Back to Homepage</a>
        </div>
    </main>
</body>
</html>



