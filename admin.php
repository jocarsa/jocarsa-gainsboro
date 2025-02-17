<?php
session_start();
require_once 'config.php';

// Credenciales de demostración (Cambia según necesites)
define('ADMIN_USER', 'jocarsa');
define('ADMIN_PASS', 'jocarsa');

// Conexión a la Base de Datos
$db = new SQLite3($dbPath);

// Asegurar que todas las tablas necesarias existan
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

// Asegurar tabla de contacto
$db->exec("CREATE TABLE IF NOT EXISTS contact (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL,
    subject TEXT NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Tabla para medios
$db->exec("CREATE TABLE IF NOT EXISTS media (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    filename TEXT NOT NULL,
    filepath TEXT NOT NULL,
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
        ('footer_image', 'https://jocarsa.com/static/logo/footer-logo.svg')  // New entry for footer image
");

// -----------------------------------------------------------
// FUNCIONES AUXILIARES
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
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&display=swap');

            body, h1, h2, p, a, table, th, td, label, textarea, input, select {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
                font-family: 'Ubuntu', Arial, sans-serif;
            }
            body {
                background-color: #f4f4f4;
                display: flex;
                flex-direction: column;
                min-height: 100vh;
            }
            .admin-container {
                width: 90%;
                max-width: 1000px;
                margin: 20px auto;
                background: #ffffff;
                padding: 20px;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                border-radius: 8px;
                flex: 1;
                display: flex;
                flex-direction: column;
            }
            header h1 {
                background-color: #3a3a3a;
                color: gainsboro;
                padding: 20px;
                text-align: center;
                font-size: 24px;
                border-radius: 8px 8px 0 0;
                margin-bottom: 0;
            }
            nav {
                background-color: #444;
                padding: 10px;
                text-align: center;
                margin-bottom: 20px;
                border-radius: 0 0 8px 8px;
            }
            nav a {
                color: white;
                text-decoration: none;
                font-weight: bold;
                margin: 0 10px;
                padding: 8px 15px;
                border-radius: 4px;
                transition: background-color 0.3s ease;
            }
            nav a:hover {
                background-color: #3a3a3a;
            }
            label {
                display: block;
                margin-top: 15px;
                font-weight: bold;
            }
            input[type='text'], input[type='password'], textarea, select, input[type='file'] {
                width: 100%;
                padding: 8px;
                margin-top: 5px;
                border: 1px solid #ccc;
                border-radius: 4px;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }
            table, th, td {
                border: 1px solid #ddd;
            }
            th, td {
                padding: 8px;
                text-align: left;
            }
            button {
                background-color: #3a3a3a;
                color: gainsboro;
                border: none;
                padding: 10px 20px;
                border-radius: 4px;
                cursor: pointer;
                margin-top: 15px;
            }
            button:hover {
                background-color: #2a2a2a;
            }
            .danger {
                color: red;
            }
            .success {
                color: green;
            }
            footer {
                background-color: #3a3a3a;
                color: gainsboro;
                padding: 20px;
                text-align: center;
                margin-top: auto;
            }
            .jocarsa-lightslateblue {
                resize: vertical;
                min-height: 200px;
            }
        </style>
    </head>
    <body>
    <div class='admin-container'>
        $content
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

// Routing
$action = $_GET['action'] ?? 'login';
$message = '';

// Cerrar sesión
if ($action === 'logout') {
    session_destroy();
    header('Location: admin.php');
    exit();
}

// Procesar inicio de sesión
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

// Si no está logueado, forzar login (excepto acción=login)
if (!isLoggedIn() && $action !== 'login') {
    header('Location: admin.php?action=login');
    exit();
}

switch($action) {

    // -----------------------------------------------------------
    // LOGIN
    // -----------------------------------------------------------
    case 'login':
        $html = "<header><h1>Acceso al Panel</h1></header>
                 <nav></nav>
                 $message
                 <form method='post' action='admin.php?action=do_login'>
                    <label>Usuario:</label>
                    <input type='text' name='username' required>
                    <label>Contraseña:</label>
                    <input type='password' name='password' required>
                    <button type='submit'>Acceder</button>
                 </form>";
        renderAdmin($html);
        break;

    // -----------------------------------------------------------
    // DASHBOARD
    // -----------------------------------------------------------
    case 'dashboard':
        $html = "<header><h1>Panel de Administración</h1></header>
                 <nav>
                     <a href='admin.php?action=dashboard'>Inicio</a>
                     <a href='admin.php?action=list_pages'>Páginas</a>
                     <a href='admin.php?action=list_blog'>Blog</a>
                     <a href='admin.php?action=list_themes'>Temas</a>
                     <a href='admin.php?action=list_config'>Configuración</a>
                     <a href='admin.php?action=list_media'>Biblioteca</a>
                     <a href='admin.php?action=list_contact'>Contacto</a>
                     <a href='admin.php?action=logout'>Salir</a>
                 </nav>
                 <p>Bienvenido al panel de administración.</p>";
        renderAdmin($html);
        break;

    // -----------------------------------------------------------
    // CONTACTO: LISTAR MENSAJES
    // -----------------------------------------------------------
    case 'list_contact':
        $res = $db->query("SELECT * FROM contact ORDER BY id DESC");

        $html = "<header><h1>Contacto</h1></header>
                 <nav>
                     <a href='admin.php?action=dashboard'>Inicio</a>
                     <a href='admin.php?action=list_pages'>Páginas</a>
                     <a href='admin.php?action=list_blog'>Blog</a>
                     <a href='admin.php?action=list_themes'>Temas</a>
                     <a href='admin.php?action=list_config'>Configuración</a>
                     <a href='admin.php?action=list_media'>Biblioteca</a>
                     <a href='admin.php?action=list_contact'>Contacto</a>
                     <a href='admin.php?action=logout'>Salir</a>
                 </nav>
                 <table>
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
                        <td>".htmlspecialchars($row['name'])."</td>
                        <td>".htmlspecialchars($row['email'])."</td>
                        <td>".htmlspecialchars($row['subject'])."</td>
                        <td>".htmlspecialchars($row['created_at'])."</td>
                        <td>
                          <a href='admin.php?action=view_contact&id={$row['id']}'>Ver</a>
                        </td>
                      </tr>";
        }

        $html .= "</table>";
        renderAdmin($html);
        break;

    // -----------------------------------------------------------
    // CONTACTO: VER UN MENSAJE
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

        $html = "<header><h1>Ver Mensaje</h1></header>
                 <nav>
                     <a href='admin.php?action=dashboard'>Inicio</a>
                     <a href='admin.php?action=list_pages'>Páginas</a>
                     <a href='admin.php?action=list_blog'>Blog</a>
                     <a href='admin.php?action=list_themes'>Temas</a>
                     <a href='admin.php?action=list_config'>Configuración</a>
                     <a href='admin.php?action=list_media'>Biblioteca</a>
                     <a href='admin.php?action=list_contact'>Contacto</a>
                     <a href='admin.php?action=logout'>Salir</a>
                 </nav>";

        $html .= "<table>
                    <tr><th>ID</th><td>{$messageData['id']}</td></tr>
                    <tr><th>Nombre</th><td>".htmlspecialchars($messageData['name'])."</td></tr>
                    <tr><th>Correo Electrónico</th><td>".htmlspecialchars($messageData['email'])."</td></tr>
                    <tr><th>Asunto</th><td>".htmlspecialchars($messageData['subject'])."</td></tr>
                    <tr><th>Mensaje</th><td>".nl2br(htmlspecialchars($messageData['message']))."</td></tr>
                    <tr><th>Creado</th><td>{$messageData['created_at']}</td></tr>
                  </table>";

        renderAdmin($html);
        break;

    // -----------------------------------------------------------
    // BIBLIOTECA DE MEDIOS: LISTAR
    // -----------------------------------------------------------
    case 'list_media':
        $mediaItems = getAllMedia($db);
        $html = "<header><h1>Biblioteca</h1></header>
                 <nav>
                     <a href='admin.php?action=dashboard'>Inicio</a>
                     <a href='admin.php?action=list_pages'>Páginas</a>
                     <a href='admin.php?action=list_blog'>Blog</a>
                     <a href='admin.php?action=list_themes'>Temas</a>
                     <a href='admin.php?action=list_config'>Configuración</a>
                     <a href='admin.php?action=list_media'>Biblioteca</a>
                     <a href='admin.php?action=list_contact'>Contacto</a>
                     <a href='admin.php?action=logout'>Salir</a>
                 </nav>
                 <p><a href='admin.php?action=upload_media'>[+] Subir Nuevo Archivo</a></p>
                 <table>
                   <tr><th>ID</th><th>Nombre de Archivo</th><th>Ruta</th><th>Fecha</th><th>Vista Previa</th><th>Acciones</th></tr>";

        foreach ($mediaItems as $m) {
            $html .= "<tr>
                        <td>{$m['id']}</td>
                        <td>".htmlspecialchars($m['filename'])."</td>
                        <td>".htmlspecialchars($m['filepath'])."</td>
                        <td>".htmlspecialchars($m['created_at'])."</td>
                        <td><img src='{$m['filepath']}' alt='' style='max-width:100px;'></td>
                        <td>
                           <a href='admin.php?action=delete_media&id={$m['id']}'
                              onclick='return confirm(\"¿Eliminar?\");'>Eliminar</a>
                        </td>
                      </tr>";
        }

        $html .= "</table>";
        renderAdmin($html);
        break;

    // -----------------------------------------------------------
    // BIBLIOTECA DE MEDIOS: SUBIR
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
                // Nombre único
                $uniqueName = time() . '-' . preg_replace('/[^a-zA-Z0-9._-]/','', $fileName);
                $targetPath = $targetDir . $uniqueName;

                if (move_uploaded_file($tmpName, $targetPath)) {
                    // Guardar en DB
                    $dbFilePath = 'static/' . $uniqueName; // ruta relativa
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

        $html = "<header><h1>Subir Nuevo Archivo</h1></header>
                 <nav>
                     <a href='admin.php?action=dashboard'>Inicio</a>
                     <a href='admin.php?action=list_pages'>Páginas</a>
                     <a href='admin.php?action=list_blog'>Blog</a>
                     <a href='admin.php?action=list_themes'>Temas</a>
                     <a href='admin.php?action=list_config'>Configuración</a>
                     <a href='admin.php?action=list_media'>Biblioteca</a>
                     <a href='admin.php?action=list_contact'>Contacto</a>
                     <a href='admin.php?action=logout'>Salir</a>
                 </nav>
                 $message
                 <form method='post' enctype='multipart/form-data'>
                   <label>Selecciona el archivo:</label>
                   <input type='file' name='file'>
                   <button type='submit' name='upload'>Subir</button>
                 </form>";
        renderAdmin($html);
        break;

    // -----------------------------------------------------------
    // BIBLIOTECA DE MEDIOS: ELIMINAR
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
    // PÁGINAS: LISTAR
    // -----------------------------------------------------------
    case 'list_pages':
        $res = $db->query("SELECT * FROM pages ORDER BY id DESC");
        $html = "<header><h1>Páginas</h1></header>
                 <nav>
                     <a href='admin.php?action=dashboard'>Inicio</a>
                     <a href='admin.php?action=list_pages'>Páginas</a>
                     <a href='admin.php?action=list_blog'>Blog</a>
                     <a href='admin.php?action=list_themes'>Temas</a>
                     <a href='admin.php?action=list_config'>Configuración</a>
                     <a href='admin.php?action=list_media'>Biblioteca</a>
                     <a href='admin.php?action=list_contact'>Contacto</a>
                     <a href='admin.php?action=logout'>Salir</a>
                 </nav>
                 <p><a href='admin.php?action=edit_page'>[+] Agregar Nueva Página</a></p>
                 <table>
                    <tr><th>ID</th><th>Título</th><th>Acciones</th></tr>";

        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $html .= "<tr>
                        <td>{$row['id']}</td>
                        <td>".htmlspecialchars($row['title'])."</td>
                        <td>
                            <a href='admin.php?action=edit_page&id={$row['id']}'>Editar</a> |
                            <a href='admin.php?action=delete_page&id={$row['id']}'
                               onclick='return confirm(\"¿Eliminar?\");'>Eliminar</a>
                        </td>
                      </tr>";
        }
        $html .= "</table>";
        renderAdmin($html);
        break;

    // -----------------------------------------------------------
    // PÁGINAS: EDITAR (Agregar / Actualizar)
    // -----------------------------------------------------------
    case 'edit_page':
        $id = $_GET['id'] ?? null;
        $pageData = ['id'=>'','title'=>'','content'=>''];

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
            $title   = $_POST['title']   ?? '';
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

        $html = "<header><h1>" . ($id ? "Editar Página" : "Agregar Página") . "</h1></header>
                 <nav>
                     <a href='admin.php?action=dashboard'>Inicio</a>
                     <a href='admin.php?action=list_pages'>Páginas</a>
                     <a href='admin.php?action=list_blog'>Blog</a>
                     <a href='admin.php?action=list_themes'>Temas</a>
                     <a href='admin.php?action=list_config'>Configuración</a>
                     <a href='admin.php?action=list_media'>Biblioteca</a>
                     <a href='admin.php?action=list_contact'>Contacto</a>
                     <a href='admin.php?action=logout'>Salir</a>
                 </nav>
                 <form method='post'>
                    <label>Título:</label>
                    <input type='text' name='title' value='" . htmlspecialchars($pageData['title']) . "' required>

                    <label>Contenido:</label>
                    <textarea name='content' class='jocarsa-lightslateblue' rows='10'>"
                    . htmlspecialchars($pageData['content']) . "</textarea>

                    <button type='submit' name='save_page'>Guardar</button>
                 </form>";
        renderAdmin($html);
        break;

    // -----------------------------------------------------------
    // PÁGINAS: ELIMINAR
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
    // BLOG: LISTAR
    // -----------------------------------------------------------
    case 'list_blog':
        $res = $db->query("SELECT * FROM blog ORDER BY id DESC");
        $html = "<header><h1>Entradas del Blog</h1></header>
                 <nav>
                     <a href='admin.php?action=dashboard'>Inicio</a>
                     <a href='admin.php?action=list_pages'>Páginas</a>
                     <a href='admin.php?action=list_blog'>Blog</a>
                     <a href='admin.php?action=list_themes'>Temas</a>
                     <a href='admin.php?action=list_config'>Configuración</a>
                     <a href='admin.php?action=list_media'>Biblioteca</a>
                     <a href='admin.php?action=list_contact'>Contacto</a>
                     <a href='admin.php?action=logout'>Salir</a>
                 </nav>
                 <p><a href='admin.php?action=edit_blog'>[+] Agregar Nueva Entrada</a></p>
                 <table>
                    <tr><th>ID</th><th>Título</th><th>Fecha</th><th>Acciones</th></tr>";

        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $html .= "<tr>
                        <td>{$row['id']}</td>
                        <td>" . htmlspecialchars($row['title']) . "</td>
                        <td>" . htmlspecialchars($row['created_at']) . "</td>
                        <td>
                            <a href='admin.php?action=edit_blog&id={$row['id']}'>Editar</a> |
                            <a href='admin.php?action=delete_blog&id={$row['id']}'
                               onclick='return confirm(\"¿Eliminar?\");'>Eliminar</a>
                        </td>
                      </tr>";
        }
        $html .= "</table>";
        renderAdmin($html);
        break;

    // -----------------------------------------------------------
    // BLOG: EDITAR / AGREGAR
    // -----------------------------------------------------------
    case 'edit_blog':
        $id = $_GET['id'] ?? null;
        $blogData = ['id'=>'','title'=>'','content'=>'','created_at'=>''];

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
            $title   = $_POST['title']   ?? '';
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

        $html = "<header><h1>" . ($id ? "Editar Entrada del Blog" : "Agregar Entrada") . "</h1></header>
                 <nav>
                     <a href='admin.php?action=dashboard'>Inicio</a>
                     <a href='admin.php?action=list_pages'>Páginas</a>
                     <a href='admin.php?action=list_blog'>Blog</a>
                     <a href='admin.php?action=list_themes'>Temas</a>
                     <a href='admin.php?action=list_config'>Configuración</a>
                     <a href='admin.php?action=list_media'>Biblioteca</a>
                     <a href='admin.php?action=list_contact'>Contacto</a>
                     <a href='admin.php?action=logout'>Salir</a>
                 </nav>
                 <form method='post'>
                    <label>Título:</label>
                    <input type='text' name='title' value='" . htmlspecialchars($blogData['title']) . "' required>

                    <label>Contenido:</label>
                    <textarea name='content' class='jocarsa-lightslateblue' rows='10'>"
                    . htmlspecialchars($blogData['content']) . "</textarea>

                    <button type='submit' name='save_blog'>Guardar</button>
                 </form>";
        renderAdmin($html);
        break;

    // -----------------------------------------------------------
    // BLOG: ELIMINAR
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
    // TEMAS: LISTAR Y ACTIVAR
    // -----------------------------------------------------------
    case 'list_themes':
        $themes       = getAvailableThemes();
        $activeTheme  = $db->querySingle("SELECT value FROM config WHERE key='active_theme'");
        $html = "<header><h1>Temas</h1></header>
                 <nav>
                     <a href='admin.php?action=dashboard'>Inicio</a>
                     <a href='admin.php?action=list_pages'>Páginas</a>
                     <a href='admin.php?action=list_blog'>Blog</a>
                     <a href='admin.php?action=list_themes'>Temas</a>
                     <a href='admin.php?action=list_config'>Configuración</a>
                     <a href='admin.php?action=list_media'>Biblioteca</a>
                     <a href='admin.php?action=list_contact'>Contacto</a>
                     <a href='admin.php?action=logout'>Salir</a>
                 </nav>";

        if (empty($themes)) {
            $html .= "<p>No se encontraron temas en la carpeta css.</p>";
            renderAdmin($html);
            break;
        }

        $html .= "<table>
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
    // CONFIG: LISTAR
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
        $html = "<header><h1>Configuración del Sitio</h1></header>
                 <nav>
                     <a href='admin.php?action=dashboard'>Inicio</a>
                     <a href='admin.php?action=list_pages'>Páginas</a>
                     <a href='admin.php?action=list_blog'>Blog</a>
                     <a href='admin.php?action=list_themes'>Temas</a>
                     <a href='admin.php?action=list_config'>Configuración</a>
                     <a href='admin.php?action=list_media'>Biblioteca</a>
                     <a href='admin.php?action=list_contact'>Contacto</a>
                     <a href='admin.php?action=logout'>Salir</a>
                 </nav>
                 $message
                 <form method='post'>
                 <table>
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

