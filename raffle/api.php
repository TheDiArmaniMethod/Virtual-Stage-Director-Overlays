<?php
// ============================================================
// Virtual Stage Director — Raffle Registration API
// Handles all actions from plugin.js and register.html
//
// Actions (via ?action=):
//   open         POST  — clear list, open registration, store event config
//   close        POST  — close registration
//   register     POST  — submit a name (checks if open)
//   get_names    GET   — return current name list (for plugin.js polling)
//   status       GET   — return open/closed state + event config (for register.html)
//   remove       POST  — admin: remove one registrant by id
//   clear        POST  — admin: clear all registrants
//   list         POST  — admin: get all registrants with timestamps
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

require_once 'db.php';

try {
    initDB();
    $pdo = getDB();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$body   = json_decode(file_get_contents('php://input'), true) ?: [];

switch ($action) {

    // ------------------------------------------------------------------
    case 'open':
        if (($body['password'] ?? '') !== ADMIN_PASSWORD) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']); break;
        }
        // Clear registrations, mark open, store event config from plugin.js
        $pdo->exec("DELETE FROM registrations");
        $stmt = $pdo->prepare("
            UPDATE event_config SET
                is_open          = 1,
                org_name         = :org_name,
                event_name       = :event_name,
                logo_url         = :logo_url,
                color_primary    = :color_primary,
                color_secondary  = :color_secondary,
                color_accent     = :color_accent,
                color_highlight  = :color_highlight,
                confirmation_msg = :confirmation_msg,
                opened_at        = NOW(),
                closed_at        = NULL
            WHERE id = 1
        ");
        $stmt->execute([
            ':org_name'         => substr($body['orgName']         ?? '', 0, 255),
            ':event_name'       => substr($body['eventName']       ?? '', 0, 255),
            ':logo_url'         => substr($body['logoUrl']         ?? '', 0, 1000),
            ':color_primary'    => substr($body['colorPrimary']    ?? '#432818', 0, 7),
            ':color_secondary'  => substr($body['colorSecondary']  ?? '#345830', 0, 7),
            ':color_accent'     => substr($body['colorAccent']     ?? '#ffe6a7', 0, 7),
            ':color_highlight'  => substr($body['colorHighlight']  ?? '#941b0c', 0, 7),
            ':confirmation_msg' => substr($body['confirmationMsg'] ?? "Congratulations! You're Entered to Win!", 0, 500)
        ]);
        echo json_encode(['success' => true]);
        break;

    // ------------------------------------------------------------------
    case 'close':
        if (($body['password'] ?? '') !== ADMIN_PASSWORD) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']); break;
        }
        $pdo->exec("UPDATE event_config SET is_open = 0, closed_at = NOW() WHERE id = 1");
        echo json_encode(['success' => true]);
        break;

    // ------------------------------------------------------------------
    case 'register':
        $config = $pdo->query("SELECT is_open FROM event_config WHERE id = 1")->fetch();
        if (!$config || !$config['is_open']) {
            echo json_encode(['success' => false, 'error' => 'Registration is currently closed.']);
            break;
        }
        $name = trim($body['name'] ?? '');
        if (empty($name)) {
            echo json_encode(['success' => false, 'error' => 'Name is required.']);
            break;
        }
        if (strlen($name) > 255) {
            echo json_encode(['success' => false, 'error' => 'Name is too long.']);
            break;
        }
        $pdo->prepare("INSERT INTO registrations (name) VALUES (:name)")->execute([':name' => $name]);
        echo json_encode(['success' => true]);
        break;

    // ------------------------------------------------------------------
    case 'get_names':
        // Used by plugin.js polling — returns plain array of names
        $names = $pdo->query("SELECT name FROM registrations ORDER BY registered_at ASC")
                      ->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode(['success' => true, 'names' => $names]);
        break;

    // ------------------------------------------------------------------
    case 'status':
        // Used by register.html on load — returns config + open state
        $config = $pdo->query("SELECT * FROM event_config WHERE id = 1")->fetch();
        $count  = (int)$pdo->query("SELECT COUNT(*) FROM registrations")->fetchColumn();
        echo json_encode([
            'success'         => true,
            'isOpen'          => (bool)$config['is_open'],
            'orgName'         => $config['org_name'],
            'eventName'       => $config['event_name'],
            'logoUrl'         => $config['logo_url'],
            'colorPrimary'    => $config['color_primary'],
            'colorSecondary'  => $config['color_secondary'],
            'colorAccent'     => $config['color_accent'],
            'colorHighlight'  => $config['color_highlight'],
            'confirmationMsg' => $config['confirmation_msg'],
            'registrantCount' => $count
        ]);
        break;

    // ------------------------------------------------------------------
    case 'remove':
        if (($body['password'] ?? '') !== ADMIN_PASSWORD) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']); break;
        }
        $pdo->prepare("DELETE FROM registrations WHERE id = :id")
            ->execute([':id' => (int)($body['id'] ?? 0)]);
        echo json_encode(['success' => true]);
        break;

    // ------------------------------------------------------------------
    case 'clear':
        if (($body['password'] ?? '') !== ADMIN_PASSWORD) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']); break;
        }
        $pdo->exec("DELETE FROM registrations");
        echo json_encode(['success' => true]);
        break;

    // ------------------------------------------------------------------
    case 'list':
        $pw = $body['password'] ?? $_GET['password'] ?? '';
        if ($pw !== ADMIN_PASSWORD) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']); break;
        }
        $rows = $pdo->query("SELECT id, name, registered_at FROM registrations ORDER BY registered_at ASC")->fetchAll();
        echo json_encode(['success' => true, 'registrants' => $rows]);
        break;

    // ------------------------------------------------------------------
    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action: ' . htmlspecialchars($action)]);
        break;
}
?>
