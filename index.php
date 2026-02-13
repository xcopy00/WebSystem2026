<?php
/**
 * Main Entry Point - Serves the frontend with routing
 */

// Enable error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get the requested path
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

// Route mapping
$routes = [
    '/' => 'public/index.html',
    '/index' => 'public/index.html',
    '/home' => 'public/index.html',
    '/login' => 'public/login.html',
    '/signin' => 'public/login.html',
    '/register' => 'public/register.html',
    '/signup' => 'public/register.html',
];

// Check if route exists
if (isset($routes[$path])) {
    $file = __DIR__ . '/' . $routes[$path];
    if (file_exists($file)) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($file);
        exit;
    }
}

// API routes - forward to api.php
if (strpos($path, '/api/') === 0 || $path === '/api.php') {
    // Set SCRIPT_NAME to api.php so api.php works correctly
    $_SERVER['SCRIPT_NAME'] = '/api.php';
    include __DIR__ . '/api.php';
    exit;
}

// 404 - Page not found
header('Content-Type: text/html');
echo '<!DOCTYPE html>
<html>
<head>
    <title>404 - Page Not Found</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: "Segoe UI", system-ui, sans-serif;
            background: #0a0a0f;
            color: #fff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            text-align: center;
            max-width: 400px;
        }
        h1 {
            font-size: 120px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 20px;
        }
        h2 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        p {
            color: rgba(255,255,255,0.5);
            margin-bottom: 30px;
        }
        a {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            padding: 12px 30px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        a:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>404</h1>
        <h2>Page Not Found</h2>
        <p>The page you are looking for does not exist.</p>
        <a href="/">Go to Home</a>
    </div>
</body>
</html>';
