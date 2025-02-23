<?php
session_start();
require_once 'config.php';

// Demo credentials (change these as needed)
define('ADMIN_USER', 'jocarsa');
define('ADMIN_PASS', 'jocarsa');

// Connect to the database
$db = new SQLite3($dbPath);

// ---------------------------------------------------------------------
// Create all needed tables if not exist
// ---------------------------------------------------------------------
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

$db->exec("CREATE TABLE IF NOT EXISTS media (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    filename TEXT NOT NULL,
    filepath TEXT NOT NULL,
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

// Insert default config values if they don't exist, including the new analytics_user key
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

// Insert default social media links only if they don't exist
$defaultSocialMedia = [
    ['Generalistas', 'Facebook', 'facebook.png'],
    ['Generalistas', 'Instagram', 'instagram.png'],
    ['Generalistas', 'Twitter (X)', 'twitter.png'],
    ['Generalistas', 'TikTok', 'tiktok.png'],
    ['Generalistas', 'Snapchat', 'snapchat.png'],
    ['Profesionales y negocios', 'LinkedIn', 'linkedin.png'],
    ['Profesionales y negocios', 'Pinterest', 'pinterest.png'],
    ['Profesionales y negocios', 'GitHub', 'github.png'],
    ['Mensajería instantánea', 'WhatsApp', 'whatsapp.png'],
    ['Mensajería instantánea', 'Telegram', 'telegram.png'],
    ['Mensajería instantánea', 'Discord', 'discord.png'],
    ['Streaming y video', 'YouTube', 'youtube.png'],
    ['Streaming y video', 'Twitch', 'twitch.png'],
    ['Redes sociales emergentes o de nicho', 'Threads', 'threads.png'],
    ['Redes sociales emergentes o de nicho', 'Mastodon', 'mastodon.png'],
    ['Redes sociales emergentes o de nicho', 'BeReal', 'bereal.png']
];

foreach ($defaultSocialMedia as $item) {
    list($category, $name, $logo) = $item;
    $existing = $db->querySingle("SELECT COUNT(*) FROM social_media WHERE name = '$name'");
    if ($existing == 0) {
        $db->exec("
            INSERT INTO social_media (category, name, url, logo)
            VALUES ('$category', '$name', '', '$logo')
        ");
    }
}

// -----------------------------------------------------------
// Helper Functions
// -----------------------------------------------------------
function isLoggedIn() {
    return (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: admin.php');
        exit();
    }
}

function renderAdmin($content) {
    echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <title>Panel de Administración</title>
    <link rel='stylesheet' href='admin.css'>
</head>
<body>
<div id='admin-container'>
    <div id='admin-sidebar'>
        <nav>
            <a href='admin.php?action=dashboard'>Inicio</a>
            <a href='admin.php?action=list_pages'>Páginas</a>
            <a href='admin.php?action=list_blog'>Blog</a>
            <a href='admin.php?action=list_themes'>Temas</a>
            <a href='admin.php?action=edit_theme'>Editar Tema</a>
            <a href='admin.php?action=list_config'>Configuración</a>
            <a href='admin.php?action=list_media'>Biblioteca</a>
            <a href='admin.php?action=list_contact'>Contacto</a>
            <a href='admin.php?action=list_heroes'>Heroes</a>
            <a href='admin.php?action=list_social_media'>Redes Sociales</a>
            <a href='admin.php?action=logout'>Salir</a>
        </nav>
    </div>
    <div id='admin-content'>
        <div id='admin-header'>
            <h1>Panel de Administración</h1>
        </div>
        <div class='admin-section'>
            $content
        </div>
    </div>
</div>
<footer>
    &copy; " . date('Y') . " jocarsa Admin Panel
</footer>
</body>
</html>";
}

function getAllMedia($db) {
    $items = [];
    $res = $db->query("SELECT * FROM media ORDER BY id DESC");
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $items[] = $row;
    }
    return $items;
}

function getAvailableThemes() {
    $themeFiles = glob(__DIR__ . '/css/*.css');
    $themes = [];
    if ($themeFiles !== false) {
        foreach ($themeFiles as $filePath) {
            $themes[] = pathinfo($filePath, PATHINFO_FILENAME);
        }
    }
    return $themes;
}

function setActiveTheme($db, $themeName) {
    $st = $db->prepare("UPDATE config SET value = :val WHERE key = 'active_theme'");
    $st->bindValue(':val', $themeName, SQLITE3_TEXT);
    $st->execute();
}

// -----------------------------------------------------------
// Routing
// -----------------------------------------------------------
$action = $_GET['action'] ?? 'login';
$message = '';

// -----------------------------------------------------------
// LOGOUT
// -----------------------------------------------------------
if ($action === 'logout') {
    session_destroy();
    header('Location: admin.php');
    exit();
}

// -----------------------------------------------------------
// PROCESS LOGIN
// -----------------------------------------------------------
if ($action === 'do_login') {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';
    if ($user === ADMIN_USER && $pass === ADMIN_PASS) {
        $_SESSION['logged_in'] = true;
        header('Location: admin.php?action=dashboard');
        exit();
    } else {
        $message = "<p class='danger'>Credenciales inválidas</p>";
        $action = 'login';
    }
}

// If user not logged in, force login (except action=login)
if (!isLoggedIn() && $action !== 'login') {
    header('Location: admin.php?action=login');
    exit();
}

// -----------------------------------------------------------
// SWITCH ACTIONS
// -----------------------------------------------------------
switch ($action) {

    // -----------------------------------------------------------
    // LOGIN PAGE
    // -----------------------------------------------------------
    case 'login':
        $html = "<div class='admin-form'>
                    <h2>Acceso al Panel</h2>
                    $message
                    <form method='post' action='admin.php?action=do_login'>
                        <label>Usuario:</label>
                        <input type='text' name='username' required>
                        <label>Contraseña:</label>
                        <input type='password' name='password' required>
                        <button type='submit'>Acceder</button>
                    </form>
                 </div>";
        renderAdmin($html);
        break;

    // -----------------------------------------------------------
    // DASHBOARD
    // -----------------------------------------------------------
    case 'dashboard':
        $html = "<h2>Bienvenido al panel de administración.</h2>";
        renderAdmin($html);
        break;

    // -----------------------------------------------------------
    // CONTACT: LIST
    // -----------------------------------------------------------
    case 'list_contact':
        $res = $db->query("SELECT * FROM contact ORDER BY id DESC");
        $html = "<h2>Contacto</h2>
                 <p><a href='admin.php?action=view_contact'>Ver Mensajes</a></p>
                 <table class='admin-table'>
                   <tr>
                     <th>ID</th>
                     <th>Nombre</th>
                     <th>Correo Electrónico</th>
                     <th>Asunto</th>
                     <th>Fecha</th>
                     <th>Acciones</th>
                   </tr>";
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $html .= "<tr>
                        <td>{$row['id']}</td>
                        <td>" . htmlspecialchars($row['name']) . "</td>
                        <td>" . htmlspecialchars($row['email']) . "</td>
                        <td>" . htmlspecialchars($row['subject']) . "</td>
                        <td>" . htmlspecialchars($row['created_at']) . "</td>
                        <td><a href='admin.php?action=view_contact&id={$row['id']}'>Ver</a></td>
                      </tr>";
        }
        $html .= "</table>";
        renderAdmin($html);
        break;

    // -----------------------------------------------------------
    // CONTACT: VIEW A MESSAGE
    // -----------------------------------------------------------
    case 'view_contact':
        $id = $_GET['id'] ?? 0;
        $stmt = $db->prepare("SELECT * FROM contact WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $res = $stmt->execute();
        $messageData = $res->fetchArray(SQLITE3_ASSOC);
        if (!$messageData) {
            header('Location: admin.php?action=list_contact');
            exit();
        }
        $html = "<h2>Ver Mensaje</h2>
                 <table class='admin-table'>
                    <tr><th>ID</th><td>{$messageData['id']}</td></tr>
                    <tr><th>Nombre</th><td>" . htmlspecialchars($messageData['name']) . "</td></tr>
                    <tr><th>Correo Electrónico</th><td>" . htmlspecialchars($messageData['email']) . "</td></tr>
                    <tr><th>Asunto</th><td>" . htmlspecialchars($messageData['subject']) . "</td></tr>
                    <tr><th>Mensaje</th><td>" . nl2br(htmlspecialchars($messageData['message'])) . "</td></tr>
                    <tr><th>Creado</th><td>{$messageData['created_at']}</td></tr>
                  </table>";
        renderAdmin($html);
        break;

    // -----------------------------------------------------------
    // MEDIA LIBRARY: LIST
    // -----------------------------------------------------------
    case 'list_media':
        $mediaItems = getAllMedia($db);
        $html = "<h2>Biblioteca</h2>
                 <p><a href='admin.php?action=upload_media'>[+] Subir Nuevo Archivo</a></p>
                 <table class='admin-table'>
                   <tr>
                     <th>ID</th>
                     <th>Nombre de Archivo</th>
                     <th>Ruta</th>
                     <th>Fecha</th>
                     <th>Vista Previa</th>
                     <th>Acciones</th>
                   </tr>";
        foreach ($mediaItems as $m) {
            $html .= "<tr>
                        <td>{$m['id']}</td>
                        <td>" . htmlspecialchars($m['filename']) . "</td>
                        <td>" . htmlspecialchars($m['filepath']) . "</td>
                        <td>" . htmlspecialchars($m['created_at']) . "</td>
                        <td><img src='{$m['filepath']}' alt='' style='max-width:100px;'></td>
                        <td>
                           <a href='admin.php?action=delete_media&id={$m['id']}' onclick='return confirm(\"¿Eliminar?\");'>Eliminar</a>
                        </td>
                      </tr>";
        }
        $html .= "</table>";
        renderAdmin($html);
        break;

    // -----------------------------------------------------------
    // MEDIA LIBRARY: UPLOAD
    // -----------------------------------------------------------
    case 'upload_media':
        if (isset($_POST['upload'])) {
            if (!empty($_FILES['file']['name'])) {
                $fileName = $_FILES['file']['name'];
                $tmpName  = $_FILES['file']['tmp_name'];
                $targetDir = __DIR__ . '/static/';
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0777, true);
                }
                // Create unique name
                $uniqueName = time() . '-' . preg_replace('/[^a-zA-Z0-9._-]/', '', $fileName);
                $targetPath = $targetDir . $uniqueName;
                if (move_uploaded_file($tmpName, $targetPath)) {
                    // Save in DB
                    $dbFilePath = 'static/' . $uniqueName;
                    $stmt = $db->prepare("INSERT INTO media (filename, filepath) VALUES (:fn, :fp)");
                    $stmt->bindValue(':fn', $fileName, SQLITE3_TEXT);
                    $stmt->bindValue(':fp', $dbFilePath, SQLITE3_TEXT);
                    $stmt->execute();
                    header('Location: admin.php?action=list_media');
                    exit();
                } else {
                    $message = "<p class='danger'>Error al mover el archivo subido.</p>";
                }
            } else {
                $message = "<p class='danger'>No se ha seleccionado ningún archivo.</p>";
            }
        } else {
            $message = '';
        }
        $html = "<div class='admin-form'>
                    <h2>Subir Nuevo Archivo</h2>
                    $message
                    <form method='post' enctype='multipart/form-data'>
                        <label>Selecciona el archivo:</label>
                        <input type='file' name='file'>
                        <button type='submit' name='upload'>Subir</button>
                    </form>
                 </div>";
        renderAdmin($html);
        break;

    // -----------------------------------------------------------
    // MEDIA LIBRARY: DELETE
    // -----------------------------------------------------------
    case 'delete_media':
        $id = $_GET['id'] ?? null;
        if ($id) {
            $st = $db->prepare("SELECT * FROM media WHERE id = :id");
            $st->bindValue(':id', $id, SQLITE3_INTEGER);
            $res = $st->execute();
            $media = $res->fetchArray(SQLITE3_ASSOC);
            if ($media) {
                $filePath = __DIR__ . '/' . $media['filepath'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                $st2 = $db->prepare("DELETE FROM media WHERE id = :id");
                $st2->bindValue(':id', $id, SQLITE3_INTEGER);
                $st2->execute();
            }
        }
        header('Location: admin.php?action=list_media');
        exit();

    // -----------------------------------------------------------
    // PAGES: LIST
    // -----------------------------------------------------------
    case 'list_pages':
        $res = $db->query("SELECT * FROM pages ORDER BY id DESC");
        $html = "<h2>Páginas</h2>
                 <p><a href='admin.php?action=edit_page'>[+] Agregar Nueva Página</a></p>
                 <table class='admin-table'>
                    <tr><th>ID</th><th>Título</th><th>Acciones</th></tr>";
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $html .= "<tr>
                        <td>{$row['id']}</td>
                        <td>" . htmlspecialchars($row['title']) . "</td>
                        <td>
                            <a href='admin.php?action=edit_page&id={$row['id']}'>Editar</a> |
                            <a href='admin.php?action=delete_page&id={$row['id']}' onclick='return confirm(\"¿Eliminar?\");'>Eliminar</a>
                        </td>
                      </tr>";
        }
        $html .= "</table>";
        renderAdmin($html);
        break;

    // -----------------------------------------------------------
    // PAGES: EDIT / ADD
    // -----------------------------------------------------------
    case 'edit_page':
        $id = $_GET['id'] ?? null;
        $pageData = ['id' => '', 'title' => '', 'content' => ''];
        if ($id) {
            $st = $db->prepare("SELECT * FROM pages WHERE id = :id");
            $st->bindValue(':id', $id, SQLITE3_INTEGER);
            $res = $st->execute();
            $found = $res->fetchArray(SQLITE3_ASSOC);
            if ($found) {
                $pageData = $found;
            }
        }
        if (isset($_POST['save_page'])) {
            $title   = $_POST['title'] ?? '';
            $content = $_POST['content'] ?? '';
            if ($id) {
                $st = $db->prepare("UPDATE pages SET title = :title, content = :content WHERE id = :id");
                $st->bindValue(':id', $id, SQLITE3_INTEGER);
            } else {
                $st = $db->prepare("INSERT INTO pages (title, content) VALUES (:title, :content)");
            }
            $st->bindValue(':title', $title, SQLITE3_TEXT);
            $st->bindValue(':content', $content, SQLITE3_TEXT);
            $st->execute();
            header('Location: admin.php?action=list_pages');
            exit();
        }
        $html = "<div class='admin-form'>
                    <h2>" . ($id ? "Editar Página" : "Agregar Página") . "</h2>
                    <form method='post'>
                        <label>Título:</label>
                        <input type='text' name='title' value='" . htmlspecialchars($pageData['title']) . "' required>
                        <label>Contenido:</label>
                        <textarea name='content' class='jocarsa-lightslateblue' rows='10'>" . htmlspecialchars($pageData['content']) . "</textarea>
                        <button type='submit' name='save_page'>Guardar</button>
                    </form>
                 </div>";
        renderAdmin($html);
        break;

    // -----------------------------------------------------------
    // PAGES: DELETE
    // -----------------------------------------------------------
    case 'delete_page':
        $id = $_GET['id'] ?? null;
        if ($id) {
            $st = $db->prepare("DELETE FROM pages WHERE id = :id");
            $st->bindValue(':id', $id, SQLITE3_INTEGER);
            $st->execute();
        }
        header('Location: admin.php?action=list_pages');
        exit();

    // -----------------------------------------------------------
    // BLOG: LIST
    // -----------------------------------------------------------
    case 'list_blog':
        $res = $db->query("SELECT * FROM blog ORDER BY id DESC");
        $html = "<h2>Entradas del Blog</h2>
                 <p><a href='admin.php?action=edit_blog'>[+] Agregar Nueva Entrada</a></p>
                 <table class='admin-table'>
                    <tr><th>ID</th><th>Título</th><th>Fecha</th><th>Acciones</th></tr>";
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $html .= "<tr>
                        <td>{$row['id']}</td>
                        <td>" . htmlspecialchars($row['title']) . "</td>
                        <td>" . htmlspecialchars($row['created_at']) . "</td>
                        <td>
                            <a href='admin.php?action=edit_blog&id={$row['id']}'>Editar</a> |
                            <a href='admin.php?action=delete_blog&id={$row['id']}' onclick='return confirm(\"¿Eliminar?\");'>Eliminar</a>
                        </td>
                      </tr>";
        }
        $html .= "</table>";
        renderAdmin($html);
        break;

    // -----------------------------------------------------------
    // BLOG: EDIT / ADD
    // -----------------------------------------------------------
    case 'edit_blog':
        $id = $_GET['id'] ?? null;
        $blogData = ['id' => '', 'title' => '', 'content' => '', 'created_at' => ''];
        if ($id) {
            $st = $db->prepare("SELECT * FROM blog WHERE id = :id");
            $st->bindValue(':id', $id, SQLITE3_INTEGER);
            $res = $st->execute();
            $found = $res->fetchArray(SQLITE3_ASSOC);
            if ($found) {
                $blogData = $found;
            }
        }
        if (isset($_POST['save_blog'])) {
            $title   = $_POST['title'] ?? '';
            $content = $_POST['content'] ?? '';
            if ($id) {
                $st = $db->prepare("UPDATE blog SET title = :title, content = :content WHERE id = :id");
                $st->bindValue(':id', $id, SQLITE3_INTEGER);
            } else {
                $st = $db->prepare("INSERT INTO blog (title, content) VALUES (:title, :content)");
            }
            $st->bindValue(':title', $title, SQLITE3_TEXT);
            $st->bindValue(':content', $content, SQLITE3_TEXT);
            $st->execute();
            header('Location: admin.php?action=list_blog');
            exit();
        }
        $html = "<div class='admin-form'>
                    <h2>" . ($id ? "Editar Entrada del Blog" : "Agregar Entrada") . "</h2>
                    <form method='post'>
                        <label>Título:</label>
                        <input type='text' name='title' value='" . htmlspecialchars($blogData['title']) . "' required>
                        <label>Contenido:</label>
                        <textarea name='content' class='jocarsa-lightslateblue' rows='10'>" . htmlspecialchars($blogData['content']) . "</textarea>
                        <button type='submit' name='save_blog'>Guardar</button>
                    </form>
                 </div>";
        renderAdmin($html);
        break;

    // -----------------------------------------------------------
    // BLOG: DELETE
    // -----------------------------------------------------------
    case 'delete_blog':
        $id = $_GET['id'] ?? null;
        if ($id) {
            $st = $db->prepare("DELETE FROM blog WHERE id = :id");
            $st->bindValue(':id', $id, SQLITE3_INTEGER);
            $st->execute();
        }
        header('Location: admin.php?action=list_blog');
        exit();

    // -----------------------------------------------------------
    // THEMES: LIST & ACTIVATE
    // -----------------------------------------------------------
    case 'list_themes':
        $themes = getAvailableThemes();
        $activeTheme = $db->querySingle("SELECT value FROM config WHERE key='active_theme'");
        $html = "<h2>Temas</h2>";
        if (empty($themes)) {
            $html .= "<p>No se encontraron temas en la carpeta css.</p>";
            renderAdmin($html);
            break;
        }
        $html .= "<table class='admin-table'>
                    <tr><th>Nombre del Tema</th><th>Activo</th><th>Acción</th></tr>";
        foreach ($themes as $tName) {
            $isActive = ($tName === $activeTheme) ? 'Sí' : 'No';
            $html .= "<tr>
                        <td>$tName</td>
                        <td>$isActive</td>
                        <td>";
            if ($isActive === 'No') {
                $html .= "<a href='admin.php?action=activate_theme&theme=$tName'>Activar</a>";
            } else {
                $html .= "Ya Está Activo";
            }
            $html .= "</td>
                      </tr>";
        }
        $html .= "</table>";
        renderAdmin($html);
        break;

    case 'activate_theme':
        $themeToActivate = $_GET['theme'] ?? '';
        $themes = getAvailableThemes();
        if (in_array($themeToActivate, $themes)) {
            setActiveTheme($db, $themeToActivate);
        }
        header('Location: admin.php?action=list_themes');
        exit();

    // -----------------------------------------------------------
    // THEMES: EDIT
    // -----------------------------------------------------------
    case 'edit_theme':
        $themeName = $db->querySingle("SELECT value FROM config WHERE key='active_theme'");
        $themePath = __DIR__ . '/css/' . $themeName . '.css';
        if (isset($_POST['save_theme'])) {
            $cssContent = $_POST['css_content'] ?? '';
            file_put_contents($themePath, $cssContent);
            $message = "<p class='success'>Tema actualizado correctamente.</p>";
        } else {
            $message = '';
        }
        $cssContent = file_get_contents($themePath);
        $html = "<div class='admin-form'>
                    <h2>Editar Tema: $themeName</h2>
                    $message
                    <form method='post'>
                        <label>Contenido CSS:</label>
                        <textarea name='css_content' class='jocarsa-lightslateblue' rows='20'>" . htmlspecialchars($cssContent) . "</textarea>
                        <button type='submit' name='save_theme'>Guardar</button>
                    </form>
                 </div>";
        renderAdmin($html);
        break;

    // -----------------------------------------------------------
    // CONFIG: LIST & SAVE
    // -----------------------------------------------------------
    case 'list_config':
        if (isset($_POST['save_config'])) {
            foreach ($_POST['config'] as $k => $v) {
                $st = $db->prepare("UPDATE config SET value = :val WHERE key = :key");
                $st->bindValue(':val', $v, SQLITE3_TEXT);
                $st->bindValue(':key', $k, SQLITE3_TEXT);
                $st->execute();
            }
            $message = "<p class='success'>Configuración actualizada.</p>";
        } else {
            $message = '';
        }
        $configs = $db->query("SELECT * FROM config ORDER BY id ASC");
        $html = "<h2>Configuración del Sitio</h2>
                 $message
                 <form method='post'>
                 <table class='admin-table'>
                 <tr><th>Clave</th><th>Valor</th></tr>";
        while ($row = $configs->fetchArray(SQLITE3_ASSOC)) {
            $key = htmlspecialchars($row['key']);
            $val = htmlspecialchars($row['value']);
            $html .= "<tr>
                        <td>$key</td>
                        <td><input type='text' name='config[$key]' value='$val'></td>
                      </tr>";
        }
        $html .= "</table>
                  <button type='submit' name='save_config'>Guardar</button>
                  </form>";
        renderAdmin($html);
        break;

    // -----------------------------------------------------------
    // HEROES: LIST
    // -----------------------------------------------------------
    case 'list_heroes':
        requireLogin();
        $res = $db->query("SELECT * FROM heroes ORDER BY id DESC");
        $html = "<h2>Héroes (Hero Banners)</h2>
                 <p><a href='admin.php?action=edit_hero'>[+] Agregar Nuevo Hero</a></p>
                 <table class='admin-table'>
                    <tr>
                        <th>ID</th>
                        <th>Page Slug</th>
                        <th>Título</th>
                        <th>Subtítulo</th>
                        <th>Background Image</th>
                        <th>Acciones</th>
                    </tr>";
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $html .= "<tr>
                        <td>{$row['id']}</td>
                        <td>" . htmlspecialchars($row['page_slug']) . "</td>
                        <td>" . htmlspecialchars($row['title']) . "</td>
                        <td>" . htmlspecialchars($row['subtitle']) . "</td>
                        <td>" . htmlspecialchars($row['background_image']) . "</td>
                        <td>
                          <a href='admin.php?action=edit_hero&id={$row['id']}'>Editar</a> |
                          <a href='admin.php?action=delete_hero&id={$row['id']}' onclick='return confirm(\"¿Eliminar?\");'>Eliminar</a>
                        </td>
                      </tr>";
        }
        $html .= "</table>";
        renderAdmin($html);
        break;

    // -----------------------------------------------------------
    // HEROES: EDIT (Add / Update)
    // -----------------------------------------------------------
    case 'edit_hero':
        requireLogin();
        $id = $_GET['id'] ?? null;
        $heroData = [
            'id' => '',
            'page_slug' => '',
            'title' => '',
            'subtitle' => '',
            'background_image' => ''
        ];

        // Fetch existing if editing
        if ($id) {
            $stmt = $db->prepare("SELECT * FROM heroes WHERE id = :id");
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $res = $stmt->execute();
            $found = $res->fetchArray(SQLITE3_ASSOC);
            if ($found) {
                $heroData = $found;
            }
        }

        // Handle form submission
        if (isset($_POST['save_hero'])) {
            $page_slug       = trim($_POST['page_slug']);
            $title           = trim($_POST['title']);
            $subtitle        = trim($_POST['subtitle']);
            $backgroundImage = trim($_POST['background_image']);

            if ($id) {
                // Update existing
                $st = $db->prepare("UPDATE heroes
                                    SET page_slug = :page_slug,
                                        title = :title,
                                        subtitle = :subtitle,
                                        background_image = :bg
                                    WHERE id = :id");
                $st->bindValue(':id', $id, SQLITE3_INTEGER);
            } else {
                // Insert new
                $st = $db->prepare("INSERT INTO heroes (page_slug, title, subtitle, background_image)
                                    VALUES (:page_slug, :title, :subtitle, :bg)");
            }
            $st->bindValue(':page_slug', $page_slug, SQLITE3_TEXT);
            $st->bindValue(':title', $title, SQLITE3_TEXT);
            $st->bindValue(':subtitle', $subtitle, SQLITE3_TEXT);
            $st->bindValue(':bg', $backgroundImage, SQLITE3_TEXT);
            $st->execute();

            header('Location: admin.php?action=list_heroes');
            exit();
        }

        // Render form
        $html = "<div class='admin-form'>
                    <h2>" . ($id ? "Editar Hero" : "Agregar Hero") . "</h2>
                    <form method='post'>
                        <label for='page_slug'>Page Slug (ej: 'blog', 'contacto', 'inicio', o el título exacto de la página):</label>
                        <input type='text' name='page_slug' id='page_slug' value='" . htmlspecialchars($heroData['page_slug']) . "' required>

                        <label for='title'>Título:</label>
                        <input type='text' name='title' id='title' value='" . htmlspecialchars($heroData['title']) . "' required>

                        <label for='subtitle'>Subtítulo:</label>
                        <input type='text' name='subtitle' id='subtitle' value='" . htmlspecialchars($heroData['subtitle']) . "'>

                        <label for='background_image'>URL de la imagen de fondo:</label>
                        <input type='text' name='background_image' id='background_image' value='" . htmlspecialchars($heroData['background_image']) . "'>

                        <button type='submit' name='save_hero'>Guardar</button>
                    </form>
                 </div>";
        renderAdmin($html);
        break;

    // -----------------------------------------------------------
    // HEROES: DELETE
    // -----------------------------------------------------------
    case 'delete_hero':
        requireLogin();
        $id = $_GET['id'] ?? null;
        if ($id) {
            $st = $db->prepare("DELETE FROM heroes WHERE id = :id");
            $st->bindValue(':id', $id, SQLITE3_INTEGER);
            $st->execute();
        }
        header('Location: admin.php?action=list_heroes');
        exit();

    // -----------------------------------------------------------
    // SOCIAL MEDIA: LIST
    // -----------------------------------------------------------
    case 'list_social_media':
        requireLogin();
        $res = $db->query("SELECT * FROM social_media ORDER BY id DESC");
        $html = "<h2>Redes Sociales</h2>
                 <p><a href='admin.php?action=edit_social_media'>[+] Agregar Nuevo Enlace</a></p>
                 <table class='admin-table'>
                    <tr>
                        <th>ID</th>
                        <th>Categoría</th>
                        <th>Nombre</th>
                        <th>URL</th>
                        <th>Logo</th>
                        <th>Acciones</th>
                    </tr>";
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $html .= "<tr>
                        <td>{$row['id']}</td>
                        <td>" . htmlspecialchars($row['category']) . "</td>
                        <td>" . htmlspecialchars($row['name']) . "</td>
                        <td>" . htmlspecialchars($row['url']) . "</td>
                        <td><img src='img/" . htmlspecialchars($row['logo']) . "' alt='" . htmlspecialchars($row['name']) . "' style='max-width:50px;'></td>
                        <td>
                          <a href='admin.php?action=edit_social_media&id={$row['id']}'>Editar</a> |
                          <a href='admin.php?action=delete_social_media&id={$row['id']}' onclick='return confirm(\"¿Eliminar?\");'>Eliminar</a>
                        </td>
                      </tr>";
        }
        $html .= "</table>";
        renderAdmin($html);
        break;

    // -----------------------------------------------------------
    // SOCIAL MEDIA: EDIT (Add / Update)
    // -----------------------------------------------------------
    case 'edit_social_media':
        requireLogin();
        $id = $_GET['id'] ?? null;
        $socialMediaData = [
            'id' => '',
            'category' => '',
            'name' => '',
            'url' => '',
            'logo' => ''
        ];

        // Fetch existing if editing
        if ($id) {
            $stmt = $db->prepare("SELECT * FROM social_media WHERE id = :id");
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $res = $stmt->execute();
            $found = $res->fetchArray(SQLITE3_ASSOC);
            if ($found) {
                $socialMediaData = $found;
            }
        }

        // Handle form submission
        if (isset($_POST['save_social_media'])) {
            $category = trim($_POST['category']);
            $name = trim($_POST['name']);
            $url = trim($_POST['url']);
            $logo = trim($_POST['logo']);

            if ($id) {
                // Update existing
                $st = $db->prepare("UPDATE social_media
                                    SET category = :category,
                                        name = :name,
                                        url = :url,
                                        logo = :logo
                                    WHERE id = :id");
                $st->bindValue(':id', $id, SQLITE3_INTEGER);
            } else {
                // Insert new
                $st = $db->prepare("INSERT INTO social_media (category, name, url, logo)
                                    VALUES (:category, :name, :url, :logo)");
            }
            $st->bindValue(':category', $category, SQLITE3_TEXT);
            $st->bindValue(':name', $name, SQLITE3_TEXT);
            $st->bindValue(':url', $url, SQLITE3_TEXT);
            $st->bindValue(':logo', $logo, SQLITE3_TEXT);
            $st->execute();

            header('Location: admin.php?action=list_social_media');
            exit();
        }

        // Render form
        $html = "<div class='admin-form'>
                    <h2>" . ($id ? "Editar Enlace de Red Social" : "Agregar Enlace de Red Social") . "</h2>
                    <form method='post'>
                        <label for='category'>Categoría:</label>
                        <input type='text' name='category' id='category' value='" . htmlspecialchars($socialMediaData['category']) . "' required>

                        <label for='name'>Nombre:</label>
                        <input type='text' name='name' id='name' value='" . htmlspecialchars($socialMediaData['name']) . "' required>

                        <label for='url'>URL:</label>
                        <input type='text' name='url' id='url' value='" . htmlspecialchars($socialMediaData['url']) . "' required>

                        <label for='logo'>Logo URL:</label>
                        <input type='text' name='logo' id='logo' value='" . htmlspecialchars($socialMediaData['logo']) . "' required>

                        <button type='submit' name='save_social_media'>Guardar</button>
                    </form>
                 </div>";
        renderAdmin($html);
        break;

    // -----------------------------------------------------------
    // SOCIAL MEDIA: DELETE
    // -----------------------------------------------------------
    case 'delete_social_media':
        requireLogin();
        $id = $_GET['id'] ?? null;
        if ($id) {
            $st = $db->prepare("DELETE FROM social_media WHERE id = :id");
            $st->bindValue(':id', $id, SQLITE3_INTEGER);
            $st->execute();
        }
        header('Location: admin.php?action=list_social_media');
        exit();

    // -----------------------------------------------------------
    // DEFAULT
    // -----------------------------------------------------------
    default:
        if (isLoggedIn()) {
            header('Location: admin.php?action=dashboard');
        } else {
            header('Location: admin.php?action=login');
        }
        exit();
}
?>
	<link rel="stylesheet" href="https://jocarsa.github.io/jocarsa-lightslateblue/jocarsa%20%7C%20lightslateblue.css">
    <script src="https://jocarsa.github.io/jocarsa-lightslateblue/jocarsa%20%7C%20lightslateblue.js"></script>
