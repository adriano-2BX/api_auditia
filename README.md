Claro, aqui está o conteúdo da documentação da API em formato Markdown, pronto para ser copiado.

````markdown
# API do AuditIA Manager

Esta é a documentação completa para a API backend do sistema AuditIA Manager. A API é responsável por todas as operações de dados, incluindo autenticação, gerenciamento de clientes, projetos, testes e relatórios.

A API é construída em PHP e utiliza um banco de dados MySQL para persistência de dados.

## Índice

1.  [Pré-requisitos](#pré-requisitos)
2.  [Configuração](#configuração)
    * [Banco de Dados](#banco-de-dados)
    * [Arquivo da API](#arquivo-da-api)
    * [Servidor Web (Apache)](#servidor-web-apache)
3.  [Endpoints da API](#endpoints-da-api)
    * [Autenticação](#1-autenticação)
    * [Clientes](#2-clientes)
    * [Projetos](#3-projetos)
    * [Usuários](#4-usuários)
    * [Casos de Teste](#5-casos-de-teste)
    * [Relatórios](#6-relatórios)
    * [Modelos Personalizados](#7-modelos-personalizados-custom-templates)
    * [Webhooks](#8-webhooks)
4.  [Schema do Banco de Dados](#schema-do-banco-de-dados)

---

## Pré-requisitos

* Servidor Web (Apache, Nginx, etc.)
* PHP >= 7.4 (com a extensão `pdo_mysql`)
* Servidor de Banco de Dados MySQL ou MariaDB

---

## Configuração

### Banco de Dados

1.  **Crie o Banco de Dados**: Utilize um cliente MySQL para criar o banco de dados.
    ```sql
    CREATE DATABASE IF NOT EXISTS `auditia_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    ```
2.  **Crie as Tabelas**: Execute o [script SQL completo](#schema-do-banco-de-dados) fornecido no final deste documento para criar todas as tabelas necessárias.

### Arquivo da API

1.  **Crie a Estrutura**: Crie um diretório chamado `api` na raiz do seu servidor web.
2.  **Adicione o Arquivo**: Dentro do diretório `api`, crie um arquivo chamado `index.php` e cole o [código-fonte completo da API](https://github.com/a/b/blob/main/api/index.php) nele.
3.  **Verifique as Credenciais**: Dentro de `index.php`, a função `getDbConnection()` já está pré-configurada com as credenciais que você forneceu. Certifique-se de que elas permaneçam corretas.

    ```php
    function getDbConnection() {
        $host = 'server.2bx.com.br';
        $port = '3306';
        $dbname = 'auditia_db';
        $user = 'root';
        $pass = 'd21d846891a08dfaa82b';
        // ...
    }
    ```

### Servidor Web (Apache)

Para que as URLs amigáveis (ex: `/api/clients/1` em vez de `/api/index.php?request=clients/1`) funcionem, crie um arquivo `.htaccess` dentro do diretório `api/` com o seguinte conteúdo:

```htaccess
<IfModule mod_rewrite.c>
  RewriteEngine On

  # Redireciona tudo que não for um arquivo ou diretório físico para o index.php
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteRule ^(.*)$ index.php [L,QSA]
</IfModule>
````

**Importante**: Certifique-se de que o módulo `mod_rewrite` do Apache está habilitado e que o seu Virtual Host permite a sobrescrita de regras (`AllowOverride All`).

-----

## Endpoints da API

A URL base para todos os endpoints é `http://seu-dominio.com/api`.

### 1\. Autenticação

#### `POST /login`

Autentica um usuário e retorna seus dados se as credenciais estiverem corretas.

**Corpo da Requisição (JSON):**

```json
{
  "email": "adriano@2bx.com.br",
  "password": "123"
}
```

**Resposta de Sucesso (200 OK):**

```json
{
  "id": 1,
  "name": "Admin User",
  "email": "adriano@2bx.com.br",
  "role": "admin"
}
```

**Resposta de Erro (401 Unauthorized):**

```json
{
  "error": "Invalid credentials"
}
```

-----

### 2\. Clientes

#### `GET /clients`

Retorna uma lista de todos os clientes.

#### `GET /clients/{id}`

Retorna os detalhes de um cliente específico.

#### `POST /clients`

Cria um novo cliente.

**Corpo da Requisição (JSON):**

```json
{
  "name": "Nova Empresa de Tecnologia"
}
```

**Resposta (201 Created):**

```json
{
  "id": 4,
  "name": "Nova Empresa de Tecnologia"
}
```

#### `PUT /clients/{id}`

Atualiza o nome de um cliente existente.

#### `DELETE /clients/{id}`

Exclui um cliente e todos os seus projetos e casos de teste associados (efeito cascata).

-----

### 3\. Projetos

#### `GET /projects`

Retorna uma lista de todos os projetos com o nome do cliente associado.

#### `GET /projects/{id}`

Retorna os detalhes de um projeto específico.

#### `POST /projects`

Cria um novo projeto.

**Corpo da Requisição (JSON):**

```json
{
  "name": "Chatbot de Vendas",
  "clientId": 2,
  "whatsappNumber": "+55 11 99999-1111",
  "description": "Bot para qualificação de leads.",
  "objective": "Aumentar a geração de leads em 20%."
}
```

#### `PUT /projects/{id}`

Atualiza os dados de um projeto existente.

#### `DELETE /projects/{id}`

Exclui um projeto.

-----

### 4\. Usuários

#### `GET /users`

Retorna uma lista de todos os usuários (sem o hash da senha).

#### `GET /users/{id}`

Retorna os detalhes de um usuário específico.

#### `POST /users`

Cria um novo usuário.

**Corpo da Requisição (JSON):**

```json
{
  "name": "Carlos Pereira",
  "email": "carlos@exemplo.com",
  "password": "senhaForte123",
  "role": "tester"
}
```

#### `PUT /users/{id}`

Atualiza os dados de um usuário. Para alterar a senha, inclua o campo `password`. Se o campo `password` for omitido, a senha atual será mantida.

#### `DELETE /users/{id}`

Exclui um usuário.

-----

### 5\. Casos de Teste

#### `GET /test-cases`

Retorna uma lista de todos os casos de teste.

#### `GET /test-cases/{id}`

Retorna os detalhes de um caso de teste específico. O `id` aqui é o ID em texto, como "TEST-1672858".

#### `POST /test-cases`

Cria um novo caso de teste. O ID é gerado automaticamente pela API.

**Corpo da Requisição (JSON):**

```json
{
  "typeId": "GREETING",
  "projectId": 1,
  "assignedTo": 2,
  "customFields": []
}
```

#### `PUT /test-cases/{id}`

Atualiza o estado de um teste (usado para pausar).

**Corpo da Requisição (JSON):**

```json
{
  "status": "paused",
  "pausedState": {
    "didGreet": "Sim",
    "notes": "Parou no meio do teste."
  }
}
```

#### `POST /test-cases/{id}/execute`

Executa um teste, muda seu status para "completed" e cria um relatório.

**Corpo da Requisição (JSON):**

```json
{
  "userId": 2,
  "results": {
    "didGreet": "Sim",
    "identifiedUser": "Não",
    "offeredHelp": "Sim",
    "didFarewell": "N/A",
    "notes": "O bot não identificou o nome do usuário."
  }
}
```

**Resposta (200 OK):**

```json
{
  "success": true,
  "reportId": "REP-1672859"
}
```

#### `DELETE /test-cases/{id}`

Exclui um caso de teste.

-----

### 6\. Relatórios

#### `GET /reports`

Retorna uma lista de todos os relatórios gerados, com detalhes do projeto, cliente e testador.

#### `GET /reports/{id}`

Retorna um relatório específico. O `id` aqui é o ID em texto, como "REP-1672859".

-----

### 7\. Modelos Personalizados (Custom Templates)

#### `GET /custom-templates`

Retorna uma lista de todos os modelos de teste personalizados.

#### `POST /custom-templates`

Cria um novo modelo de teste.

**Corpo da Requisição (JSON):**

```json
{
  "name": "Teste de Onboarding Financeiro",
  "description": "Verifica o fluxo de onboarding para novos clientes do setor financeiro.",
  "category": "Financeiro",
  "formFields": [
    { "label": "Verificou documento?", "type": "tri-state", "options": [] },
    { "label": "Ofereceu cartão de crédito?", "type": "select", "options": ["Sim", "Não"] }
  ]
}
```

-----

### 8\. Webhooks

#### `GET /webhooks`

Retorna uma lista de todos os webhooks configurados.

#### `POST /webhooks`

Cria um novo webhook.

**Corpo da Requisição (JSON):**

```json
{
  "url": "[https://n8n.meudominio.com/webhook/12345](https://n8n.meudominio.com/webhook/12345)",
  "events": ["test_completed", "project_created"]
}
```

**Eventos Disponíveis:**

  * `client_created`
  * `project_created`
  * `user_created`
  * `test_created`
  * `test_completed`

-----

## Schema do Banco de Dados

Este é o script SQL para criar a estrutura completa do banco de dados.

```sql
CREATE DATABASE IF NOT EXISTS `auditia_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `auditia_db`;

CREATE TABLE `clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','tester','client') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'tester',
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `custom_test_templates` (
  `id` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `category` varchar(100) NOT NULL,
  `formFields` json NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `test_cases` (
  `id` varchar(50) NOT NULL,
  `typeId` varchar(50) NOT NULL,
  `projectId` int(11) NOT NULL,
  `status` enum('pending','completed','paused') NOT NULL DEFAULT 'pending',
  `assignedTo` int(11) NULL,
  `customFields` json DEFAULT NULL,
  `pausedState` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `projectId` (`projectId`),
  KEY `assignedTo` (`assignedTo`),
  CONSTRAINT `test_cases_ibfk_1` FOREIGN KEY (`projectId`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `test_cases_ibfk_2` FOREIGN KEY (`assignedTo`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `reports` (
  `id` varchar(50) NOT NULL,
  `testCaseId` varchar(50) NOT NULL,
  `testerId` int(11) NULL,
  `clientId` int(11) NOT NULL,
  `projectId` int(11) NOT NULL,
  `executionDate` datetime NOT NULL,
  `results` json NOT NULL,
  PRIMARY KEY (`id`),
  KEY `testerId` (`testerId`),
  KEY `clientId` (`clientId`),
  KEY `projectId` (`projectId`),
  CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`testerId`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `webhooks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `url` varchar(2048) NOT NULL,
  `events` json NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```
