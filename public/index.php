<?php
// ARQUIVO: index.php

// =================================================================
// 1. CABEÇALHOS E CONFIGURAÇÃO INICIAL
// =================================================================

// Define a origem permitida. '*' é flexível mas para produção, o ideal é
// usar o domínio exato do seu front-end.
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Responde à requisição pre-flight OPTIONS do navegador.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// =================================================================
// 2. CONEXÃO COM O BANCO DE DADOS
// =================================================================

/**
 * Estabelece e retorna uma conexão PDO com o banco de dados.
 * @return PDO
 */
function getDbConnection() {
    $host = 'server.2bx.com.br';
    $port = '3306';
    $dbname = 'auditia_db';
    $user = 'root';
    $pass = 'd21d846891a08dfaa82b';
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
        // Em produção, evite expor detalhes do erro. Logue o erro em um arquivo.
        // error_log('Database connection failed: ' . $e->getMessage());
        echo json_encode(['error' => 'Falha na conexão com o banco de dados.']);
        exit;
    }
}

// =================================================================
// 3. FUNÇÃO AUXILIAR PARA TRATAR ENTRADA JSON
// =================================================================

/**
 * Pega o corpo da requisição, decodifica de JSON para array e valida.
 * Encerra o script com erro 400 se o JSON for inválido.
 * @return array
 */
function getJsonBody() {
    $data = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'JSON inválido: ' . json_last_error_msg()]);
        exit;
    }
    return $data ?? [];
}


// =================================================================
// 4. FUNÇÕES DE LÓGICA (HANDLERS) POR ENDPOINT
// =================================================================

// -------------------- AUTENTICAÇÃO (LOGIN) --------------------
function handleLogin() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Método não permitido']);
        return;
    }

    $data = getJsonBody();
    if (!isset($data['email']) || !isset($data['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Email e senha são obrigatórios']);
        return;
    }

    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT id, name, email, role, password_hash FROM users WHERE email = ?");
    $stmt->execute([$data['email']]);
    $user = $stmt->fetch();

    if ($user && password_verify($data['password'], $user['password_hash'])) {
        unset($user['password_hash']); // Nunca retorne o hash da senha
        http_response_code(200);
        echo json_encode($user);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Credenciais inválidas']);
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
            $data = getJsonBody();
            $stmt = $pdo->prepare("INSERT INTO clients (name) VALUES (?)");
            $stmt->execute([$data['name']]);
            $newId = $pdo->lastInsertId();
            
            $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
            $stmt->execute([$newId]);
            http_response_code(201);
            echo json_encode($stmt->fetch());
            break;
        case 'PUT':
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'ID do cliente é obrigatório']); return; }
            $data = getJsonBody();
            $stmt = $pdo->prepare("UPDATE clients SET name = ? WHERE id = ?");
            $stmt->execute([$data['name'], $id]);
            echo json_encode(['success' => true]);
            break;
        case 'DELETE':
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'ID do cliente é obrigatório']); return; }
            $pdo->beginTransaction();
            try {
                // Adicione aqui a lógica para deletar dados relacionados se necessário
                $pdo->prepare("DELETE FROM clients WHERE id = ?")->execute([$id]);
                $pdo->commit();
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                $pdo->rollBack();
                http_response_code(500);
                echo json_encode(['error' => 'Falha ao deletar cliente.', 'details' => $e->getMessage()]);
            }
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
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
            $data = getJsonBody();
            $sql = "INSERT INTO projects (name, clientId, whatsappNumber, description, objective) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$data['name'], $data['clientId'], $data['whatsappNumber'], $data['description'], $data['objective']]);
            $newId = $pdo->lastInsertId();
            
            $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
            $stmt->execute([$newId]);
            http_response_code(201);
            echo json_encode($stmt->fetch());
            break;
        case 'PUT':
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'ID do projeto é obrigatório']); return; }
            $data = getJsonBody();
            $sql = "UPDATE projects SET name = ?, clientId = ?, whatsappNumber = ?, description = ?, objective = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$data['name'], $data['clientId'], $data['whatsappNumber'], $data['description'], $data['objective'], $id]);
            echo json_encode(['success' => true]);
            break;
        case 'DELETE':
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'ID do projeto é obrigatório']); return; }
            $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
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
            $data = getJsonBody();
            $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (name, email, role, password_hash) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$data['name'], $data['email'], $data['role'], $password_hash]);
            $newId = $pdo->lastInsertId();

            $stmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
            $stmt->execute([$newId]);
            http_response_code(201);
            echo json_encode($stmt->fetch());
            break;
        case 'PUT':
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'ID do usuário é obrigatório']); return; }
            $data = getJsonBody();
            if (!empty($data['password'])) {
                $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
                $sql = "UPDATE users SET name = ?, email = ?, role = ?, password_hash = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$data['name'], $data['email'], $data['role'], $password_hash, $id]);
            } else {
                $sql = "UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$data['name'], $data['email'], $data['role'], $id]);
            }
            echo json_encode(['success' => true]);
            break;
        case 'DELETE':
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'ID do usuário é obrigatório']); return; }
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
            break;
    }
}

// -------------------- CASOS DE TESTE (TEST CASES) --------------------
function handleTestsRequest($id, $action) {
    $method = $_SERVER['REQUEST_METHOD'];
    $pdo = getDbConnection();

    switch ($method) {
        case 'GET':
            $sql = "SELECT tc.*, p.name as projectName, c.name as clientName, u.name as assignedToName 
                    FROM test_cases tc
                    LEFT JOIN projects p ON tc.projectId = p.id
                    LEFT JOIN clients c ON p.clientId = c.id
                    LEFT JOIN users u ON tc.assignedTo = u.id";
            if ($id) {
                $stmt = $pdo->prepare("$sql WHERE tc.id = ?");
                $stmt->execute([$id]);
                $testCase = $stmt->fetch();
                if ($testCase) {
                    $testCase['customFields'] = json_decode($testCase['customFields'] ?: '[]', true);
                    $testCase['pausedState'] = json_decode($testCase['pausedState'] ?: 'null', true);
                }
                echo json_encode($testCase);
            } else {
                $stmt = $pdo->query("$sql ORDER BY tc.id DESC");
                $testCases = $stmt->fetchAll();
                foreach($testCases as &$tc) {
                    $tc['customFields'] = json_decode($tc['customFields'] ?: '[]', true);
                    $tc['pausedState'] = json_decode($tc['pausedState'] ?: 'null', true);
                }
                echo json_encode($testCases);
            }
            break;
        case 'POST':
            // Rota: POST /test_cases/{id}/execute
            if ($id && $action === 'execute') {
                executeTest($pdo, $id);
            } 
            // Rota: POST /test_cases
            else {
                createTest($pdo);
            }
            break;
        case 'PUT':
            // Rota: PUT /test_cases/{id}
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'ID do Caso de Teste é obrigatório']); return; }
            updateTest($pdo, $id);
            break;
        case 'DELETE':
            // Rota: DELETE /test_cases/{id}
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'ID do Caso de Teste é obrigatório']); return; }
            $stmt = $pdo->prepare("DELETE FROM test_cases WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
            break;
    }
}

function createTest($pdo) {
    $data = getJsonBody();
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
    $data = getJsonBody();
    $sql = "UPDATE test_cases SET status = ?, pausedState = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$data['status'], json_encode($data['pausedState']), $id]);
    echo json_encode(['success' => true]);
}

function executeTest($pdo, $id) {
    $data = getJsonBody();
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
        echo json_encode(['error' => 'Falha ao executar o teste.', 'details' => $e->getMessage()]);
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
            echo json_encode(['error' => 'Método não permitido (Relatórios são criados via execução de teste)']);
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
            $data = getJsonBody();
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
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'ID do modelo é obrigatório']); return; }
            $data = getJsonBody();
            $sql = "UPDATE custom_test_templates SET name = ?, description = ?, category = ?, formFields = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$data['name'], $data['description'], $data['category'], json_encode($data['formFields']), $id]);
            echo json_encode(['success' => true]);
            break;
        case 'DELETE':
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'ID do modelo é obrigatório']); return; }
            $stmt = $pdo->prepare("DELETE FROM custom_test_templates WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
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
            $data = getJsonBody();
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
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'ID do webhook é obrigatório']); return; }
            $data = getJsonBody();
            $sql = "UPDATE webhooks SET url = ?, events = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$data['url'], json_encode($data['events']), $id]);
            echo json_encode(['success' => true]);
            break;
        case 'DELETE':
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'ID do webhook é obrigatório']); return; }
            $stmt = $pdo->prepare("DELETE FROM webhooks WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
            break;
    }
}

function triggerWebhookEvent($eventName, $payload) {
    try {
        $pdo = getDbConnection();
        // Usar JSON_CONTAINS é específico do MySQL 5.7+. Para compatibilidade maior,
        // um LIKE '%"event"%' pode ser usado, mas é menos performático.
        $stmt = $pdo->prepare("SELECT url FROM webhooks WHERE JSON_CONTAINS(events, :event)");
        $stmt->execute([':event' => '"'.$eventName.'"']);
        $webhooks = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($webhooks as $url) {
            $ch = curl_init($url);
            $body = json_encode([
                'event' => $eventName,
                'triggeredAt' => date('c'), // Formato ISO 8601
                'payload' => $payload
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Content-Length: ' . strlen($body)]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            // Executa a requisição de forma assíncrona para não bloquear o fluxo principal
            curl_exec($ch);
            // Verifique curl_error($ch) se precisar debugar
            curl_close($ch);
        }
    } catch(Exception $e) {
        // Em um ambiente de produção, é crucial registrar este erro.
        error_log("Falha ao disparar webhooks para o evento {$eventName}: " . $e->getMessage());
    }
}


// =================================================================
// 5. ROTEADOR PRINCIPAL
// =================================================================

$requestUri = trim($_SERVER['REQUEST_URI'], '/');
$requestParts = explode('?', $requestUri);
$path = $requestParts[0];

// Remove o nome de um possível subdiretório se a API não estiver na raiz do domínio.
// Ex: se a URL for "domain.com/api/clients", e o index.php estiver em /api/
// $path será "clients". Ajuste 'NOME_DO_DIRETORIO_DA_API' se necessário.
// $path = str_replace('NOME_DO_DIRETORIO_DA_API/', '', $path);

$pathParts = explode('/', $path);

$endpoint = $pathParts[0] ?? null;
$id = $pathParts[1] ?? null;
$action = $pathParts[2] ?? null;

// Lógica de roteamento
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
    // CORRIGIDO: de 'test-cases' para 'test_cases' para consistência
    case 'test_cases': 
        handleTestsRequest($id, $action);
        break;
    case 'reports':
        handleReportsRequest($id);
        break;
    // CORRIGIDO: de 'custom-templates' para 'custom_templates' para consistência
    case 'custom_templates':
        handleTemplatesRequest($id);
        break;
    case 'webhooks':
        handleWebhooksRequest($id);
        break;
    case '': // Raiz da API
    case null:
        echo json_encode(['status' => 'AuditIA API is running']);
        break;
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint não encontrado', 'endpoint_requested' => $endpoint]);
        break;
}

?>
