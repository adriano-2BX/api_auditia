<?php
// ARQUIVO: api/index.php
// API Completa para AuditIA Manager em um único arquivo.

// =================================================================
// 1. CABEÇALHOS E CONFIGURAÇÃO INICIAL
// =================================================================
header("Access-Control-Allow-Origin: *"); // Permite acesso de qualquer origem (ajuste para produção)
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Tratamento para requisição OPTIONS (pre-flight CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}


// =================================================================
// 2. CONEXÃO COM O BANCO DE DADOS
// =================================================================

/**
 * Estabelece e retorna uma conexão PDO com o banco de dados.
 * As credenciais foram preenchidas conforme fornecido.
 * @return PDO
 */
function getDbConnection() {
    $host = 'server.2bx.com.br'; // Host do seu banco de dados
    $port = '3306';              // Porta do seu banco de dados
    $dbname = 'auditia_db';      // O nome do seu banco de dados
    $user = 'root';              // Seu usuário do banco de dados
    $pass = 'd21d846891a08dfaa82b'; // Sua senha do banco de dados
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        return new PDO($dsn, $user, $pass, $options);
    } catch (\PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
        exit;
    }
}


// =================================================================
// 3. FUNÇÕES DE LÓGICA (HANDLERS) POR ENDPOINT
// =================================================================

// -------------------- AUTENTICAÇÃO (LOGIN) --------------------
function handleLogin() {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'));
    if (!isset($data->email) || !isset($data->password)) {
        http_response_code(400);
        echo json_encode(['error' => 'Email and password are required']);
        return;
    }

    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT id, name, email, role, password_hash FROM users WHERE email = ?");
    $stmt->execute([$data->email]);
    $user = $stmt->fetch();

    if ($user && password_verify($data->password, $user['password_hash'])) {
        unset($user['password_hash']);
        http_response_code(200);
        echo json_encode($user);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
    }
}

// -------------------- CLIENTES (CLIENTS) --------------------
function handleClientsRequest($id) {
    $method = $_SERVER['REQUEST_METHOD'];
    $pdo = getDbConnection();

    switch ($method) {
        case 'GET':
            if ($id) {
                $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode($stmt->fetch());
            } else {
                $stmt = $pdo->query("SELECT * FROM clients ORDER BY name");
                echo json_encode($stmt->fetchAll());
            }
            break;
        case 'POST':
            $data = json_decode(file_get_contents('php://input'));
            $stmt = $pdo->prepare("INSERT INTO clients (name) VALUES (?)");
            $stmt->execute([$data->name]);
            $newId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
            $stmt->execute([$newId]);
            http_response_code(201);
            echo json_encode($stmt->fetch());
            break;
        case 'PUT':
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'Client ID is required']); return; }
            $data = json_decode(file_get_contents('php://input'));
            $stmt = $pdo->prepare("UPDATE clients SET name = ? WHERE id = ?");
            $stmt->execute([$data->name, $id]);
            echo json_encode(['success' => true]);
            break;
        case 'DELETE':
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'Client ID is required']); return; }
            $pdo->beginTransaction();
            try {
                $pdo->prepare("DELETE FROM clients WHERE id = ?")->execute([$id]);
                $pdo->commit();
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                $pdo->rollBack();
                http_response_code(500);
                echo json_encode(['error' => 'Failed to delete client and related data.']);
            }
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
            break;
    }
}

// -------------------- PROJETOS (PROJECTS) --------------------
function handleProjectsRequest($id) {
    $method = $_SERVER['REQUEST_METHOD'];
    $pdo = getDbConnection();

    switch ($method) {
        case 'GET':
            $sql = "SELECT p.*, c.name as clientName FROM projects p JOIN clients c ON p.clientId = c.id";
            if ($id) {
                $stmt = $pdo->prepare("$sql WHERE p.id = ?");
                $stmt->execute([$id]);
                echo json_encode($stmt->fetch());
            } else {
                $stmt = $pdo->query("$sql ORDER BY p.name");
                echo json_encode($stmt->fetchAll());
            }
            break;
        case 'POST':
            $data = json_decode(file_get_contents('php://input'));
            $sql = "INSERT INTO projects (name, clientId, whatsappNumber, description, objective) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$data->name, $data->clientId, $data->whatsappNumber, $data->description, $data->objective]);
            $newId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
            $stmt->execute([$newId]);
            http_response_code(201);
            echo json_encode($stmt->fetch());
            break;
        case 'PUT':
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'Project ID is required']); return; }
            $data = json_decode(file_get_contents('php://input'));
            $sql = "UPDATE projects SET name = ?, clientId = ?, whatsappNumber = ?, description = ?, objective = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$data->name, $data->clientId, $data->whatsappNumber, $data->description, $data->objective, $id]);
            echo json_encode(['success' => true]);
            break;
        case 'DELETE':
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'Project ID is required']); return; }
            $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
            break;
    }
}

// -------------------- USUÁRIOS (USERS) --------------------
function handleUsersRequest($id) {
    $method = $_SERVER['REQUEST_METHOD'];
    $pdo = getDbConnection();

    switch ($method) {
        case 'GET':
            $sql = "SELECT id, name, email, role FROM users";
            if ($id) {
                $stmt = $pdo->prepare("$sql WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode($stmt->fetch());
            } else {
                $stmt = $pdo->query("$sql ORDER BY name");
                echo json_encode($stmt->fetchAll());
            }
            break;
        case 'POST':
            $data = json_decode(file_get_contents('php://input'));
            $password_hash = password_hash($data->password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (name, email, role, password_hash) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$data->name, $data->email, $data->role, $password_hash]);
            $newId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
            $stmt->execute([$newId]);
            http_response_code(201);
            echo json_encode($stmt->fetch());
            break;
        case 'PUT':
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'User ID is required']); return; }
            $data = json_decode(file_get_contents('php://input'));
            if (!empty($data->password)) {
                $password_hash = password_hash($data->password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET name = ?, email = ?, role = ?, password_hash = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$data->name, $data->email, $data->role, $password_hash, $id]);
            } else {
                $sql = "UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$data->name, $data->email, $data->role, $id]);
            }
            echo json_encode(['success' => true]);
            break;
        case 'DELETE':
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'User ID is required']); return; }
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
            break;
    }
}

// -------------------- TESTES (TEST CASES) --------------------
function handleTestsRequest($id, $action) {
    $method = $_SERVER['REQUEST_METHOD'];
    $pdo = getDbConnection();

    switch ($method) {
        case 'GET':
            $sql = "SELECT * FROM test_cases";
            if ($id) {
                $stmt = $pdo->prepare("$sql WHERE id = ?");
                $stmt->execute([$id]);
                $testCase = $stmt->fetch();
                if ($testCase) {
                    $testCase['customFields'] = json_decode($testCase['customFields'], true);
                    $testCase['pausedState'] = json_decode($testCase['pausedState'], true);
                }
                echo json_encode($testCase);
            } else {
                $stmt = $pdo->query("$sql ORDER BY id DESC");
                $testCases = $stmt->fetchAll();
                foreach($testCases as &$tc) {
                   $tc['customFields'] = json_decode($tc['customFields'], true);
                   $tc['pausedState'] = json_decode($tc['pausedState'], true);
                }
                echo json_encode($testCases);
            }
            break;
        case 'POST':
            if ($id && $action === 'execute') {
                executeTest($pdo, $id);
            } else {
                createTest($pdo);
            }
            break;
        case 'PUT':
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'Test Case ID is required']); return; }
            updateTest($pdo, $id);
            break;
        case 'DELETE':
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'Test Case ID is required']); return; }
            $stmt = $pdo->prepare("DELETE FROM test_cases WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
            break;
    }
}

function createTest($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    $testId = 'TEST-' . time();
    $sql = "INSERT INTO test_cases (id, typeId, projectId, status, assignedTo, customFields, pausedState) VALUES (?, ?, ?, 'pending', ?, ?, null)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$testId, $data['typeId'], $data['projectId'], $data['assignedTo'], json_encode($data['customFields'] ?? [])]);
    $stmt = $pdo->prepare("SELECT * FROM test_cases WHERE id = ?");
    $stmt->execute([$testId]);
    $testCase = $stmt->fetch();
    $testCase['customFields'] = json_decode($testCase['customFields'], true);
    triggerWebhookEvent('test_created', ['testCase' => $testCase]);
    http_response_code(201);
    echo json_encode($testCase);
}

function updateTest($pdo, $id) {
    $data = json_decode(file_get_contents('php://input'), true);
    $sql = "UPDATE test_cases SET status = ?, pausedState = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$data['status'], json_encode($data['pausedState']), $id]);
    echo json_encode(['success' => true]);
}

function executeTest($pdo, $id) {
    $data = json_decode(file_get_contents('php://input'), true);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE test_cases SET status = 'completed', pausedState = null WHERE id = ?");
        $stmt->execute([$id]);
        $stmt = $pdo->prepare("SELECT projectId FROM test_cases WHERE id = ?");
        $stmt->execute([$id]);
        $testCase = $stmt->fetch();
        $stmt = $pdo->prepare("SELECT clientId FROM projects WHERE id = ?");
        $stmt->execute([$testCase['projectId']]);
        $project = $stmt->fetch();
        $reportId = 'REP-' . time();
        $sql = "INSERT INTO reports (id, testCaseId, testerId, clientId, projectId, executionDate, results) VALUES (?, ?, ?, ?, ?, NOW(), ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$reportId, $id, $data['userId'], $project['clientId'], $testCase['projectId'], json_encode($data['results'])]);
        $pdo->commit();
        $stmt = $pdo->prepare("SELECT * FROM reports WHERE id = ?");
        $stmt->execute([$reportId]);
        $report = $stmt->fetch();
        $report['results'] = json_decode($report['results'], true);
        triggerWebhookEvent('test_completed', ['report' => $report]);
        echo json_encode(['success' => true, 'reportId' => $reportId]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to execute test.', 'details' => $e->getMessage()]);
    }
}

// -------------------- RELATÓRIOS (REPORTS) --------------------
function handleReportsRequest($id) {
    $method = $_SERVER['REQUEST_METHOD'];
    $pdo = getDbConnection();

    switch ($method) {
        case 'GET':
            $sql = "SELECT r.*, tc.typeId, p.name as projectName, c.name as clientName, u.name as testerName
                    FROM reports r
                    LEFT JOIN test_cases tc ON r.testCaseId = tc.id
                    LEFT JOIN projects p ON r.projectId = p.id
                    LEFT JOIN clients c ON r.clientId = c.id
                    LEFT JOIN users u ON r.testerId = u.id";
            if ($id) {
                $stmt = $pdo->prepare("$sql WHERE r.id = ?");
                $stmt->execute([$id]);
                $report = $stmt->fetch();
                if ($report) {
                    $report['results'] = json_decode($report['results'], true);
                }
                echo json_encode($report);
            } else {
                $stmt = $pdo->query("$sql ORDER BY r.executionDate DESC");
                $reports = $stmt->fetchAll();
                foreach($reports as &$r) {
                   $r['results'] = json_decode($r['results'], true);
                }
                echo json_encode($reports);
            }
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed (Reports are created via test execution)']);
            break;
    }
}

// -------------------- MODELOS (CUSTOM TEMPLATES) --------------------
function handleTemplatesRequest($id) {
    $method = $_SERVER['REQUEST_METHOD'];
    $pdo = getDbConnection();

    switch ($method) {
        case 'GET':
            if ($id) {
                $stmt = $pdo->prepare("SELECT * FROM custom_test_templates WHERE id = ?");
                $stmt->execute([$id]);
                $template = $stmt->fetch();
                if ($template) {
                    $template['formFields'] = json_decode($template['formFields'], true);
                }
                echo json_encode($template);
            } else {
                $stmt = $pdo->query("SELECT * FROM custom_test_templates ORDER BY name");
                $templates = $stmt->fetchAll();
                foreach($templates as &$t) {
                   $t['formFields'] = json_decode($t['formFields'], true);
                }
                echo json_encode($templates);
            }
            break;
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            $templateId = 'CUSTOM-' . time();
            $sql = "INSERT INTO custom_test_templates (id, name, description, category, formFields) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$templateId, $data['name'], $data['description'], $data['category'], json_encode($data['formFields'])]);
            $stmt = $pdo->prepare("SELECT * FROM custom_test_templates WHERE id = ?");
            $stmt->execute([$templateId]);
            $template = $stmt->fetch();
            $template['formFields'] = json_decode($template['formFields'], true);
            http_response_code(201);
            echo json_encode($template);
            break;
        case 'PUT':
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'Template ID is required']); return; }
            $data = json_decode(file_get_contents('php://input'), true);
            $sql = "UPDATE custom_test_templates SET name = ?, description = ?, category = ?, formFields = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$data['name'], $data['description'], $data['category'], json_encode($data['formFields']), $id]);
            echo json_encode(['success' => true]);
            break;
        case 'DELETE':
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'Template ID is required']); return; }
            $stmt = $pdo->prepare("DELETE FROM custom_test_templates WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
            break;
    }
}

// -------------------- WEBHOOKS --------------------
function handleWebhooksRequest($id) {
    $method = $_SERVER['REQUEST_METHOD'];
    $pdo = getDbConnection();

    switch ($method) {
        case 'GET':
            $sql = "SELECT * FROM webhooks";
            if ($id) {
                $stmt = $pdo->prepare("$sql WHERE id = ?");
                $stmt->execute([$id]);
                $webhook = $stmt->fetch();
                if ($webhook) {
                    $webhook['events'] = json_decode($webhook['events'], true);
                }
                echo json_encode($webhook);
            } else {
                $stmt = $pdo->query($sql);
                $webhooks = $stmt->fetchAll();
                foreach($webhooks as &$wh) {
                   $wh['events'] = json_decode($wh['events'], true);
                }
                echo json_encode($webhooks);
            }
            break;
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            $sql = "INSERT INTO webhooks (url, events) VALUES (?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$data['url'], json_encode($data['events'])]);
            $newId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("SELECT * FROM webhooks WHERE id = ?");
            $stmt->execute([$newId]);
            $webhook = $stmt->fetch();
            $webhook['events'] = json_decode($webhook['events'], true);
            http_response_code(201);
            echo json_encode($webhook);
            break;
        case 'PUT':
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'Webhook ID is required']); return; }
            $data = json_decode(file_get_contents('php://input'), true);
            $sql = "UPDATE webhooks SET url = ?, events = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$data['url'], json_encode($data['events']), $id]);
            echo json_encode(['success' => true]);
            break;
        case 'DELETE':
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'Webhook ID is required']); return; }
            $stmt = $pdo->prepare("DELETE FROM webhooks WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
            break;
    }
}

function triggerWebhookEvent($eventName, $payload) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT url FROM webhooks WHERE JSON_CONTAINS(events, :event)");
        $stmt->execute([':event' => '"'.$eventName.'"']);
        $webhooks = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($webhooks as $url) {
            $ch = curl_init($url);
            $body = json_encode([
                'event' => $eventName,
                'triggeredAt' => date('c'),
                'payload' => $payload
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Content-Length: ' . strlen($body)]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_exec($ch);
            curl_close($ch);
        }
    } catch(Exception $e) {
        // error_log("Failed to trigger webhooks: " . $e->getMessage());
    }
}


// =================================================================
// 4. ROTEADOR PRINCIPAL
// =================================================================

$requestUri = trim($_SERVER['REQUEST_URI'], '/');
$requestParts = explode('?', $requestUri);
$path = $requestParts[0];
$pathParts = explode('/', $path);

// Encontra o endpoint a partir da URL. Assume que a API está em um diretório 'api'.
$endpointIndex = array_search('api', $pathParts);
if ($endpointIndex === false) {
    http_response_code(400);
    echo json_encode(['error' => "API path not found in URL. Make sure you are accessing via /api/your-endpoint."]);
    exit;
}

$endpoint = $pathParts[$endpointIndex + 1] ?? null;
$id = $pathParts[$endpointIndex + 2] ?? null;
$action = $pathParts[$endpointIndex + 3] ?? null;

switch ($endpoint) {
    case 'login':
        handleLogin();
        break;
    case 'clients':
        handleClientsRequest($id);
        break;
    case 'projects':
        handleProjectsRequest($id);
        break;
    case 'users':
        handleUsersRequest($id);
        break;
    case 'test-cases':
        handleTestsRequest($id, $action);
        break;
    case 'reports':
        handleReportsRequest($id);
        break;
    case 'custom-templates':
        handleTemplatesRequest($id);
        break;
    case 'webhooks':
        handleWebhooksRequest($id);
        break;
    case null:
        http_response_code(404);
        echo json_encode(['error' => 'API endpoint not specified.']);
        break;
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found', 'endpoint_requested' => $endpoint]);
        break;
}

?>
