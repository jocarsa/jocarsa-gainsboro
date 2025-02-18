<?php
// Mini CMS with PHP and SQLite3
require_once 'config.php';

// Initialize SQLite3 database
$db = new SQLite3($dbPath);

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

$db->exec("CREATE TABLE IF NOT EXISTS contact (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL,
    subject TEXT NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Insert default config values if they don't exist
$db->exec("
    INSERT OR IGNORE INTO config (key, value) VALUES
        ('title', 'jocarsa | gainsboro'),
        ('logo', 'https://jocarsa.com/static/logo/jocarsa%20%7C%20gainsboro.svg'),
        ('meta_description', 'Default meta description'),
        ('meta_tags', 'default, tags'),
        ('meta_author', 'Jose Vicente Carratala'),
        ('active_theme', 'gainsboro'),
        ('footer_image', 'https://jocarsa.com/static/logo/footer-logo.svg'),
        ('analytics_user', 'defaultUser')
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
$footerImage     = htmlspecialchars($config['footer_image'] ?? 'default-footer-logo.svg');
$metaDescription = htmlspecialchars($config['meta_description'] ?? 'Default description');
$metaTags        = htmlspecialchars($config['meta_tags'] ?? 'default, tags');
$metaAuthor      = htmlspecialchars($config['meta_author'] ?? 'Default Author');
$analyticsUser   = htmlspecialchars($config['analytics_user'] ?? 'defaultUser');

// ---------------------------------------------------------------------
// Dynamically detect all CSS files in the css folder
// ---------------------------------------------------------------------
$themeFiles = glob(__DIR__ . '/css/*.css');
$availableThemes = [];

if ($themeFiles !== false) {
    foreach ($themeFiles as $filePath) {
        // Extract the filename without extension
        $filename = pathinfo($filePath, PATHINFO_FILENAME);
        $availableThemes[] = $filename;
    }
}

// Determine the active theme from the database config
$activeTheme = $config['active_theme'] ?? 'gainsboro';
if (!in_array($activeTheme, $availableThemes) && count($availableThemes) > 0) {
    $activeTheme = $availableThemes[0];
}

// ---------------------------------------------------------------------
// Helper function to render the final HTML, now with analyticsUser
// ---------------------------------------------------------------------
function render(
    $content,
    $menu,
    $theme,
    $title,
    $logo,
    $footerImage,
    $metaDescription,
    $metaTags,
    $metaAuthor,
    $analyticsUser
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
    echo "                <a href='?page=inicio'>";
    echo "                    <img src=\"$logo\" alt=\"Site Logo\"> $title\n";
    echo "                </a>";
    echo "            </h1>\n";
    echo "        </header>\n";
    echo "        <nav>\n";
    echo "            $menu\n";
    echo "        </nav>\n";
    echo "        <main>\n";
    echo "            $content\n";
    echo "        </main>\n";
    echo "        <footer>\n";
    echo "            &copy; " . date('Y') . " <img src=\"$footerImage\" alt=\"Footer Logo\"> $title\n";
    echo "        </footer>\n";
    // Insert the analytics script with the user parameter from configuration
    echo "        <script src=\"https://ghostwhite.jocarsa.com/analytics.js?user=$analyticsUser\"></script>\n";
    echo "    </body>\n";
    echo "</html>\n";
}

// ---------------------------------------------------------------------
// Build the menu
// ---------------------------------------------------------------------
$menu = "<a href='?page=blog'>Blog</a>";
// Add dynamic pages to the menu
$result = $db->query("SELECT title FROM pages ORDER BY title ASC");
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $menu .= " | <a href='?page=" . urlencode($row['title']) . "'>" . htmlspecialchars($row['title']) . "</a>";
}

// Add a static link to "Contacto" in the main menu
$menu .= " | <a href='?page=contacto'>Contacto</a>";

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
        $blogContent .= "    <div>" . $row['content'] . "</div>\n"; // HTML allowed
        $blogContent .= "</article>\n<hr>\n";
    }
    render($blogContent, $menu, $activeTheme, $title, $logo, $footerImage, $metaDescription, $metaTags, $metaAuthor, $analyticsUser);

} elseif ($page === 'contacto') {
    // Contact form page
    $contactContent = "<h2>Contacto</h2>";

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Collect and sanitize form data
        $name    = trim($_POST['name'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if ($name && $email && $subject && $message) {
            // Insert into contact table
            $stmt = $db->prepare("INSERT INTO contact (name, email, subject, message)
                                  VALUES (:n, :e, :s, :m)");
            $stmt->bindValue(':n', $name, SQLITE3_TEXT);
            $stmt->bindValue(':e', $email, SQLITE3_TEXT);
            $stmt->bindValue(':s', $subject, SQLITE3_TEXT);
            $stmt->bindValue(':m', $message, SQLITE3_TEXT);
            $stmt->execute();

            $contactContent .= "<p>¡Gracias por tu mensaje, $name! Te responderemos pronto.</p>";
        } else {
            $contactContent .= "<p style='color:red;'>Por favor, rellena todos los campos.</p>";
        }
    }

    // Display form
    $contactContent .= "
    <form method='post'>
        <label for='name'>Nombre Completo:</label><br>
        <input type='text' id='name' name='name' required><br><br>

        <label for='email'>Correo Electrónico:</label><br>
        <input type='email' id='email' name='email' required><br><br>

        <label for='subject'>Asunto:</label><br>
        <input type='text' id='subject' name='subject' required><br><br>

        <label for='message'>Mensaje:</label><br>
        <textarea id='message' name='message' rows='5' required></textarea><br><br>

        <button type='submit'>Enviar</button>
    </form>";

    render($contactContent, $menu, $activeTheme, $title, $logo, $footerImage, $metaDescription, $metaTags, $metaAuthor, $analyticsUser);

} else {
    // Display a specific page
    $stmt = $db->prepare("SELECT content FROM pages WHERE title = :title");
    $stmt->bindValue(':title', $page, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);

    if ($row) {
        $pageContent = "<h2>" . htmlspecialchars($page) . "</h2>\n" . "<div>" . $row['content'] . "</div>\n";
        render($pageContent, $menu, $activeTheme, $title, $logo, $footerImage, $metaDescription, $metaTags, $metaAuthor, $analyticsUser);
    } else {
        render("<h2>Page Not Found</h2>", $menu, $activeTheme, $title, $logo, $footerImage, $metaDescription, $metaTags, $metaAuthor, $analyticsUser);
    }
}
?>

