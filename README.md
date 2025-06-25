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

1.  **Crie o Diretório da API**: Crie um diretório no seu servidor (ex: `/var/www/apiauditia`) e aponte o subdomínio `apiauditia.2bx.com.br` para este diretório.
2.  **Adicione o Arquivo**: Dentro deste diretório, crie um arquivo chamado `index.php` e cole o código-fonte completo da API nele.
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

Para que as URLs amigáveis (ex: `/clients/1`) funcionem, crie um arquivo `.htaccess` no mesmo diretório do `index.php` com o seguinte conteúdo:

```htaccess
<IfModule mod_rewrite.c>
  RewriteEngine On

  # Redireciona tudo que não for um arquivo ou diretório físico para o index.php
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteRule ^(.*)$ index.php [L,QSA]
</IfModule>
Importante: Certifique-se de que o módulo mod_rewrite do Apache está habilitado e que o seu Virtual Host permite a sobrescrita de regras (AllowOverride All).Endpoints da APIA URL base para todos os endpoints é http://apiauditia.2bx.com.br1. AutenticaçãoPOST /loginAutentica um usuário e retorna seus dados se as credenciais estiverem corretas.Corpo da Requisição (JSON):{
  "email": "adriano@2bx.com.br",
  "password": "123"
}
Resposta de Sucesso (200 OK):{
  "id": 1,
  "name": "Admin User",
  "email": "adriano@2bx.com.br",
  "role": "admin"
}
Resposta de Erro (401 Unauthorized):{
  "error": "Invalid credentials"
}
2. ClientesGET /clientsRetorna uma lista de todos os clientes.GET /clients/{id}Retorna os detalhes de um cliente específico.POST /clientsCria um novo cliente.Corpo da Requisição (JSON):{
  "name": "Nova Empresa de Tecnologia"
}
Resposta (201 Created):{
  "id": 4,
  "name": "Nova Empresa de Tecnologia"
}
PUT /clients/{id}Atualiza o nome de um cliente existente.DELETE /clients/{id}Exclui um cliente e todos os seus projetos e casos de teste associados (efeito cascata).3. ProjetosGET /projectsRetorna uma lista de todos os projetos com o nome do cliente associado.GET /projects/{id}Retorna os detalhes de um projeto específico.POST /projectsCria um novo projeto.Corpo da Requisição (JSON):{
  "name": "Chatbot de Vendas",
  "clientId": 2,
  "whatsappNumber": "+55 11 99999-1111",
  "description": "Bot para qualificação de leads.",
  "objective": "Aumentar a geração de leads em 20%."
}
PUT /projects/{id}Atualiza os dados de um projeto existente.DELETE /projects/{id}Exclui um projeto.4. UsuáriosGET /usersRetorna uma lista de todos os usuários (sem o hash da senha).GET /users/{id}Retorna os detalhes de um usuário específico.POST /usersCria um novo usuário.Corpo da Requisição (JSON):{
  "name": "Carlos Pereira",
  "email": "carlos@exemplo.com",
  "password": "senhaForte123",
  "role": "tester"
}
PUT /users/{id}Atualiza os dados de um usuário. Para alterar a senha, inclua o campo password. Se o campo password for omitido, a senha atual será mantida.DELETE /users/{id}Exclui um usuário.5. Casos de TesteGET /test-casesRetorna uma lista de todos os casos de teste.GET /test-cases/{id}Retorna os detalhes de um caso de teste específico. O id aqui é o ID em texto, como "TEST-1672858".POST /test-casesCria um novo caso de teste. O ID é gerado automaticamente pela API.Corpo da Requisição (JSON):{
  "typeId": "GREETING",
  "projectId": 1,
  "assignedTo": 2,
  "customFields": []
}
PUT /test-cases/{id}Atualiza o estado de um teste (usado para pausar).Corpo da Requisição (JSON):{
  "status": "paused",
  "pausedState": {
    "didGreet": "Sim",
    "notes": "Parou no meio do teste."
  }
}
POST /test-cases/{id}/executeExecuta um teste, muda seu status para "completed" e cria um relatório.Corpo da Requisição (JSON):{
  "userId": 2,
  "results": {
    "didGreet": "Sim",
    "identifiedUser": "Não",
    "offeredHelp": "Sim",
    "didFarewell": "N/A",
    "notes": "O bot não identificou o nome do usuário."
  }
}
Resposta (200 OK):{
  "success": true,
  "reportId": "REP-1672859"
}
DELETE /test-cases/{id}Exclui um caso de teste.6. RelatóriosGET /reportsRetorna uma lista de todos os relatórios gerados, com detalhes do projeto, cliente e testador.GET /reports/{id}Retorna um relatório específico. O id aqui é o ID em texto, como "REP-1672859".7. Modelos Personalizados (Custom Templates)GET /custom-templatesRetorna uma lista de todos os modelos de teste personalizados.GET /custom-templates/{id}Retorna os detalhes de um modelo de teste específico.POST /custom-templatesCria um novo modelo de teste.Corpo da Requisição (JSON):{
  "name": "Teste de Onboarding Financeiro",
  "description": "Verifica o fluxo de onboarding para novos clientes do setor financeiro.",
  "category": "Financeiro",
  "formFields": [
    { "label": "Verificou documento?", "type": "tri-state", "options": [] },
    { "label": "Ofereceu cartão de crédito?", "type": "select", "options": ["Sim", "Não"] }
  ]
}
PUT /custom-templates/{id}Atualiza um modelo de teste existente.DELETE /custom-templates/{id}Exclui um modelo de teste personalizado.8. WebhooksGET /webhooksRetorna uma lista de todos os webhooks configurados.GET /webhooks/{id}Retorna os detalhes de um webhook específico.POST /webhooksCria um novo webhook.Corpo da Requisição (JSON):{
  "url": "[https://n8n.meudominio.com/webhook/12345](https://n8n.meudominio.com/webhook/12345)",
  "events": ["test_completed", "project_created"]
}
PUT /webhooks/{id}Atualiza um webhook existente.DELETE /webhooks/{id}Exclui um webhook.Eventos de Webhook Disponíveis:client_createdproject_createduser_createdtest_createdtest_completedSchema do Banco de DadosEste é o script SQL para criar a estrutura completa do banco de dados.CREATE DATABASE IF NOT EXISTS `auditia_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
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
