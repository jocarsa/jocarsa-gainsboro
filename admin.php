<?php
session_start();
require_once 'config.php';

// Demo credentials
define('ADMIN_USER', 'jocarsa');
define('ADMIN_PASS', 'jocarsa');

// Connect DB
$db = new SQLite3($dbPath);

// Ensure all tables exist
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

$db->exec("CREATE TABLE IF NOT EXISTS config (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    key TEXT UNIQUE NOT NULL,
    value TEXT NOT NULL
);");

// NEW: MEDIA TABLE
$db->exec("CREATE TABLE IF NOT EXISTS media (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    filename TEXT NOT NULL,
    filepath TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);");

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true);
}

/**
 * Require login
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: admin.php');
        exit();
    }
}

/**
 * Render admin layout
 */
function renderAdmin($content) {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <meta charset='utf-8'>
        <title>Admin Panel</title>
        <!-- Inline style or you can use a separate css/admin.css file -->
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Ubuntu:ital,wght@0,300;0,400;0,500;0,700;1,300;1,400;1,500;1,700&display=swap');

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
            .block-item {
                border: 1px solid #ddd;
                border-radius: 4px;
                padding: 10px;
                margin-top: 10px;
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
                border-radius: 0;
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

/**************************************************************
 * MEDIA LIBRARY HELPERS
 **************************************************************/

/**
 * Return array of all media items
 */
function getAllMedia($db) {
    $items = [];
    $res = $db->query("SELECT * FROM media ORDER BY id DESC");
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $items[] = $row;
    }
    return $items;
}

/**************************************************************
 * BLOCK LIBRARY DEFINITIONS
 * For fields you want to pick from the media library,
 * use 'type' => 'media'.
 **************************************************************/
$blockTypes = [
    'hero' => [
        'label' => 'Hero Banner',
        'fields' => [
            'title'           => ['type'=>'text',   'label'=>'Hero Title'],
            'subtitle'        => ['type'=>'text',   'label'=>'Subtitle'],
            'backgroundImage' => ['type'=>'media',  'label'=>'Background Image'],
            'buttonText'      => ['type'=>'text',   'label'=>'Button Text (optional)'],
            'buttonLink'      => ['type'=>'text',   'label'=>'Button Link (optional)'],
        ],
    ],
    'article' => [
        'label' => 'Article',
        'fields' => [
            'heading'         => ['type'=>'text',   'label'=>'Article Heading'],
            'content'         => ['type'=>'textarea','label'=>'Article Content'],
        ],
    ],
    'testimonial' => [
        'label' => 'Testimonial',
        'fields' => [
            'quote'           => ['type'=>'textarea','label'=>'Quote'],
            'author'          => ['type'=>'text',    'label'=>'Author'],
        ],
    ],
    'gallery' => [
        'label' => 'Image Gallery',
        'fields' => [
            'images'          => ['type'=>'textarea','label'=>'Image URLs (one per line)'],
        ],
    ],
    'faq' => [
        'label' => 'FAQ',
        'fields' => [
            'items'           => ['type'=>'textarea','label'=>"FAQ items (Q:..., A:...)"],
        ],
    ],
    'cta' => [
        'label' => 'Call To Action',
        'fields' => [
            'text'            => ['type'=>'textarea','label'=>'CTA Text'],
            'buttonText'      => ['type'=>'text',    'label'=>'Button Text'],
            'buttonLink'      => ['type'=>'text',    'label'=>'Button Link'],
        ],
    ],
    'video' => [
        'label' => 'Video Embed',
        'fields' => [
            'embedUrl'        => ['type'=>'text',    'label'=>'Embed URL'],
            'caption'         => ['type'=>'text',    'label'=>'Caption (optional)'],
        ],
    ],
    'columns' => [
        'label' => '2 Columns',
        'fields' => [
            'leftContent'     => ['type'=>'textarea','label'=>'Left Column Content'],
            'rightContent'    => ['type'=>'textarea','label'=>'Right Column Content'],
        ],
    ],
];

// Routing
$action = $_GET['action'] ?? 'login';
$message = '';

// LOGOUT
if ($action === 'logout') {
    session_destroy();
    header('Location: admin.php');
    exit();
}

// DO LOGIN
if ($action === 'do_login') {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';
    if ($user === ADMIN_USER && $pass === ADMIN_PASS) {
        $_SESSION['logged_in'] = true;
        header('Location: admin.php?action=dashboard');
        exit();
    } else {
        $message = "<p class='danger'>Invalid credentials</p>";
        $action = 'login';
    }
}

// If not logged in, force login (except for action=login)
if (!isLoggedIn() && $action !== 'login') {
    header('Location: admin.php?action=login');
    exit();
}

switch($action) {

    // -----------------------------------------------------------
    // LOGIN FORM
    // -----------------------------------------------------------
    case 'login':
        $html = "<header><h1>Admin Login</h1></header>
                 <nav></nav>
                 $message
                 <form method='post' action='admin.php?action=do_login'>
                    <label>Username:</label>
                    <input type='text' name='username' required>
                    <label>Password:</label>
                    <input type='password' name='password' required>
                    <button type='submit'>Login</button>
                 </form>";
        renderAdmin($html);
        break;

    // -----------------------------------------------------------
    // DASHBOARD
    // -----------------------------------------------------------
    case 'dashboard':
        requireLogin();
        $html = "<header><h1>Admin Dashboard</h1></header>
                 <nav>
                     <a href='admin.php?action=dashboard'>Home</a>
                     <a href='admin.php?action=list_pages'>Pages</a>
                     <a href='admin.php?action=list_blog'>Blog</a>
                     <a href='admin.php?action=list_themes'>Themes</a>
                     <a href='admin.php?action=list_config'>Config</a>
                     <a href='admin.php?action=list_media'>Media</a>
                     <a href='admin.php?action=logout'>Logout</a>
                 </nav>
                 <p>Welcome to the admin dashboard.</p>";
        renderAdmin($html);
        break;

    // -----------------------------------------------------------
    // MEDIA: LIST
    // -----------------------------------------------------------
    case 'list_media':
        requireLogin();
        $mediaItems = getAllMedia($db);
        $html = "<header><h1>Media Library</h1></header>
                 <nav>
                     <a href='admin.php?action=dashboard'>Home</a>
                     <a href='admin.php?action=list_pages'>Pages</a>
                     <a href='admin.php?action=list_blog'>Blog</a>
                     <a href='admin.php?action=list_themes'>Themes</a>
                     <a href='admin.php?action=list_config'>Config</a>
                     <a href='admin.php?action=list_media'>Media</a>
                     <a href='admin.php?action=logout'>Logout</a>
                 </nav>
                 <p><a href='admin.php?action=upload_media'>[+] Upload New Media</a></p>
                 <table>
                   <tr><th>ID</th><th>Filename</th><th>Filepath</th><th>Created</th><th>Preview</th><th>Actions</th></tr>";

        foreach ($mediaItems as $m) {
            $html .= "<tr>
                        <td>{$m['id']}</td>
                        <td>".htmlspecialchars($m['filename'])."</td>
                        <td>".htmlspecialchars($m['filepath'])."</td>
                        <td>".htmlspecialchars($m['created_at'])."</td>
                        <td><img src='{$m['filepath']}' alt='' style='max-width:100px;'></td>
                        <td>
                           <a href='admin.php?action=delete_media&id={$m['id']}' onclick='return confirm(\"Delete?\");'>Delete</a>
                        </td>
                      </tr>";
        }

        $html .= "</table>";
        renderAdmin($html);
        break;

    // -----------------------------------------------------------
    // MEDIA: UPLOAD
    // -----------------------------------------------------------
    case 'upload_media':
        requireLogin();
        if (isset($_POST['upload'])) {
            if (!empty($_FILES['file']['name'])) {
                $fileName = $_FILES['file']['name'];
                $tmpName  = $_FILES['file']['tmp_name'];

                $targetDir = __DIR__ . '/static/';
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0777, true);
                }
                // make a unique name
                $uniqueName = time() . '-' . preg_replace('/[^a-zA-Z0-9._-]/','', $fileName);
                $targetPath = $targetDir . $uniqueName;

                if (move_uploaded_file($tmpName, $targetPath)) {
                    // store in DB
                    $dbFilePath = 'static/' . $uniqueName; // relative path
                    $stmt = $db->prepare("INSERT INTO media (filename, filepath) VALUES (:fn, :fp)");
                    $stmt->bindValue(':fn', $fileName, SQLITE3_TEXT);
                    $stmt->bindValue(':fp', $dbFilePath, SQLITE3_TEXT);
                    $stmt->execute();
                    header('Location: admin.php?action=list_media');
                    exit();
                } else {
                    $message = "<p class='danger'>Error moving uploaded file.</p>";
                }
            } else {
                $message = "<p class='danger'>No file selected.</p>";
            }
        } else {
            $message = '';
        }

        $html = "<header><h1>Upload Media</h1></header>
                 <nav>
                     <a href='admin.php?action=dashboard'>Home</a>
                     <a href='admin.php?action=list_pages'>Pages</a>
                     <a href='admin.php?action=list_blog'>Blog</a>
                     <a href='admin.php?action=list_themes'>Themes</a>
                     <a href='admin.php?action=list_config'>Config</a>
                     <a href='admin.php?action=list_media'>Media</a>
                     <a href='admin.php?action=logout'>Logout</a>
                 </nav>
                 $message
                 <form method='post' enctype='multipart/form-data'>
                   <label>Select File:</label>
                   <input type='file' name='file'>
                   <button type='submit' name='upload'>Upload</button>
                 </form>";
        renderAdmin($html);
        break;

    // -----------------------------------------------------------
    // MEDIA: DELETE
    // -----------------------------------------------------------
    case 'delete_media':
        requireLogin();
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
        requireLogin();
        $res = $db->query("SELECT * FROM pages ORDER BY id DESC");
        $html = "<header><h1>Pages</h1></header>
                 <nav>
                     <a href='admin.php?action=dashboard'>Home</a>
                     <a href='admin.php?action=list_pages'>Pages</a>
                     <a href='admin.php?action=list_blog'>Blog</a>
                     <a href='admin.php?action=list_themes'>Themes</a>
                     <a href='admin.php?action=list_config'>Config</a>
                     <a href='admin.php?action=list_media'>Media</a>
                     <a href='admin.php?action=logout'>Logout</a>
                 </nav>
                 <p><a href='admin.php?action=edit_page'>[+] Add New Page</a></p>
                 <table>
                    <tr><th>ID</th><th>Title</th><th>Actions</th></tr>";

        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $html .= "<tr>
                        <td>{$row['id']}</td>
                        <td>".htmlspecialchars($row['title'])."</td>
                        <td>
                            <a href='admin.php?action=edit_page&id={$row['id']}'>Edit</a> | 
                            <a href='admin.php?action=delete_page&id={$row['id']}' onclick='return confirm(\"Delete?\");'>Delete</a>
                        </td>
                      </tr>";
        }
        $html .= "</table>";
        renderAdmin($html);
        break;

    // -----------------------------------------------------------
    // PAGES: EDIT
    // -----------------------------------------------------------
    case 'edit_page':
        requireLogin();
        $id = $_GET['id'] ?? null;
        $pageData = ['id'=>'','title'=>'','content'=>json_encode([])];

        if ($id) {
            $st = $db->prepare("SELECT * FROM pages WHERE id = :id");
            $st->bindValue(':id', $id, SQLITE3_INTEGER);
            $res = $st->execute();
            $found = $res->fetchArray(SQLITE3_ASSOC);
            if ($found) {
                $pageData = $found;
            }
        }

        // If saving
        if (isset($_POST['save_page'])) {
            $title = $_POST['title'] ?? '';
            $blocks = $_POST['blocks'] ?? [];
            $contentJson = json_encode($blocks, JSON_UNESCAPED_UNICODE);

            if ($id) {
                $st = $db->prepare("UPDATE pages SET title = :title, content = :content WHERE id = :id");
                $st->bindValue(':id', $id, SQLITE3_INTEGER);
            } else {
                $st = $db->prepare("INSERT INTO pages (title, content) VALUES (:title, :content)");
            }
            $st->bindValue(':title', $title, SQLITE3_TEXT);
            $st->bindValue(':content', $contentJson, SQLITE3_TEXT);
            $st->execute();
            header('Location: admin.php?action=list_pages');
            exit();
        }

        // Decode existing content
        $blocksData = [];
        if (!empty($pageData['content'])) {
            $tmp = json_decode($pageData['content'], true);
            if (is_array($tmp)) {
                $blocksData = $tmp;
            }
        }

        // Build form
        $html = "<header><h1>".($id ? "Edit Page" : "Add Page")."</h1></header>
                 <nav>
                     <a href='admin.php?action=dashboard'>Home</a>
                     <a href='admin.php?action=list_pages'>Pages</a>
                     <a href='admin.php?action=list_blog'>Blog</a>
                     <a href='admin.php?action=list_themes'>Themes</a>
                     <a href='admin.php?action=list_config'>Config</a>
                     <a href='admin.php?action=list_media'>Media</a>
                     <a href='admin.php?action=logout'>Logout</a>
                 </nav>
                 <form method='post'>
                    <label>Title:</label>
                    <input type='text' name='title' value='".htmlspecialchars($pageData['title'])."' required>

                    <h2>Block Builder</h2>
                    <div id='block-builder-container'>";

        // Existing blocks
        foreach ($blocksData as $index => $block) {
            $bType = $block['type'] ?? 'hero';
            $html .= buildBlockEditorHtml($db, $index, $bType, $block, $blockTypes);
        }

        // The "Add New Block" button
        $html .= "</div>
                  <button type='button' onclick='addBlock()'>Add New Block</button>
                  <button type='submit' name='save_page'>Save</button>
                 </form>";

        // Add the JavaScript that defines addBlock()  
        $html .= "<script>
var blockIndex = ".count($blocksData).";
var blockTypes = ".json_encode($blockTypes).";
var mediaLibrary = ".json_encode(getAllMedia($db)).";

// Called by the 'Add New Block' button
function addBlock() {
    var container = document.getElementById('block-builder-container');
    var newBlockType = 'hero';
    var newBlockData = {};
    var newHtml = buildBlockHtml(blockIndex, newBlockType, newBlockData);
    var div = document.createElement('div');
    div.classList.add('block-item');
    div.innerHTML = newHtml;
    container.appendChild(div);
    blockIndex++;
}

// Build the HTML for a block
function buildBlockHtml(i, type, blockData) {
    let out = '';
    // Block type <select>
    out += `<label>Block Type:</label>
            <select name='blocks[\${i}][type]' onchange='onBlockTypeChange(\${i}, this.value)'>`;
    for (const tKey in blockTypes) {
        const label = blockTypes[tKey].label;
        const selected = (tKey === type) ? 'selected' : '';
        out += `<option value='\${tKey}' \${selected}>\${label}</option>`;
    }
    out += '</select>';

    // Fields
    var fields = blockTypes[type].fields;
    for (const fKey in fields) {
        const fDef = fields[fKey];
        const val = blockData[fKey] || '';
        out += `<label>\${fDef.label}</label>`;
        if (fDef.type === 'text') {
            out += `<input type='text' name='blocks[\${i}][\${fKey}]' value='\${escapeHtml(val)}'>`;
        } else if (fDef.type === 'textarea') {
            out += `<textarea name='blocks[\${i}][\${fKey}]' rows='3'>\${escapeHtml(val)}</textarea>`;
        } else if (fDef.type === 'media') {
            out += `<select name='blocks[\${i}][\${fKey}]'>
                     <option value=''>-- select media --</option>`;
            for (const m of mediaLibrary) {
                const selected = (m.filepath === val) ? 'selected' : '';
                out += \`<option value='\${escapeHtml(m.filepath)}' \${selected}>\${escapeHtml(m.filename)}</option>\`;
            }
            out += '</select>';
        }
    }
    return out;
}

// Called when user changes block type
function onBlockTypeChange(i, newType) {
    var blockContainer = document.querySelector('#block-builder-container .block-item:nth-child(' + (i+1) + ')');
    var newHtml = buildBlockHtml(i, newType, {});
    blockContainer.innerHTML = newHtml;
}

// Basic escaping
function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;')
              .replace(/</g, '&lt;')
              .replace(/>/g, '&gt;')
              .replace(/\"/g, '&quot;')
              .replace(/\'/g, '&#39;');
}
</script>";

        renderAdmin($html);
        break;

    // -----------------------------------------------------------
    // PAGES: DELETE
    // -----------------------------------------------------------
    case 'delete_page':
        requireLogin();
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
        requireLogin();
        $res = $db->query("SELECT * FROM blog ORDER BY id DESC");
        $html = "<header><h1>Blog Entries</h1></header>
                 <nav>
                     <a href='admin.php?action=dashboard'>Home</a>
                     <a href='admin.php?action=list_pages'>Pages</a>
                     <a href='admin.php?action=list_blog'>Blog</a>
                     <a href='admin.php?action=list_themes'>Themes</a>
                     <a href='admin.php?action=list_config'>Config</a>
                     <a href='admin.php?action=list_media'>Media</a>
                     <a href='admin.php?action=logout'>Logout</a>
                 </nav>
                 <p><a href='admin.php?action=edit_blog'>[+] Add New Blog Entry</a></p>
                 <table>
                    <tr><th>ID</th><th>Title</th><th>Created</th><th>Actions</th></tr>";

        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $html .= "<tr>
                        <td>{$row['id']}</td>
                        <td>".htmlspecialchars($row['title'])."</td>
                        <td>".htmlspecialchars($row['created_at'])."</td>
                        <td>
                            <a href='admin.php?action=edit_blog&id={$row['id']}'>Edit</a> |
                            <a href='admin.php?action=delete_blog&id={$row['id']}' onclick='return confirm(\"Delete?\");'>Delete</a>
                        </td>
                      </tr>";
        }
        $html .= "</table>";
        renderAdmin($html);
        break;

    // -----------------------------------------------------------
    // BLOG: EDIT
    // -----------------------------------------------------------
    case 'edit_blog':
        requireLogin();
        $id = $_GET['id'] ?? null;
        $blogData = ['id'=>'','title'=>'','content'=>json_encode([]),'created_at'=>''];

        if ($id) {
            $st = $db->prepare("SELECT * FROM blog WHERE id = :id");
            $st->bindValue(':id', $id, SQLITE3_INTEGER);
            $res = $st->execute();
            $found = $res->fetchArray(SQLITE3_ASSOC);
            if ($found) {
                $blogData = $found;
            }
        }

        // If saving
        if (isset($_POST['save_blog'])) {
            $title = $_POST['title'] ?? '';
            $blocks = $_POST['blocks'] ?? [];
            $contentJson = json_encode($blocks, JSON_UNESCAPED_UNICODE);

            if ($id) {
                $st = $db->prepare("UPDATE blog SET title = :title, content = :content WHERE id = :id");
                $st->bindValue(':id', $id, SQLITE3_INTEGER);
            } else {
                $st = $db->prepare("INSERT INTO blog (title, content) VALUES (:title, :content)");
            }
            $st->bindValue(':title', $title, SQLITE3_TEXT);
            $st->bindValue(':content', $contentJson, SQLITE3_TEXT);
            $st->execute();
            header('Location: admin.php?action=list_blog');
            exit();
        }

        // Decode existing
        $blocksData = [];
        if (!empty($blogData['content'])) {
            $tmp = json_decode($blogData['content'], true);
            if (is_array($tmp)) {
                $blocksData = $tmp;
            }
        }

        $html = "<header><h1>".($id ? "Edit Blog Entry" : "Add Blog Entry")."</h1></header>
                 <nav>
                     <a href='admin.php?action=dashboard'>Home</a>
                     <a href='admin.php?action=list_pages'>Pages</a>
                     <a href='admin.php?action=list_blog'>Blog</a>
                     <a href='admin.php?action=list_themes'>Themes</a>
                     <a href='admin.php?action=list_config'>Config</a>
                     <a href='admin.php?action=list_media'>Media</a>
                     <a href='admin.php?action=logout'>Logout</a>
                 </nav>
                 <form method='post'>
                    <label>Title:</label>
                    <input type='text' name='title' value='".htmlspecialchars($blogData['title'])."' required>

                    <h2>Block Builder</h2>
                    <div id='block-builder-container'>";

        // Existing blocks
        foreach ($blocksData as $index => $block) {
            $bType = $block['type'] ?? 'hero';
            $html .= buildBlockEditorHtml($db, $index, $bType, $block, $blockTypes);
        }

        // "Add New Block" button
        $html .= "</div>
                  <button type='button' onclick='addBlock()'>Add New Block</button>
                  <button type='submit' name='save_blog'>Save</button>
                 </form>";

        // JavaScript snippet
        $html .= "<script>
var blockIndex = ".count($blocksData).";
var blockTypes = ".json_encode($blockTypes).";
var mediaLibrary = ".json_encode(getAllMedia($db)).";

function addBlock() {
    var container = document.getElementById('block-builder-container');
    var newBlockType = 'hero';
    var newBlockData = {};
    var newHtml = buildBlockHtml(blockIndex, newBlockType, newBlockData);
    var div = document.createElement('div');
    div.classList.add('block-item');
    div.innerHTML = newHtml;
    container.appendChild(div);
    blockIndex++;
}

function buildBlockHtml(i, type, blockData) {
    let out = '';
    out += `<label>Block Type:</label>
            <select name='blocks[\${i}][type]' onchange='onBlockTypeChange(\${i}, this.value)'>`;
    for (const tKey in blockTypes) {
        const label = blockTypes[tKey].label;
        const selected = (tKey === type) ? 'selected' : '';
        out += `<option value='\${tKey}' \${selected}>\${label}</option>`;
    }
    out += '</select>';

    var fields = blockTypes[type].fields;
    for (const fKey in fields) {
        const fDef = fields[fKey];
        const val = blockData[fKey] || '';
        out += `<label>\${fDef.label}</label>`;
        if (fDef.type === 'text') {
            out += `<input type='text' name='blocks[\${i}][\${fKey}]' value='\${escapeHtml(val)}'>`;
        } else if (fDef.type === 'textarea') {
            out += `<textarea name='blocks[\${i}][\${fKey}]' rows='3'>\${escapeHtml(val)}</textarea>`;
        } else if (fDef.type === 'media') {
            out += `<select name='blocks[\${i}][\${fKey}]'>
                     <option value=''>-- select media --</option>`;
            for (const m of mediaLibrary) {
                const selected = (m.filepath === val) ? 'selected' : '';
                out += \`<option value='\${escapeHtml(m.filepath)}' \${selected}>\${escapeHtml(m.filename)}</option>\`;
            }
            out += '</select>';
        }
    }
    return out;
}

function onBlockTypeChange(i, newType) {
    var blockContainer = document.querySelector('#block-builder-container .block-item:nth-child(' + (i+1) + ')');
    var newHtml = buildBlockHtml(i, newType, {});
    blockContainer.innerHTML = newHtml;
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;')
              .replace(/</g, '&lt;')
              .replace(/>/g, '&gt;')
              .replace(/\"/g, '&quot;')
              .replace(/\'/g, '&#39;');
}
</script>";

        renderAdmin($html);
        break;

    // -----------------------------------------------------------
    // BLOG: DELETE
    // -----------------------------------------------------------
    case 'delete_blog':
        requireLogin();
        $id = $_GET['id'] ?? null;
        if ($id) {
            $st = $db->prepare("DELETE FROM blog WHERE id = :id");
            $st->bindValue(':id', $id, SQLITE3_INTEGER);
            $st->execute();
        }
        header('Location: admin.php?action=list_blog');
        exit();

    // -----------------------------------------------------------
    // THEMES: LIST
    // -----------------------------------------------------------
    case 'list_themes':
        requireLogin();
        $themes = $db->query("SELECT * FROM themes ORDER BY id ASC");
        $html = "<header><h1>Themes</h1></header>
                 <nav>
                     <a href='admin.php?action=dashboard'>Home</a>
                     <a href='admin.php?action=list_pages'>Pages</a>
                     <a href='admin.php?action=list_blog'>Blog</a>
                     <a href='admin.php?action=list_themes'>Themes</a>
                     <a href='admin.php?action=list_config'>Config</a>
                     <a href='admin.php?action=list_media'>Media</a>
                     <a href='admin.php?action=logout'>Logout</a>
                 </nav>
                 <p><a href='admin.php?action=add_theme'>[+] Add Theme</a></p>
                 <table>
                    <tr><th>ID</th><th>Name</th><th>Active</th><th>Actions</th></tr>";

        while ($row = $themes->fetchArray(SQLITE3_ASSOC)) {
            $active = $row['active'] ? 'Yes' : 'No';
            $html .= "<tr>
                        <td>{$row['id']}</td>
                        <td>".htmlspecialchars($row['name'])."</td>
                        <td>$active</td>
                        <td><a href='admin.php?action=activate_theme&id={$row['id']}'>Activate</a></td>
                      </tr>";
        }
        $html .= "</table>";
        renderAdmin($html);
        break;

    // ADD THEME
    case 'add_theme':
        requireLogin();
        if (isset($_POST['add_theme'])) {
            $name = $_POST['name'] ?? '';
            if ($name) {
                $st = $db->prepare("INSERT OR IGNORE INTO themes (name, active) VALUES (:name, 0)");
                $st->bindValue(':name', $name, SQLITE3_TEXT);
                $st->execute();
            }
            header('Location: admin.php?action=list_themes');
            exit();
        }
        $html = "<header><h1>Add Theme</h1></header>
                 <nav>
                     <a href='admin.php?action=dashboard'>Home</a>
                     <a href='admin.php?action=list_pages'>Pages</a>
                     <a href='admin.php?action=list_blog'>Blog</a>
                     <a href='admin.php?action=list_themes'>Themes</a>
                     <a href='admin.php?action=list_config'>Config</a>
                     <a href='admin.php?action=list_media'>Media</a>
                     <a href='admin.php?action=logout'>Logout</a>
                 </nav>
                 <form method='post'>
                    <label>Theme Name:</label>
                    <input type='text' name='name' required>
                    <button type='submit' name='add_theme'>Add Theme</button>
                 </form>";
        renderAdmin($html);
        break;

    // ACTIVATE THEME
    case 'activate_theme':
        requireLogin();
        $id = $_GET['id'] ?? null;
        if ($id) {
            $db->exec("UPDATE themes SET active = 0");
            $st = $db->prepare("UPDATE themes SET active = 1 WHERE id = :id");
            $st->bindValue(':id', $id, SQLITE3_INTEGER);
            $st->execute();
        }
        header('Location: admin.php?action=list_themes');
        exit();

    // -----------------------------------------------------------
    // CONFIG: LIST
    // -----------------------------------------------------------
    case 'list_config':
        requireLogin();
        if (isset($_POST['save_config'])) {
            foreach ($_POST['config'] as $k => $v) {
                $st = $db->prepare("UPDATE config SET value = :val WHERE key = :key");
                $st->bindValue(':val', $v, SQLITE3_TEXT);
                $st->bindValue(':key', $k, SQLITE3_TEXT);
                $st->execute();
            }
            $message = "<p class='success'>Configuration updated.</p>";
        } else {
            $message = '';
        }

        $configs = $db->query("SELECT * FROM config ORDER BY id ASC");
        $html = "<header><h1>Site Config</h1></header>
                 <nav>
                     <a href='admin.php?action=dashboard'>Home</a>
                     <a href='admin.php?action=list_pages'>Pages</a>
                     <a href='admin.php?action=list_blog'>Blog</a>
                     <a href='admin.php?action=list_themes'>Themes</a>
                     <a href='admin.php?action=list_config'>Config</a>
                     <a href='admin.php?action=list_media'>Media</a>
                     <a href='admin.php?action=logout'>Logout</a>
                 </nav>
                 $message
                 <form method='post'>
                 <table>
                 <tr><th>Key</th><th>Value</th></tr>";

        while ($row = $configs->fetchArray(SQLITE3_ASSOC)) {
            $key = htmlspecialchars($row['key']);
            $val = htmlspecialchars($row['value']);
            $html .= "<tr>
                        <td>$key</td>
                        <td><input type='text' name='config[$key]' value='$val'></td>
                      </tr>";
        }
        $html .= "</table>
                  <button type='submit' name='save_config'>Save</button>
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


/**
 * Helper function: buildBlockEditorHtml
 * Used in 'edit_page' or 'edit_blog' to render existing blocks
 */
function buildBlockEditorHtml($db, $index, $bType, $blockData, $blockTypes) {
    // Gather the entire media library for <select> usage:
    $mediaItems = getAllMedia($db);

    $out = "<div class='block-item'>";

    // Block Type Select
    $out .= "<label>Block Type:</label>
             <select name='blocks[$index][type]' onchange='onBlockTypeChange($index, this.value)'>";

    foreach ($blockTypes as $typeKey => $def) {
        $selected = ($typeKey === $bType) ? 'selected' : '';
        $label = $def['label'];
        $out .= "<option value='$typeKey' $selected>$label</option>";
    }
    $out .= "</select>";

    // Fields
    if (isset($blockTypes[$bType])) {
        foreach ($blockTypes[$bType]['fields'] as $fieldKey => $fieldDef) {
            $val = $blockData[$fieldKey] ?? '';
            $valEsc = htmlspecialchars($val);
            $fLabel = $fieldDef['label'];
            $fType = $fieldDef['type'];

            $out .= "<label>$fLabel</label>";
            if ($fType === 'text') {
                $out .= "<input type='text' name='blocks[$index][$fieldKey]' value='$valEsc'>";
            } elseif ($fType === 'textarea') {
                $out .= "<textarea name='blocks[$index][$fieldKey]' rows='3'>$valEsc</textarea>";
            } elseif ($fType === 'media') {
                // Build a <select> from $mediaItems
                $out .= "<select name='blocks[$index][$fieldKey]'>
                         <option value=''>-- select media --</option>";
                foreach ($mediaItems as $m) {
                    $selected = ($m['filepath'] === $val) ? 'selected' : '';
                    $safeFn   = htmlspecialchars($m['filename']);
                    $safeFp   = htmlspecialchars($m['filepath']);
                    $out .= "<option value='$safeFp' $selected>$safeFn</option>";
                }
                $out .= "</select>";
            }
        }
    }

    $out .= "</div>";
    return $out;
}
?>

