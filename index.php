<?php
require_once 'config.php';

// Initialize SQLite3 database
$db = new SQLite3($dbPath);

// Ensure all necessary tables exist
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

$db->exec("CREATE TABLE IF NOT EXISTS heroes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    page_slug TEXT UNIQUE NOT NULL,
    title TEXT NOT NULL,
    subtitle TEXT,
    background_image TEXT
)");

$db->exec("CREATE TABLE IF NOT EXISTS social_media (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    category TEXT NOT NULL,
    name TEXT NOT NULL,
    url TEXT NOT NULL,
    logo TEXT NOT NULL
)");

// Insert default config values if not exists
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
        $filename = pathinfo($filePath, PATHINFO_FILENAME);
        $availableThemes[] = $filename;
    }
}

// Determine the active theme
$activeTheme = $config['active_theme'] ?? 'gainsboro';
if (!in_array($activeTheme, $availableThemes) && count($availableThemes) > 0) {
    $activeTheme = $availableThemes[0];
}

// ---------------------------------------------------------------------
// Function to fetch hero for a given slug
// ---------------------------------------------------------------------
function fetchHeroSection($db, $slug) {
    $stmt = $db->prepare("SELECT * FROM heroes WHERE page_slug = :slug");
    $stmt->bindValue(':slug', $slug, SQLITE3_TEXT);
    $res = $stmt->execute();
    $hero = $res->fetchArray(SQLITE3_ASSOC);

    if (!$hero) {
        // No hero found
        return '';
    }

    $title    = htmlspecialchars($hero['title']);
    $subtitle = htmlspecialchars($hero['subtitle']);
    $bgImage  = htmlspecialchars($hero['background_image']);

    // Return the hero HTML
    // NOTE: We'll style `.hero` to be full-width, placed after nav, before main
    return "
    <section class='hero' style='background-image: url(\"$bgImage\");'>
        <div class='hero-content'>
            <h2>$title</h2>
            <p>$subtitle</p>
        </div>
    </section>
    ";
}

// ---------------------------------------------------------------------
// Helper function to render final HTML
// Adds $hero after <nav>, before <main>, full width
// ---------------------------------------------------------------------
function render(
    $hero,
    $content,
    $menu,
    $theme,
    $title,
    $logo,
    $footerImage,
    $metaDescription,
    $metaTags,
    $metaAuthor,
    $analyticsUser,
    $socialMediaLinks
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
    echo "                <a href='?page=inicio'>\n";
    echo "                    <img src=\"$logo\" alt=\"Site Logo\"> $title\n";
    echo "                </a>\n";
    echo "            </h1>\n";
    echo "        </header>\n";
    echo "        <nav>\n";
    echo "            $menu\n";
    echo "        </nav>\n";

    // Display social media links in the navigation menu
    if (!empty($socialMediaLinks)) {
        echo "<div class='social-media-nav'>\n";
        foreach ($socialMediaLinks as $link) {
            echo "<a href='" . htmlspecialchars($link['url']) . "' target='_blank'>
                    <img src='" . htmlspecialchars($link['logo']) . "' alt='" . htmlspecialchars($link['name']) . "'>
                  </a>\n";
        }
        echo "</div>\n";
    }

    // Place hero here (if any)
    if (!empty($hero)) {
        echo $hero;
    }

    // main container remains boxed
    echo "        <main>\n";
    echo "            $content\n";
    echo "        </main>\n";

    echo "        <footer>\n";
    echo "            &copy; " . date('Y') . " <img src=\"$footerImage\" alt=\"Footer Logo\"> $title\n";
    echo "        </footer>\n";

    // Display social media links in the footer
    if (!empty($socialMediaLinks)) {
        echo "<div class='social-media-footer'>\n";
        foreach ($socialMediaLinks as $link) {
            echo "<a href='" . htmlspecialchars($link['url']) . "' target='_blank'>
                    <img src='" . htmlspecialchars($link['logo']) . "' alt='" . htmlspecialchars($link['name']) . "'>
                  </a>\n";
        }
        echo "</div>\n";
    }

    // Insert the analytics script with user param
    echo "        <script src=\"https://ghostwhite.jocarsa.com/analytics.js?user=$analyticsUser\"></script>\n";
    echo "    </body>\n";
    echo "</html>\n";
}

// ---------------------------------------------------------------------
// Build the main menu
// ---------------------------------------------------------------------
$menu = "<a href='?page=blog'>Blog</a>";
$result = $db->query("SELECT title FROM pages ORDER BY title ASC");
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $menu .= " | <a href='?page=" . urlencode($row['title']) . "'>" . htmlspecialchars($row['title']) . "</a>";
}
$menu .= " | <a href='?page=contacto'>Contacto</a>";

// ---------------------------------------------------------------------
// Handle requests
// ---------------------------------------------------------------------
$page = $_GET['page'] ?? 'inicio';

// Fetch social media links from the database
$socialMediaResult = $db->query("SELECT name, url, logo FROM social_media");
$socialMediaLinks = [];
while ($row = $socialMediaResult->fetchArray(SQLITE3_ASSOC)) {
    $socialMediaLinks[] = $row;
}

if ($page === 'blog') {
    // BLOG
    $result = $db->query("SELECT title, content, created_at FROM blog ORDER BY created_at DESC");
    $blogContent = "<h2>Blog</h2>\n";
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $blogContent .= "<article>\n";
        $blogContent .= "    <h3>" . htmlspecialchars($row['title']) . "</h3>\n";
        $blogContent .= "    <time>" . htmlspecialchars($row['created_at']) . "</time>\n";
        $blogContent .= "    <div>" . $row['content'] . "</div>\n"; // HTML allowed
        $blogContent .= "</article>\n<hr>\n";
    }
    // fetch hero for "blog"
    $heroSection = fetchHeroSection($db, 'blog');

    render($heroSection, $blogContent, $menu, $activeTheme, $title, $logo, $footerImage, $metaDescription, $metaTags, $metaAuthor, $analyticsUser, $socialMediaLinks);

} elseif ($page === 'contacto') {
    // CONTACT
    $contactContent = "<h2>Contacto</h2>";

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name    = trim($_POST['name'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if ($name && $email && $subject && $message) {
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

    // fetch hero for "contacto"
    $heroSection = fetchHeroSection($db, 'contacto');

    render($heroSection, $contactContent, $menu, $activeTheme, $title, $logo, $footerImage, $metaDescription, $metaTags, $metaAuthor, $analyticsUser, $socialMediaLinks);

} else {
    // SPECIFIC PAGE
    $stmt = $db->prepare("SELECT content FROM pages WHERE title = :title");
    $stmt->bindValue(':title', $page, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);

    if ($row) {
        $pageContent = "<h2>" . htmlspecialchars($page) . "</h2>\n" . "<div>" . $row['content'] . "</div>\n";
        // fetch hero for the given $page
        $heroSection = fetchHeroSection($db, $page);

        render($heroSection, $pageContent, $menu, $activeTheme, $title, $logo, $footerImage, $metaDescription, $metaTags, $metaAuthor, $analyticsUser, $socialMediaLinks);
    } else {
        render('', "<h2>Page Not Found</h2>", $menu, $activeTheme, $title, $logo, $footerImage, $metaDescription, $metaTags, $metaAuthor, $analyticsUser, $socialMediaLinks);
    }
}

// ---------------------------------------------------------------------
// Generate sitemap.xml on every load
// ---------------------------------------------------------------------
function generateSitemap($db) {
    // Determine the protocol and domain for absolute URLs
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $domain = $protocol . $_SERVER['HTTP_HOST'];

    $urls = [];

    // Homepage (assuming 'inicio' is the homepage)
    $urls[] = ['loc' => $domain . '/?page=inicio', 'lastmod' => date('Y-m-d')];

    // Blog and Contact pages
    $urls[] = ['loc' => $domain . '/?page=blog', 'lastmod' => date('Y-m-d')];
    $urls[] = ['loc' => $domain . '/?page=contacto', 'lastmod' => date('Y-m-d')];

    // Static pages from the pages table
    $result = $db->query("SELECT title FROM pages");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $pageTitle = urlencode($row['title']);
        $urls[] = ['loc' => $domain . '/?page=' . $pageTitle, 'lastmod' => date('Y-m-d')];
    }

    // Individual blog posts (assuming they can be accessed via ?page=blog&post=ID)
    $result = $db->query("SELECT id, created_at FROM blog");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $urls[] = [
            'loc' => $domain . '/?page=blog&post=' . $row['id'],
            'lastmod' => date('Y-m-d', strtotime($row['created_at']))
        ];
    }

    // Build XML content using DOMDocument
    $xml = new DOMDocument('1.0', 'UTF-8');
    $xml->formatOutput = true;
    $urlset = $xml->createElement('urlset');
    $urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

    foreach ($urls as $entry) {
        $url = $xml->createElement('url');
        $loc = $xml->createElement('loc', htmlspecialchars($entry['loc']));
        $url->appendChild($loc);
        $lastmod = $xml->createElement('lastmod', $entry['lastmod']);
        $url->appendChild($lastmod);
        $urlset->appendChild($url);
    }

    $xml->appendChild($urlset);
    // Save the sitemap.xml file in the root directory
    $xml->save('sitemap.xml');
}

// Call the sitemap generator
generateSitemap($db);
?>

