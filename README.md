Documentação Técnica da API - AuditIA ManagerVersão: 1.1.0Bem-vindo à documentação técnica completa da API do AuditIA Manager. Esta API RESTful foi projetada para ser o backend do sistema, gerenciando todos os recursos de forma segura e eficiente. A comunicação é feita através do protocolo HTTP utilizando o formato JSON.URL BaseTodas as URLs mencionadas nesta documentação são relativas à seguinte URL base:http://apiauditia.2bx.com.br1. Autenticação e AutorizaçãoA API utiliza um processo de autenticação simples baseado em e-mail e senha. Em uma evolução futura para produção, o endpoint de login retornaria um token (ex: JWT - JSON Web Token) que deveria ser utilizado para autenticar as requisições subsequentes.Fluxo Sugerido para Produção:O cliente envia as credenciais para POST /login.A API valida e retorna um token de acesso.Para todas as requisições subsequentes a endpoints protegidos, o cliente deve enviar o token no cabeçalho Authorization.Authorization: Bearer <seu_token_jwt>
2. Convenções da APICódigos de Status HTTPA API utiliza os códigos de status HTTP para indicar o sucesso ou falha de uma requisição:CódigoSignificadoDescrição200OKA requisição foi bem-sucedida (para GET, PUT, DELETE).201CreatedO recurso foi criado com sucesso (para POST).400Bad RequestA requisição é inválida (ex: corpo malformatado, campos faltando).401UnauthorizedAutenticação falhou ou não foi fornecida.404Not FoundO recurso solicitado não foi encontrado.405Method Not AllowedO método HTTP utilizado não é permitido para o recurso.500Internal Server ErrorOcorreu um erro inesperado no servidor.Formato de ErroRespostas de erro (4xx, 5xx) seguirão um formato padronizado:{
  "error": "Descrição do erro."
}
3. Endpoints da APIRecurso: AutenticaçãoPOST /loginAutentica um usuário no sistema.Método: POSTCaminho: /loginCorpo da Requisição: application/jsonCampoTipoObrigatórioDescriçãoemailStringSimE-mail do usuário.passwordStringSimSenha do usuário.Exemplo de Requisição (cURL):curl -X POST [http://apiauditia.2bx.com.br/login](http://apiauditia.2bx.com.br/login) \
-H "Content-Type: application/json" \
-d '{
  "email": "admin@2bx.com.br",
  "password": "sua_senha_segura"
}'
Resposta de Sucesso (200 OK):{
  "id": 1,
  "name": "Admin User",
  "email": "admin@2bx.com.br",
  "role": "admin"
}
Respostas de Erro:401 Unauthorized - Credenciais inválidas.400 Bad Request - E-mail ou senha não fornecidos.Recurso: ClientesGerencia os clientes (empresas) da plataforma.GET /clientsRetorna uma lista de todos os clientes cadastrados.Método: GETCaminho: /clientsExemplo de Requisição (cURL):curl -X GET [http://apiauditia.2bx.com.br/clients](http://apiauditia.2bx.com.br/clients)
Resposta de Sucesso (200 OK):[
  {
    "id": 1,
    "name": "Banco Digital"
  },
  {
    "id": 2,
    "name": "Varejo Online"
  }
]
GET /clients/{id}Retorna os detalhes de um cliente específico.Método: GETCaminho: /clients/{id}Parâmetros de URL:ParâmetroTipoDescrição{id}IntegerID do cliente a ser buscado.Exemplo de Requisição (cURL):curl -X GET [http://apiauditia.2bx.com.br/clients/1](http://apiauditia.2bx.com.br/clients/1)
Resposta de Sucesso (200 OK):{
  "id": 1,
  "name": "Banco Digital"
}
Resposta de Erro: 404 Not Found - Cliente com o ID fornecido não existe.POST /clientsCria um novo cliente.Método: POSTCaminho: /clientsCorpo da Requisição: application/jsonCampoTipoObrigatórioDescriçãonameStringSimNome do cliente.Exemplo de Requisição (cURL):curl -X POST [http://apiauditia.2bx.com.br/clients](http://apiauditia.2bx.com.br/clients) \
-H "Content-Type: application/json" \
-d '{"name": "Nova Seguradora"}'
Resposta (201 Created): Retorna o objeto do cliente recém-criado.{
  "id": 3,
  "name": "Nova Seguradora"
}
PUT /clients/{id}Atualiza as informações de um cliente.Método: PUTCaminho: /clients/{id}Corpo da Requisição: application/json (mesmo do POST)Exemplo de Requisição (cURL):curl -X PUT [http://apiauditia.2bx.com.br/clients/3](http://apiauditia.2bx.com.br/clients/3) \
-H "Content-Type: application/json" \
-d '{"name": "Nova Seguradora S.A."}'
Resposta (200 OK):{
  "success": true
}
DELETE /clients/{id}Exclui um cliente. Atenção: Esta ação removerá em cascata todos os projetos e casos de teste associados.Método: DELETECaminho: /clients/{id}Exemplo de Requisição (cURL):curl -X DELETE [http://apiauditia.2bx.com.br/clients/3](http://apiauditia.2bx.com.br/clients/3)
Resposta (200 OK):{
  "success": true
}
Recurso: ProjetosGerencia os projetos de chatbot, que são associados a um cliente.GET /projectsRetorna uma lista de todos os projetos.Método: GETCaminho: /projectsResposta de Sucesso (200 OK):[
  {
    "id": 1,
    "name": "Chatbot de Atendimento",
    "clientId": 1,
    "whatsappNumber": "+55 11 98765-4321",
    "description": "Chatbot para suporte ao cliente no app principal.",
    "objective": "Reduzir o tempo de espera no atendimento.",
    "clientName": "Banco Digital"
  }
]
POST /projectsCria um novo projeto.Método: POSTCaminho: /projectsCorpo da Requisição: application/jsonCampoTipoObrigatórioDescriçãonameStringSimNome do projeto.clientIdIntegerSimID do cliente ao qual o projeto pertence.whatsappNumberStringSimNúmero do WhatsApp do chatbot.descriptionStringNãoBreve descrição do projeto.objectiveStringNãoObjetivo principal do chatbot.Resposta (201 Created): Retorna o objeto do projeto recém-criado.PUT /projects/{id}Atualiza as informações de um projeto.Método: PUTCaminho: /projects/{id}Corpo da Requisição: application/json (mesmo do POST)DELETE /projects/{id}Exclui um projeto.Método: DELETECaminho: /projects/{id}Recurso: UsuáriosGerencia os usuários do sistema.GET /usersRetorna uma lista de todos os usuários.Método: GETCaminho: /usersGET /users/{id}Retorna os detalhes de um usuário específico.Método: GETCaminho: /users/{id}POST /usersCria um novo usuário.Método: POSTCaminho: /usersCorpo da Requisição: application/jsonCampoTipoObrigatórioDescriçãonameStringSimNome completo do usuário.emailStringSimE-mail de login do usuário (único).passwordStringSimSenha para o novo usuário.roleStringSimPerfil (admin, tester, client).PUT /users/{id}Atualiza as informações de um usuário. Se o campo password for enviado, a senha será atualizada.Método: PUTCaminho: /users/{id}DELETE /users/{id}Exclui um usuário.Método: DELETECaminho: /users/{id}Recurso: Casos de TesteGerencia os casos de teste a serem executados.POST /test-casesCria um novo caso de teste.Método: POSTCaminho: /test-casesCorpo da Requisição: application/jsonCampoTipoObrigatórioDescriçãotypeIdStringSimID do modelo de teste (padrão ou personalizado).projectIdIntegerSimID do projeto onde o teste será executado.assignedToIntegerSimID do usuário (testador) responsável pelo teste.customFieldsArrayNãoArray de objetos para campos personalizados no teste.PUT /test-cases/{id}Atualiza o estado de um teste, tipicamente para paused.Método: PUTCaminho: /test-cases/{id}Corpo da Requisição: application/jsonCampoTipoObrigatórioDescriçãostatusStringSimNovo status (pending, paused).pausedStateObjectSimObjeto JSON com os dados já preenchidos.POST /test-cases/{id}/executeMarca um teste como concluído e gera o relatório correspondente.Método: POSTCaminho: /test-cases/{id}/executeCorpo da Requisição: application/jsonCampoTipoObrigatórioDescriçãouserIdIntegerSimID do usuário que está executando o teste.resultsObjectSimObjeto JSON com os resultados do formulário.Resposta (200 OK):{
  "success": true,
  "reportId": "REP-1687741800"
}
DELETE /test-cases/{id}Exclui um caso de teste.Método: DELETECaminho: /test-cases/{id}Recurso: RelatóriosEndpoint para consulta dos resultados dos testes executados.GET /reportsRetorna uma lista de todos os relatórios gerados.Método: GETCaminho: /reportsGET /reports/{id}Retorna os detalhes de um relatório específico.Método: GETCaminho: /reports/{id}Recurso: Modelos PersonalizadosGerencia os modelos de teste criados pelos administradores.GET /custom-templatesRetorna uma lista de todos os modelos de teste personalizados.GET /custom-templates/{id}Retorna os detalhes de um modelo de teste específico.POST /custom-templatesCria um novo modelo de teste personalizado.Corpo da Requisição: application/jsonCampoTipoObrigatórioDescriçãonameStringSimNome do modelo.descriptionStringSimDescrição do propósito do modelo.categoryStringSimCategoria para agrupar o modelo.formFieldsArraySimArray de objetos definindo os campos do formulário.PUT /custom-templates/{id}Atualiza um modelo de teste existente.DELETE /custom-templates/{id}Exclui um modelo de teste personalizado.Recurso: WebhooksGerencia webhooks para notificações de eventos em tempo real.GET /webhooksRetorna uma lista de todos os webhooks configurados.GET /webhooks/{id}Retorna os detalhes de um webhook específico.POST /webhooksCria um novo webhook.Corpo da Requisição: application/jsonCampoTipoObrigatórioDescriçãourlStringSimURL para onde o evento será enviado (POST).eventsArraySimArray de strings com os eventos a serem assinados.PUT /webhooks/{id}Atualiza a URL ou os eventos de um webhook.DELETE /webhooks/{id}Exclui um webhook.Eventos de Webhook DisponíveisQuando um evento assinado ocorre, a API envia uma requisição POST para a URL do webhook com o seguinte corpo:{
  "event": "test_completed",
  "triggeredAt": "2025-06-25T21:50:00-03:00",
  "payload": {
    // ...
