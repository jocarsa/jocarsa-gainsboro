<?php
// Mini CMS with PHP and SQLite3
require_once 'config.php';

// Initialize SQLite3 database
$db = new SQLite3($dbPath);

// Create necessary tables if they don't exist
$db->exec("CREATE TABLE IF NOT EXISTS pages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT UNIQUE NOT NULL,
    content TEXT NOT NULL
)");

$db->exec("CREATE TABLE IF NOT EXISTS blog (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    content TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$db->exec("CREATE TABLE IF NOT EXISTS config (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    key TEXT UNIQUE NOT NULL,
    value TEXT NOT NULL
)");

// Insert default config values if they don't exist
$db->exec("
    INSERT OR IGNORE INTO config (key, value) VALUES
        ('title', 'jocarsa | gainsboro'),
        ('logo', 'https://jocarsa.com/static/logo/jocarsa%20%7C%20gainsboro.svg'),
        ('meta_description', 'Default meta description'),
        ('meta_tags', 'default, tags'),
        ('meta_author', 'Jose Vicente Carratala'),
        ('active_theme', 'gainsboro')
");

// ---------------------------------------------------------------------
// Fetch config values
// ---------------------------------------------------------------------
$config = [];
$configResult = $db->query("SELECT key, value FROM config");
while ($row = $configResult->fetchArray(SQLITE3_ASSOC)) {
    $config[$row['key']] = $row['value'];
}

$title           = htmlspecialchars($config['title'] ?? 'Default Title');
$logo            = htmlspecialchars($config['logo'] ?? 'default-logo.svg');
$metaDescription = htmlspecialchars($config['meta_description'] ?? 'Default description');
$metaTags        = htmlspecialchars($config['meta_tags'] ?? 'default, tags');
$metaAuthor      = htmlspecialchars($config['meta_author'] ?? 'Default Author');

// ---------------------------------------------------------------------
// Dynamically detect all CSS files in the `css` folder
// ---------------------------------------------------------------------
/**
 * If your file is in the same folder as `css`, use ./css/*.css
 * If your structure is different, adjust accordingly. 
 */
$themeFiles = glob(__DIR__ . '/css/*.css'); 
$availableThemes = [];

if ($themeFiles !== false) {
    foreach ($themeFiles as $filePath) {
        // Extract the filename without extension, e.g. "gainsboro" from "gainsboro.css"
        $filename = pathinfo($filePath, PATHINFO_FILENAME);
        $availableThemes[] = $filename;
    }
}

// Determine the active theme from the database config
$activeTheme = $config['active_theme'] ?? 'gainsboro';

// If the stored active theme doesn't exist in the available themes, use the first found
if (!in_array($activeTheme, $availableThemes) && count($availableThemes) > 0) {
    $activeTheme = $availableThemes[0];
}

// ---------------------------------------------------------------------
// Helper function to render the final HTML
// ---------------------------------------------------------------------
function render(
    $content,
    $menu,
    $theme,
    $title,
    $logo,
    $metaDescription,
    $metaTags,
    $metaAuthor
) {
    echo "<!DOCTYPE html>\n";
    echo "<html lang=\"en\">\n";
    echo "    <head>\n";
    echo "        <meta charset=\"UTF-8\">\n";
    echo "        <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
    echo "        <title>$title</title>\n";
    echo "        <meta name=\"description\" content=\"$metaDescription\">\n";
    echo "        <meta name=\"keywords\" content=\"$metaTags\">\n";
    echo "        <meta name=\"author\" content=\"$metaAuthor\">\n";
    echo "        <link rel=\"stylesheet\" href=\"css/$theme.css\">\n";
    echo "        <link rel=\"icon\" type=\"image/svg+xml\" href=\"$logo\">\n";
    echo "    </head>\n";
    echo "    <body>\n";
    echo "        <header>\n";
    echo "            <h1>\n";
    echo "                <img src=\"$logo\" alt=\"Site Logo\"> $title\n";
    echo "            </h1>\n";
    echo "        </header>\n";
    echo "        <nav>\n";
    echo "            $menu\n";
    echo "        </nav>\n";
    echo "        <main>\n";
    echo "            $content\n";
    echo "        </main>\n";
    echo "        <footer>\n";
    echo "            &copy; " . date('Y') . " <img src=\"$logo\" alt=\"Site Logo\"> $title\n";
    echo "        </footer>\n";
    echo "    </body>\n";
    echo "</html>\n";
}

// ---------------------------------------------------------------------
// Build the menu
// ---------------------------------------------------------------------
$menu = "<a href='?page=inicio'>Inicio</a> | <a href='?page=blog'>Blog</a>";
$result = $db->query("SELECT title FROM pages ORDER BY title ASC");
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $menu .= " | <a href='?page=" . urlencode($row['title']) . "'>" 
          . htmlspecialchars($row['title']) . "</a>";
}

// ---------------------------------------------------------------------
// Handle requests
// ---------------------------------------------------------------------
$page = $_GET['page'] ?? 'inicio';

if ($page === 'blog') {
    // Display blog entries
    $result = $db->query("SELECT title, content, created_at FROM blog ORDER BY created_at DESC");
    $blogContent = "<h2>Blog</h2>\n";
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $blogContent .= "<article>\n";
        $blogContent .= "    <h3>" . htmlspecialchars($row['title']) . "</h3>\n";
        $blogContent .= "    <time>" . htmlspecialchars($row['created_at']) . "</time>\n";
        // Directly output the content (contains HTML)
        $blogContent .= "    <div>" . $row['content'] . "</div>\n";
        $blogContent .= "</article>\n<hr>\n";
    }
    render($blogContent, $menu, $activeTheme, $title, $logo, $metaDescription, $metaTags, $metaAuthor);
} else {
    // Display a specific page
    $stmt = $db->prepare("SELECT content FROM pages WHERE title = :title");
    $stmt->bindValue(':title', $page, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);

    if ($row) {
        $pageContent = "<h2>" . htmlspecialchars($page) . "</h2>\n"
                     . "<div>" . $row['content'] . "</div>\n";
        render($pageContent, $menu, $activeTheme, $title, $logo, $metaDescription, $metaTags, $metaAuthor);
    } else {
        render("<h2>Page Not Found</h2>", $menu, $activeTheme, $title, $logo, $metaDescription, $metaTags, $metaAuthor);
    }
}
?>

