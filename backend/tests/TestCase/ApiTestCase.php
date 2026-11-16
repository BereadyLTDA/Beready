<?php

declare(strict_types=1);

namespace App\Test\TestCase;

use App\Services\JwtService;
use Cake\Cache\Cache;
use Cake\Datasource\EntityInterface;
use Cake\TestSuite\EmailTrait;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\Http\ServerRequest;
use Cake\Routing\Router;
use Cake\TestSuite\TestCase;
use League\OpenAPIValidation\PSR7\Exception\ValidationFailed;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use League\OpenAPIValidation\Schema\Exception\SchemaMismatch;

/**
 * Base dos testes de integração da API.
 *
 * Roles, permissões e planos vêm dos seeds (config/Seeds), aplicados no tests/bootstrap.php.
 * Usuários e demais registros são criados pelos próprios testes; as fixtures limpam as tabelas.
 */
abstract class ApiTestCase extends TestCase
{
    // Troca os transports de e-mail por um transport de teste: nada é enviado de verdade
    use EmailTrait;
    use IntegrationTestTrait {
        _sendRequest as sendIntegrationRequest;
    }

    // IDs fixos de config/Seeds/PlanosSeed.php
    protected const PLANO_TRIAL = 1;
    protected const PLANO_GRATUITO = 2;
    protected const PLANO_PREMIUM = 3;

    protected array $fixtures = [
        'app.Users',
        'app.Assinaturas',
        'app.Flashcards',
        'app.Quizes',
        'app.Prompts',
        'app.Tags',
        'app.ProgressoUsuario',
        'app.UsuarioRoles',
        'app.LogsPermissoes',
    ];

    /**
     * Variáveis de ambiente alteradas no teste, restauradas no tearDown.
     */
    private array $envBackup = [];

    /**
     * Headers enviados em todas as requisições do teste (ver withBearer).
     */
    private array $headers = [];

    /**
     * Toda resposta é conferida com o contrato OpenAPI (ver assertRespostaConformeContrato).
     * Desligue (no próprio teste ou no setUp) apenas quando o teste provoca, de propósito, uma
     * resposta que o contrato não deve descrever.
     */
    protected bool $validarContrato = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Contadores do rate limit não podem vazar de um teste para outro
        Cache::clear('rate_limit');
        Cache::clear('social_login');
    }

    protected function tearDown(): void
    {
        foreach ($this->envBackup as $key => $value) {
            $this->writeEnv($key, $value);
        }
        $this->envBackup = [];

        parent::tearDown();
    }

    /**
     * Cria um usuário ativo com senha "secret123".
     */
    protected function createUser(string $role = 'user', array $data = []): EntityInterface
    {
        static $sequence = 0;
        static $senhaHash = null;
        $sequence++;
        // password_hash é lento; um hash só serve para todos os usuários de teste
        $senhaHash ??= password_hash('secret123', PASSWORD_DEFAULT);

        return $this->insert('Users', $data + [
            'nome' => "Usuário {$sequence}",
            'email' => "usuario{$sequence}_" . uniqid() . '@teste.local',
            'senha_hash' => $senhaHash,
            'role' => $role,
            'status' => 'ativo',
        ]);
    }

    /**
     * Insere um registro ignorando o _accessible da entidade (dados montados pelo próprio teste).
     */
    protected function insert(string $alias, array $data): EntityInterface
    {
        $table = $this->getTableLocator()->get($alias);
        $entity = $table->newEntity($data, ['accessibleFields' => ['*' => true]]);

        return $table->saveOrFail($entity);
    }

    protected function fetch(string $alias, int $id): ?EntityInterface
    {
        return $this->getTableLocator()->get($alias)->find()->where(['id' => $id])->first();
    }

    /**
     * Tokens gerados pelo JwtService real.
     *
     * @return array{access_token: string, refresh_token: string}
     */
    protected function tokensFor(EntityInterface $user, ?string $role = null): array
    {
        return (new JwtService())->generateTokens([
            'id' => $user->id,
            'email' => $user->email,
            'nome' => $user->nome,
            'role' => $role ?? $user->role,
        ]);
    }

    /**
     * Configura as próximas requisições como JSON, autenticadas com o access token do usuário.
     */
    protected function actingAs(EntityInterface $user, ?string $role = null): void
    {
        $this->withBearer($this->tokensFor($user, $role)['access_token']);
    }

    protected function withBearer(?string $token): void
    {
        $this->headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
        if ($token !== null) {
            $this->headers['Authorization'] = 'Bearer ' . $token;
        }
    }

    /**
     * O IntegrationTestTrait descarta os headers configurados após cada requisição;
     * reaplica os do withBearer() (headers extras de configRequest() têm prioridade).
     */
    protected function _sendRequest(array|string $url, string $method, array|string $data = []): void
    {
        $this->_request['headers'] = ($this->_request['headers'] ?? []) + $this->headers;

        $this->sendIntegrationRequest($url, $method, $data);

        if ($this->validarContrato) {
            $this->assertRespostaConformeContrato($url, $method);
        }
    }

    /**
     * Confere a resposta recebida com o contrato OpenAPI (backend/openapi.yaml): o status precisa
     * estar documentado na operação e o corpo precisa obedecer ao schema.
     *
     * Só a resposta é validada: os testes enviam corpos inválidos de propósito. Requisições que não
     * casam com nenhuma operação documentada (rota de fallback, rotas pendentes) passam direto; quem
     * garante que toda rota está no contrato é o OpenApiContractTest.
     */
    private function assertRespostaConformeContrato(array|string $url, string $method): void
    {
        if ($this->_response === null) {
            return;
        }

        $path = (string)parse_url(is_array($url) ? Router::url($url) : $url, PHP_URL_PATH);
        $operacao = self::operacaoDocumentada($method, $path);
        if ($operacao === null) {
            return;
        }

        [$metodoDocumentado, $template] = explode(' ', $operacao, 2);

        try {
            self::contrato()['validator']->validate(
                new OperationAddress($template, strtolower($metodoDocumentado)),
                $this->_response
            );
        } catch (ValidationFailed $e) {
            $corpo = (string)$this->_response->getBody();
            if (mb_strlen($corpo) > 1500) {
                $corpo = mb_substr($corpo, 0, 1500) . '… [truncado]';
            }

            $this->fail(sprintf(
                "Resposta fora do contrato OpenAPI (backend/openapi.yaml)\n"
                . "  Requisição: %s %s\n"
                . "  Operação:   %s\n"
                . "  Status:     %d\n"
                . "  Motivo:     %s\n"
                . "  Corpo:      %s\n"
                . 'Corrija o contrato (ou a API, se a resposta estiver errada).',
                $method,
                $path,
                $operacao,
                $this->_response->getStatusCode(),
                self::motivoDaFalha($e),
                $corpo === '' ? '(vazio)' : $corpo
            ));
        }
    }

    /**
     * "MÉTODO /template" da operação do contrato que atende a requisição, ou null se não houver.
     * A rota é resolvida pelo próprio Router (mesmos padrões de parâmetro da aplicação); rotas
     * gêmeas de uma operação documentada são validadas pelo schema dela.
     */
    private static function operacaoDocumentada(string $method, string $path): ?string
    {
        try {
            $params = self::rotaDe($method, $path);
        } catch (\Throwable) {
            return null;
        }

        $chave = strtoupper($method) . ' ' . ($params['_matchedRoute'] ?? '');
        $chave = OpenApiContractTest::operacaoEquivalente($chave);

        return isset(self::contrato()['operacoes'][$chave]) ? $chave : null;
    }

    /**
     * Parâmetros da rota que atende a requisição (inclui `_matchedRoute`, o template registrado).
     *
     * @return array<string, mixed>
     */
    private static function rotaDe(string $method, string $path): array
    {
        // Quem carrega as rotas é o RoutingMiddleware: se um middleware anterior respondeu
        // (401, 429...), o Router ainda está vazio neste ponto
        if (!Router::routes()) {
            (new Application(CONFIG))->routes(Router::createRouteBuilder('/'));
        }

        return Router::getRouteCollection()->parseRequest(new ServerRequest([
            "url" => $path === "" ? "/" : $path,
            "environment" => ["REQUEST_METHOD" => strtoupper($method)],
        ]));
    }

    /**
     * Validador e índice das operações do contrato, montados uma única vez por processo.
     *
     * @return array{validator: \League\OpenAPIValidation\PSR7\ResponseValidator, operacoes: array<string, true>}
     */
    private static function contrato(): array
    {
        static $contrato = null;

        if ($contrato === null) {
            $validator = (new ValidatorBuilder())
                ->fromYamlFile(OpenApiContractTest::ARQUIVO)
                ->getResponseValidator();

            $contrato = [
                'validator' => $validator,
                'operacoes' => array_fill_keys(OpenApiContractTest::operacoesDoContrato($validator->getSchema()), true),
            ];
        }

        return $contrato;
    }

    /**
     * Junta as mensagens da cadeia de exceções do validador e, quando houver, o caminho do campo.
     */
    private static function motivoDaFalha(\Throwable $e): string
    {
        $partes = [];
        for ($atual = $e; $atual !== null; $atual = $atual->getPrevious()) {
            $mensagem = $atual->getMessage();
            if ($atual instanceof SchemaMismatch && $atual->dataBreadCrumb() !== null) {
                $campo = implode('.', $atual->dataBreadCrumb()->buildChain());
                $mensagem .= sprintf(' [campo: %s]', $campo === '' ? '(raiz)' : $campo);
            }
            $partes[] = $mensagem;
        }

        return implode(' → ', $partes);
    }

    protected function postJson(string $url, array $data = []): void
    {
        $this->post($url, (string)json_encode($data));
    }

    protected function putJson(string $url, array $data = []): void
    {
        $this->put($url, (string)json_encode($data));
    }

    protected function responseJson(): array
    {
        return (array)json_decode((string)$this->_response->getBody(), true);
    }

    /**
     * Define uma variável de ambiente só durante o teste (env() lê $_SERVER, $_ENV e getenv).
     */
    protected function setEnv(string $key, ?string $value): void
    {
        if (!array_key_exists($key, $this->envBackup)) {
            $this->envBackup[$key] = $_SERVER[$key] ?? $_ENV[$key] ?? (getenv($key) === false ? null : getenv($key));
        }

        $this->writeEnv($key, $value);
    }

    private function writeEnv(string $key, ?string $value): void
    {
        if ($value === null) {
            unset($_SERVER[$key], $_ENV[$key]);
            putenv($key);

            return;
        }

        $_SERVER[$key] = $_ENV[$key] = $value;
        putenv("{$key}={$value}");
    }
}
