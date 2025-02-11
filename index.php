<?php
// Mini CMS with PHP and SQLite3
require_once 'config.php';

// Initialize SQLite3 database
$db = new SQLite3($dbPath);

// Create necessary tables if they don't exist (same as in admin)
$db->exec("CREATE TABLE IF NOT EXISTS pages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT UNIQUE NOT NULL,
    content TEXT NOT NULL
);");

$db->exec("CREATE TABLE IF NOT EXISTS blog (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    content TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);");

$db->exec("CREATE TABLE IF NOT EXISTS themes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT UNIQUE NOT NULL,
    active BOOLEAN DEFAULT 0
);");

$db->exec("INSERT OR IGNORE INTO themes (name, active) VALUES ('gainsboro', 1);");

$db->exec("CREATE TABLE IF NOT EXISTS config (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    key TEXT UNIQUE NOT NULL,
    value TEXT NOT NULL
);");

// Insert default config values if not exist
$db->exec("INSERT OR IGNORE INTO config (key, value) VALUES
    ('title', 'jocarsa | gainsboro'),
    ('logo', 'https://jocarsa.com/static/logo/jocarsa%20%7C%20gainsboro.svg'),
    ('meta_description', 'Default meta description'),
    ('meta_tags', 'default, tags'),
    ('meta_author', 'Jose Vicente Carratala');");

// Fetch active theme
$themeResult = $db->querySingle("SELECT name FROM themes WHERE active = 1 LIMIT 1;");
$activeTheme = $themeResult ? $themeResult : 'gainsboro';

// Fetch config values
$config = [];
$configResult = $db->query("SELECT key, value FROM config;");
while ($row = $configResult->fetchArray(SQLITE3_ASSOC)) {
    $config[$row['key']] = $row['value'];
}

$title = htmlspecialchars($config['title'] ?? 'Default Title');
$logo = htmlspecialchars($config['logo'] ?? 'default-logo.svg');
$metaDescription = htmlspecialchars($config['meta_description'] ?? 'Default description');
$metaTags = htmlspecialchars($config['meta_tags'] ?? 'default, tags');
$metaAuthor = htmlspecialchars($config['meta_author'] ?? 'Default Author');

// Helper function to render the final HTML
function render($content, $menu, $theme, $title, $logo, $metaDescription, $metaTags, $metaAuthor) {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>$title</title>
        <meta name='description' content='$metaDescription'>
        <meta name='keywords' content='$metaTags'>
        <meta name='author' content='$metaAuthor'>
        <link rel='stylesheet' href='css/$theme.css'>
        <link rel='icon' type='image/svg+xml' href='$logo' />
    </head>
    <body>
        <h1><img src='$logo'>$title</h1>
        <nav>$menu</nav>
        <div id='contenedor'>$content</div>
        <footer>(c)" . date('Y') . " <img src='$logo'>$title</footer>
    </body>
    </html>";
}

// Build the menu
$menu = "<a href='?page=inicio'>Inicio</a> | <a href='?page=blog'>Blog</a>";
$result = $db->query("SELECT title FROM pages ORDER BY title ASC;");
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $menu .= " | <a href='?page=" . urlencode($row['title']) . "'>" . htmlspecialchars($row['title']) . "</a>";
}

// Handle requests
$page = $_GET['page'] ?? 'inicio';

if ($page === 'blog') {
    // Display blog entries
    $result = $db->query("SELECT title, content, created_at FROM blog ORDER BY created_at DESC;");
    $blogContent = "<h1>Blog</h1>";
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $blogContent .= "<article>
            <h2>" . htmlspecialchars($row['title']) . "</h2>
            <small>" . htmlspecialchars($row['created_at']) . "</small>
            <div>" . $row['content'] . "</div>
        </article><hr>";
    }
    render($blogContent, $menu, $activeTheme, $title, $logo, $metaDescription, $metaTags, $metaAuthor);
} else {
    // Display a page
    $stmt = $db->prepare("SELECT content FROM pages WHERE title = :title;");
    $stmt->bindValue(':title', $page, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);

    if ($row) {
        // Directly output the HTML content
        $pageContent = "<h2>" . htmlspecialchars($page) . "</h2><div>" . $row['content'] . "</div>";
        render($pageContent, $menu, $activeTheme, $title, $logo, $metaDescription, $metaTags, $metaAuthor);
    } else {
        render("<h2>Page Not Found</h2>", $menu, $activeTheme, $title, $logo, $metaDescription, $metaTags, $metaAuthor);
    }
}
?>
<link rel="stylesheet" href="https://jocarsa.github.io/jocarsa-lightslateblue/jocarsa | lightslateblue.css">
