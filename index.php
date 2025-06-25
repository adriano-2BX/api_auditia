<?php
// ARQUIVO: api/db.php
// Este arquivo lida com a conexão com o banco de dados.
// Preencha com suas credenciais do MySQL quando as tiver.

function getDbConnection() {
    $host = '127.0.0.1';      // ou o host do seu banco de dados
    $dbname = 'auditia_db'; // o nome do seu banco de dados
    $user = 'root';         // seu usuário do banco de dados
    $pass = '';             // sua senha do banco de dados
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        return new PDO($dsn, $user, $pass, $options);
    } catch (\PDOException $e) {
        // Em um ambiente de produção, você deve registrar este erro em vez de exibi-lo.
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
        exit;
    }
}
?>
```php
<?php
// ARQUIVO: api/index.php
// Este é o ponto de entrada principal (roteador) para a API.
// Ele direciona as requisições para os arquivos apropriados com base no endpoint.

header("Access-Control-Allow-Origin: *"); // Permite acesso de qualquer origem (ajuste para produção)
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// O roteamento é baseado no parâmetro 'request' da URL.
// Ex: /api/index.php?request=clients ou /api/clients
$requestUri = trim($_SERVER['REQUEST_URI'], '/');
$requestParts = explode('?', $requestUri);
$path = $requestParts[0];
$pathParts = explode('/', $path);

// Encontra o endpoint a partir da URL. Assume que a API está em um diretório 'api'.
$endpoint = $pathParts[count($pathParts) - 1];
$id = isset($pathParts[count($pathParts)]) ? $pathParts[count($pathParts)] : null;


// Tratamento para requisição OPTIONS (pre-flight CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Direciona para o handler correto.
switch ($endpoint) {
    case 'login':
        require __DIR__ . '/auth.php';
        handleLogin();
        break;
    case 'clients':
        require __DIR__ . '/clients.php';
        handleClientsRequest();
        break;
    case 'projects':
        require __DIR__ . '/projects.php';
        handleProjectsRequest();
        break;
    case 'users':
        require __DIR__ . '/users.php';
        handleUsersRequest();
        break;
    case 'test-cases':
        require __DIR__ . '/tests.php';
        handleTestsRequest();
        break;
    case 'reports':
        require __DIR__ . '/reports.php';
        handleReportsRequest();
        break;
    case 'custom-templates':
        require __DIR__ . '/templates.php';
        handleTemplatesRequest();
        break;
    case 'webhooks':
        require __DIR__ . '/webhooks.php';
        handleWebhooksRequest();
        break;
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
        break;
}
?>
```php
<?php
// ARQUIVO: api/auth.php
// Lida com a autenticação de usuários.

require_once 'db.php';

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
        // Senha correta. Em uma aplicação real, você geraria um JWT (JSON Web Token) aqui.
        // Por simplicidade, retornamos os dados do usuário (sem o hash da senha).
        unset($user['password_hash']);
        http_response_code(200);
        echo json_encode($user);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
    }
}
?>
```php
<?php
// ARQUIVO: api/clients.php
// Lida com as operações CRUD para Clientes.

require_once 'db.php';

function handleClientsRequest() {
    $method = $_SERVER['REQUEST_METHOD'];
    $path = explode('/', trim($_SERVER['PATH_INFO'] ?? '', '/'));
    $id = $path[1] ?? null;

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
            echo json_encode($stmt->fetch());
            break;
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'));
            $stmt = $pdo->prepare("UPDATE clients SET name = ? WHERE id = ?");
            $stmt->execute([$data->name, $id]);
            echo json_encode(['success' => true]);
            break;
        case 'DELETE':
             // Também deleta projetos e casos de teste associados (cascade)
            $pdo->beginTransaction();
            try {
                $pdo->prepare("DELETE FROM projects WHERE clientId = ?")->execute([$id]);
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
?>
```php
<?php
// ARQUIVO: api/projects.php
// Lida com as operações CRUD para Projetos.

require_once 'db.php';

function handleProjectsRequest() {
    $method = $_SERVER['REQUEST_METHOD'];
    $path = explode('/', trim($_SERVER['PATH_INFO'] ?? '', '/'));
    $id = $path[1] ?? null;

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
            // Retorna o novo projeto criado
            $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
            $stmt->execute([$newId]);
            echo json_encode($stmt->fetch());
            break;
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'));
            $sql = "UPDATE projects SET name = ?, clientId = ?, whatsappNumber = ?, description = ?, objective = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$data->name, $data->clientId, $data->whatsappNumber, $data->description, $data->objective, $id]);
            echo json_encode(['success' => true]);
            break;
        case 'DELETE':
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
?>
```php
<?php
// ARQUIVO: api/users.php
// Lida com as operações CRUD para Usuários.

require_once 'db.php';

function handleUsersRequest() {
    $method = $_SERVER['REQUEST_METHOD'];
    $path = explode('/', trim($_SERVER['PATH_INFO'] ?? '', '/'));
    $id = $path[1] ?? null;

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
            // Retorna o novo usuário criado (sem a senha)
            $stmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
            $stmt->execute([$newId]);
            echo json_encode($stmt->fetch());
            break;
        case 'PUT':
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
?>
```php
<?php
// ARQUIVO: api/tests.php
// Lida com as operações para Casos de Teste.

require_once 'db.php';
require_once 'webhooks.php';

function handleTestsRequest() {
    $method = $_SERVER['REQUEST_METHOD'];
    $path = explode('/', trim($_SERVER['PATH_INFO'] ?? '', '/'));
    $id = $path[1] ?? null;
    $action = $path[2] ?? null;

    $pdo = getDbConnection();

    switch ($method) {
        case 'GET':
            $sql = "SELECT * FROM test_cases";
            if ($id) {
                $stmt = $pdo->prepare("$sql WHERE id = ?");
                $stmt->execute([$id]);
                $testCase = $stmt->fetch();
                $testCase['customFields'] = json_decode($testCase['customFields']);
                $testCase['pausedState'] = json_decode($testCase['pausedState']);
                echo json_encode($testCase);
            } else {
                $stmt = $pdo->query("$sql ORDER BY id DESC");
                $testCases = $stmt->fetchAll();
                foreach($testCases as &$tc) {
                   $tc['customFields'] = json_decode($tc['customFields']);
                   $tc['pausedState'] = json_decode($tc['pausedState']);
                }
                echo json_encode($testCases);
            }
            break;
        case 'POST':
             // Ação especial para executar um teste
            if ($id && $action === 'execute') {
                executeTest($pdo, $id);
            } else {
                createTest($pdo);
            }
            break;
        case 'PUT':
            // Ação para pausar ou atualizar
            updateTest($pdo, $id);
            break;
        case 'DELETE':
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
    $data = json_decode(file_get_contents('php://input'));
    $sql = "INSERT INTO test_cases (typeId, projectId, status, assignedTo, customFields, pausedState) VALUES (?, ?, 'pending', ?, ?, null)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$data->typeId, $data->projectId, $data->assignedTo, json_encode($data->customFields ?? [])]);
    $newId = $pdo->lastInsertId();
    
    // Retorna o novo teste criado
    $stmt = $pdo->prepare("SELECT * FROM test_cases WHERE id = ?");
    $stmt->execute([$newId]);
    $testCase = $stmt->fetch();
    $testCase['customFields'] = json_decode($testCase['customFields']);
    triggerWebhookEvent('test_created', ['testCase' => $testCase]);
    echo json_encode($testCase);
}

function updateTest($pdo, $id) {
    $data = json_decode(file_get_contents('php://input'));
    $sql = "UPDATE test_cases SET status = ?, pausedState = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$data->status, json_encode($data->pausedState), $id]);
    echo json_encode(['success' => true]);
}

function executeTest($pdo, $id) {
    $data = json_decode(file_get_contents('php://input'));
    $pdo->beginTransaction();
    try {
        // Atualiza o status do teste para 'completed'
        $stmt = $pdo->prepare("UPDATE test_cases SET status = 'completed', pausedState = null WHERE id = ?");
        $stmt->execute([$id]);

        // Pega informações para o relatório
        $stmt = $pdo->prepare("SELECT projectId FROM test_cases WHERE id = ?");
        $stmt->execute([$id]);
        $testCase = $stmt->fetch();

        $stmt = $pdo->prepare("SELECT clientId FROM projects WHERE id = ?");
        $stmt->execute([$testCase['projectId']]);
        $project = $stmt->fetch();

        // Cria o relatório
        $sql = "INSERT INTO reports (testCaseId, testerId, clientId, projectId, executionDate, results) VALUES (?, ?, ?, ?, NOW(), ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id, $data->userId, $project['clientId'], $testCase['projectId'], json_encode($data->results)]);
        $reportId = $pdo->lastInsertId();

        $pdo->commit();

        // Trigger Webhook
        $stmt = $pdo->prepare("SELECT * FROM reports WHERE id = ?");
        $stmt->execute([$reportId]);
        $report = $stmt->fetch();
        $report['results'] = json_decode($report['results']);
        triggerWebhookEvent('test_completed', ['report' => $report]);

        echo json_encode(['success' => true, 'reportId' => $reportId]);

    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to execute test.', 'details' => $e->getMessage()]);
    }
}
?>
```php
<?php
// ARQUIVO: api/reports.php
// Lida com a listagem de Relatórios.

require_once 'db.php';

function handleReportsRequest() {
    $method = $_SERVER['REQUEST_METHOD'];
    $path = explode('/', trim($_SERVER['PATH_INFO'] ?? '', '/'));
    $id = $path[1] ?? null;

    $pdo = getDbConnection();

    switch ($method) {
        case 'GET':
            $sql = "SELECT r.*, tc.typeId, p.name as projectName, c.name as clientName, u.name as testerName
                    FROM reports r
                    JOIN test_cases tc ON r.testCaseId = tc.id
                    JOIN projects p ON r.projectId = p.id
                    JOIN clients c ON r.clientId = c.id
                    JOIN users u ON r.testerId = u.id";
            if ($id) {
                $stmt = $pdo->prepare("$sql WHERE r.id = ?");
                $stmt->execute([$id]);
                $report = $stmt->fetch();
                $report['results'] = json_decode($report['results']);
                echo json_encode($report);
            } else {
                $stmt = $pdo->query("$sql ORDER BY r.executionDate DESC");
                $reports = $stmt->fetchAll();
                foreach($reports as &$r) {
                   $r['results'] = json_decode($r['results']);
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
?>
```php
<?php
// ARQUIVO: api/templates.php
// Lida com as operações CRUD para Modelos de Teste Personalizados.

require_once 'db.php';

function handleTemplatesRequest() {
    $method = $_SERVER['REQUEST_METHOD'];
    $path = explode('/', trim($_SERVER['PATH_INFO'] ?? '', '/'));
    $id = $path[1] ?? null;

    $pdo = getDbConnection();

    switch ($method) {
        case 'GET':
            if ($id) {
                $stmt = $pdo->prepare("SELECT * FROM custom_test_templates WHERE id = ?");
                $stmt->execute([$id]);
                $template = $stmt->fetch();
                $template['formFields'] = json_decode($template['formFields']);
                echo json_encode($template);
            } else {
                $stmt = $pdo->query("SELECT * FROM custom_test_templates ORDER BY name");
                $templates = $stmt->fetchAll();
                foreach($templates as &$t) {
                   $t['formFields'] = json_decode($t['formFields']);
                }
                echo json_encode($templates);
            }
            break;
        case 'POST':
            $data = json_decode(file_get_contents('php://input'));
            $sql = "INSERT INTO custom_test_templates (name, description, category, formFields) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$data->name, $data->description, $data->category, json_encode($data->formFields)]);
            $newId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("SELECT * FROM custom_test_templates WHERE id = ?");
            $stmt->execute([$newId]);
            $template = $stmt->fetch();
            $template['formFields'] = json_decode($template['formFields']);
            echo json_encode($template);
            break;
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'));
            $sql = "UPDATE custom_test_templates SET name = ?, description = ?, category = ?, formFields = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$data->name, $data->description, $data->category, json_encode($data->formFields), $id]);
            echo json_encode(['success' => true]);
            break;
        case 'DELETE':
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
?>
```php
<?php
// ARQUIVO: api/webhooks.php
// Lida com as operações CRUD para Webhooks e o disparo de eventos.

require_once 'db.php';

function handleWebhooksRequest() {
    $method = $_SERVER['REQUEST_METHOD'];
    $path = explode('/', trim($_SERVER['PATH_INFO'] ?? '', '/'));
    $id = $path[1] ?? null;

    $pdo = getDbConnection();

    switch ($method) {
        case 'GET':
            $sql = "SELECT * FROM webhooks";
            if ($id) {
                $stmt = $pdo->prepare("$sql WHERE id = ?");
                $stmt->execute([$id]);
                $webhook = $stmt->fetch();
                $webhook['events'] = json_decode($webhook['events']);
                echo json_encode($webhook);
            } else {
                $stmt = $pdo->query($sql);
                $webhooks = $stmt->fetchAll();
                foreach($webhooks as &$wh) {
                   $wh['events'] = json_decode($wh['events']);
                }
                echo json_encode($webhooks);
            }
            break;
        case 'POST':
            $data = json_decode(file_get_contents('php://input'));
            $sql = "INSERT INTO webhooks (url, events) VALUES (?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$data->url, json_encode($data->events)]);
            $newId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("SELECT * FROM webhooks WHERE id = ?");
            $stmt->execute([$newId]);
            $webhook = $stmt->fetch();
            $webhook['events'] = json_decode($webhook['events']);
            echo json_encode($webhook);
            break;
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'));
            $sql = "UPDATE webhooks SET url = ?, events = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$data->url, json_encode($data->events), $id]);
            echo json_encode(['success' => true]);
            break;
        case 'DELETE':
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
    $pdo = getDbConnection();
    // Encontra webhooks que assinam este evento
    $stmt = $pdo->prepare("SELECT * FROM webhooks WHERE JSON_CONTAINS(events, ?)");
    $stmt->execute(['"'.$eventName.'"']);
    $webhooks = $stmt->fetchAll();

    foreach ($webhooks as $webhook) {
        $ch = curl_init($webhook['url']);
        $body = json_encode([
            'event' => $eventName,
            'triggeredAt' => date('c'),
            'payload' => $payload
        ]);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($body)
        ]);
        // Em produção, você pode querer adicionar timeouts e tratamento de erros
        // curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        // curl_exec é assíncrono por padrão se você não esperar pela resposta.
        // Para um sistema mais robusto, considere usar uma fila de background.
        curl_exec($ch);
        curl_close($ch);
    }
}
?>
```sql
-- ARQUIVO: database.sql
-- Script SQL para criar todas as tabelas necessárias no banco de dados MySQL.

CREATE DATABASE IF NOT EXISTS `auditia_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `auditia_db`;

-- Tabela de Clientes
CREATE TABLE `clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Projetos
CREATE TABLE `projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `clientId` int(11) NOT NULL,
  `whatsappNumber` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `objective` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `clientId` (`clientId`),
  CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`clientId`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Usuários
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','tester','client') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'tester',
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Modelos de Teste Personalizados
CREATE TABLE `custom_test_templates` (
  `id` varchar(50) NOT NULL, -- ex: CUSTOM-1662586518
  `name` varchar(255) NOT NULL,
  `description` text,
  `category` varchar(100) NOT NULL,
  `formFields` json NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Casos de Teste
CREATE TABLE `test_cases` (
  `id` varchar(50) NOT NULL, -- ex: TEST-001
  `typeId` varchar(50) NOT NULL COMMENT 'ID do preset ou do modelo customizado',
  `projectId` int(11) NOT NULL,
  `status` enum('pending','completed','paused') NOT NULL DEFAULT 'pending',
  `assignedTo` int(11) NOT NULL,
  `customFields` json DEFAULT NULL,
  `pausedState` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `projectId` (`projectId`),
  KEY `assignedTo` (`assignedTo`),
  CONSTRAINT `test_cases_ibfk_1` FOREIGN KEY (`projectId`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `test_cases_ibfk_2` FOREIGN KEY (`assignedTo`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Tabela de Relatórios
CREATE TABLE `reports` (
  `id` varchar(50) NOT NULL, -- ex: REP-1662586518
  `testCaseId` varchar(50) NOT NULL,
  `testerId` int(11) NOT NULL,
  `clientId` int(11) NOT NULL,
  `projectId` int(11) NOT NULL,
  `executionDate` datetime NOT NULL,
  `results` json NOT NULL,
  PRIMARY KEY (`id`),
  KEY `testerId` (`testerId`),
  KEY `clientId` (`clientId`),
  KEY `projectId` (`projectId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Tabela de Webhooks
CREATE TABLE `webhooks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `url` varchar(2048) NOT NULL,
  `events` json NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

```htaccess
# ARQUIVO: api/.htaccess
# Use este arquivo para habilitar URLs amigáveis (ex: /api/clients em vez de /api/index.php?request=clients)
# O seu servidor Apache precisa ter o mod_rewrite habilitado.

<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteRule ^(.*)$ index.php?request=$1 [QSA,L]
</IfModule>
