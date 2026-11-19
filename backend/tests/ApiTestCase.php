<?php

namespace Tests;

use App\Models;
use App\Services\JwtService;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use League\OpenAPIValidation\PSR7\Exception\ValidationFailed;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ResponseValidator;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use League\OpenAPIValidation\Schema\Exception\SchemaMismatch;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Tests\Feature\OpenApiContractTest;

/**
 * Base dos testes de integração da API.
 *
 * - Banco: Postgres descartável (TEST_DATABASE_URL), recriado pelo RefreshDatabase; roles,
 *   permissões e planos vêm do DatabaseSeeder.
 * - Toda resposta é conferida com o contrato OpenAPI (openapi.yaml); ver assertRespostaConformeContrato.
 * - Os helpers (createUser, insert, actingAs, assertResponseCode, responseJson...) têm os mesmos
 *   nomes dos testes da versão em CakePHP, para os testes terem sido portados quase linha a linha.
 */
abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    // Roles, permissões e planos iniciais
    protected bool $seed = true;

    // IDs fixos de database/seeders/sql/dados-iniciais.sql
    protected const PLANO_TRIAL = 1;

    protected const PLANO_GRATUITO = 2;

    protected const PLANO_PREMIUM = 3;

    /**
     * Nome da tabela/alias usado nos testes do CakePHP => model.
     */
    private const MODELS = [
        'Users' => Models\User::class,
        'Assinaturas' => Models\Assinatura::class,
        'Planos' => Models\Plano::class,
        'Roles' => Models\Role::class,
        'Permissoes' => Models\Permissao::class,
        'UsuarioRoles' => Models\UsuarioRole::class,
        'LogsPermissoes' => Models\LogPermissao::class,
        'Flashcards' => Models\Flashcard::class,
        'FlashcardTags' => Models\FlashcardTag::class,
        'Tags' => Models\Tag::class,
        'Quizes' => Models\Quiz::class,
        'QuizQuestoes' => Models\QuizQuestao::class,
        'QuizAlternativas' => Models\QuizAlternativa::class,
        'RespostasUsuario' => Models\RespostaUsuario::class,
        'ProgressoUsuario' => Models\ProgressoUsuario::class,
        'PreferenciasUsuario' => Models\PreferenciaUsuario::class,
        'Prompts' => Models\Prompt::class,
        'Traducoes' => Models\Traducao::class,
        'ImagensGeradas' => Models\ImagemGerada::class,
        'FrasesSemelhantes' => Models\FraseSemelhante::class,
    ];

    /**
     * Toda resposta é conferida com o contrato OpenAPI. Desligue (no próprio teste) apenas quando o
     * teste provoca, de propósito, uma resposta que o contrato não deve descrever.
     */
    protected bool $validarContrato = true;

    /**
     * Resposta da última requisição (o equivalente ao $this->_response do CakePHP).
     */
    protected ?TestResponse $ultimaResposta = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Nenhum e-mail sai dos testes (equivalente ao EmailTrait do CakePHP)
        Mail::fake();

        $this->withBearer(null);
    }

    // ---------------------------------------------------------------- dados

    /**
     * Simula o Google aprovando o reCAPTCHA (login e cadastro exigem o token).
     */
    protected function recaptchaAprova(): void
    {
        config(['beready.recaptcha.secret' => 'segredo-recaptcha-de-teste']);
        Http::fake(['*recaptcha*' => Http::response(['success' => true, 'score' => 0.9])]);
    }

    /**
     * Cria um usuário ativo com senha "secret123".
     *
     * @param  array<string, mixed>  $data
     */
    protected function createUser(string $role = 'user', array $data = []): Models\User
    {
        static $sequence = 0;
        static $senhaHash = null;
        $sequence++;
        // password_hash é lento; um hash só serve para todos os usuários de teste
        $senhaHash ??= password_hash('secret123', PASSWORD_DEFAULT);

        /** @var Models\User */
        return $this->insert('Users', $data + [
            'nome' => "Usuário {$sequence}",
            'email' => "usuario{$sequence}_".uniqid().'@teste.local',
            'senha_hash' => $senhaHash,
            'role' => $role,
            'status' => 'ativo',
            'email_verificado_em' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Insere um registro ignorando o $fillable (dados montados pelo próprio teste) e o recarrega,
     * para trazer os valores padrão do banco (uuid, criado_em...).
     *
     * @param  string  $alias  nome usado nos testes do CakePHP (Users, Flashcards...) ou classe do model
     * @param  array<string, mixed>  $data
     */
    protected function insert(string $alias, array $data): Model
    {
        $classe = self::MODELS[$alias] ?? $alias;
        /** @var Model $model */
        $model = new $classe;
        $model->forceFill($data)->saveOrFail();

        return $model->getKey() !== null ? ($model->fresh() ?? $model) : $model;
    }

    /**
     * Consulta Eloquent da tabela (equivalente ao getTableLocator()->get($alias)->find() do CakePHP).
     *
     * @return Builder<Model>
     */
    protected function tabela(string $alias): Builder
    {
        $classe = self::MODELS[$alias] ?? $alias;

        return $classe::query();
    }

    protected function fetch(string $alias, int $id): ?Model
    {
        $classe = self::MODELS[$alias] ?? $alias;

        return $classe::find($id);
    }

    // ---------------------------------------------------------------- autenticação

    /**
     * Tokens gerados pelo JwtService real.
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int, token_type: string}
     */
    protected function tokensFor(Models\User $user, ?string $role = null): array
    {
        return app(JwtService::class)->generateTokens([
            'id' => (int) $user->id,
            'email' => (string) $user->email,
            'nome' => $user->nome,
            'role' => $role ?? $user->role,
        ]);
    }

    /**
     * Próximas requisições em JSON, autenticadas com o access token do usuário.
     * (Sem tipo no parâmetro: substitui o actingAs do Laravel, que espera um Authenticatable.)
     *
     * @param  Models\User  $user
     */
    public function actingAs($user, $role = null)
    {
        $this->withBearer($this->tokensFor($user, $role)['access_token']);

        return $this;
    }

    protected function withBearer(?string $token): void
    {
        $this->flushHeaders();
        $this->withHeaders(['Accept' => 'application/json']);
        if ($token !== null) {
            $this->withHeader('Authorization', 'Bearer '.$token);
        }
    }

    /**
     * Cookie enviado nas próximas requisições, sem criptografia (a API não criptografa cookies).
     */
    protected function cookie(string $nome, string $valor): void
    {
        $this->withUnencryptedCookie($nome, $valor);
    }

    /**
     * Troca uma implementação do container (equivalente ao mockService do CakePHP).
     */
    protected function mockService(string $abstract, Closure $factory): void
    {
        $this->app->bind($abstract, $factory);
    }

    /**
     * Variável de ambiente => chave de config equivalente. Os testes do CakePHP alteravam o
     * ambiente; aqui a aplicação lê config(), então setEnv() altera a config correspondente.
     */
    private const ENV_PARA_CONFIG = [
        'APP_BASE_URL' => 'beready.frontend_url',
        'STRIPE_WEBHOOK_SECRET' => 'beready.stripe.webhook_secret',
        'STRIPE_SECRET_KEY' => 'beready.stripe.secret',
        'ANTHROPIC_API_KEY' => 'beready.ia.anthropic_api_key',
        'ANTHROPIC_MODEL' => 'beready.ia.modelo',
        'IA_LIMITE_DIARIO' => 'beready.ia.limite_diario',
        'RECAPTCHA_SECRET_KEY' => 'beready.recaptcha.secret',
        'REFRESH_COOKIE_SAMESITE' => 'beready.refresh_cookie.samesite',
        'REFRESH_COOKIE_SECURE' => 'beready.refresh_cookie.secure',
    ];

    /**
     * Altera, só durante o teste, a configuração que corresponde à variável de ambiente.
     */
    protected function setEnv(string $key, ?string $value): void
    {
        $this->assertArrayHasKey($key, self::ENV_PARA_CONFIG, "setEnv(): sem config equivalente para {$key}");

        $valor = match ($key) {
            'APP_BASE_URL' => rtrim((string) $value, '/').'/',
            'IA_LIMITE_DIARIO' => (int) $value,
            'REFRESH_COOKIE_SECURE' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => $value,
        };

        config([self::ENV_PARA_CONFIG[$key] => $valor]);
    }

    // ---------------------------------------------------------------- requisições e asserts

    /**
     * Toda requisição dos testes passa por aqui: guarda a resposta e confere o contrato.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $response = parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
        $this->ultimaResposta = $response;

        if ($this->validarContrato) {
            $this->assertRespostaConformeContrato($method, $uri, $response);
        }

        return $response;
    }

    protected function resposta(): TestResponse
    {
        $this->assertNotNull($this->ultimaResposta, 'Nenhuma requisição foi feita no teste');

        return $this->ultimaResposta;
    }

    protected function assertResponseCode(int $status, string $mensagem = ''): void
    {
        $this->assertSame(
            $status,
            $this->resposta()->getStatusCode(),
            trim($mensagem.' Corpo da resposta: '.mb_substr((string) $this->resposta()->getContent(), 0, 1000)),
        );
    }

    /**
     * Qualquer 2xx (como o assertResponseOk do CakePHP).
     */
    protected function assertResponseOk(): void
    {
        $status = $this->resposta()->getStatusCode();
        $this->assertTrue(
            $status >= 200 && $status < 300,
            "Esperado 2xx, recebido {$status}: ".mb_substr((string) $this->resposta()->getContent(), 0, 1000),
        );
    }

    /**
     * Algum e-mail foi enviado para o endereço (equivalente ao assertMailSentTo do CakePHP).
     */
    protected function assertMailSentTo(string $email): void
    {
        Mail::assertSent(fn (Mailable $mail) => $mail->hasTo($email));
    }

    protected function assertHeader(string $nome, string $valor): void
    {
        $this->assertSame($valor, $this->resposta()->headers->get($nome));
    }

    protected function assertRedirect(string $url): void
    {
        $this->assertTrue($this->resposta()->isRedirect(), 'A resposta não é um redirecionamento');
        $this->assertSame($url, $this->resposta()->headers->get('Location'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function responseJson(): array
    {
        return (array) json_decode((string) $this->resposta()->getContent(), true);
    }

    // ---------------------------------------------------------------- contrato OpenAPI

    /**
     * Confere a resposta com o contrato OpenAPI (openapi.yaml): o status precisa estar documentado
     * na operação e o corpo precisa obedecer ao schema.
     *
     * Só a resposta é validada: os testes enviam corpos inválidos de propósito. Requisições que não
     * casam com nenhuma operação documentada (fallback) passam direto; quem garante que toda rota
     * está no contrato é o OpenApiContractTest.
     */
    private function assertRespostaConformeContrato(string $method, string $uri, TestResponse $response): void
    {
        $path = (string) parse_url($uri, PHP_URL_PATH);
        $operacao = $this->operacaoDocumentada($method, $path);
        if ($operacao === null) {
            return;
        }

        [$metodoDocumentado, $template] = explode(' ', $operacao, 2);
        $psr = (new PsrHttpFactory(new Psr17Factory, new Psr17Factory, new Psr17Factory, new Psr17Factory))
            ->createResponse($response->baseResponse);

        try {
            self::validador()->validate(new OperationAddress($template, strtolower($metodoDocumentado)), $psr);
        } catch (ValidationFailed $e) {
            $corpo = (string) $response->getContent();
            if (mb_strlen($corpo) > 1500) {
                $corpo = mb_substr($corpo, 0, 1500).'… [truncado]';
            }

            $this->fail(sprintf(
                "Resposta fora do contrato OpenAPI (openapi.yaml)\n"
                ."  Requisição: %s %s\n"
                ."  Operação:   %s\n"
                ."  Status:     %d\n"
                ."  Motivo:     %s\n"
                ."  Corpo:      %s\n"
                .'Corrija o contrato (ou a API, se a resposta estiver errada).',
                $method,
                $path,
                $operacao,
                $response->getStatusCode(),
                self::motivoDaFalha($e),
                $corpo === '' ? '(vazio)' : $corpo,
            ));
        }
    }

    /**
     * "MÉTODO /template" da operação do contrato que atende a requisição, ou null se não houver.
     * A rota é resolvida pelo próprio roteador (mesmos padrões de parâmetro da aplicação); rotas
     * gêmeas de uma operação documentada são validadas pelo schema dela.
     */
    private function operacaoDocumentada(string $method, string $path): ?string
    {
        try {
            $rota = $this->app['router']->getRoutes()->match(Request::create($path === '' ? '/' : $path, strtoupper($method)));
        } catch (\Throwable) {
            return null;
        }
        if ($rota->isFallback) {
            return null;
        }

        $chave = OpenApiContractTest::operacaoEquivalente(strtoupper($method).' '.OpenApiContractTest::template($rota->uri()));

        return in_array($chave, OpenApiContractTest::operacoesDoContrato(self::validador()->getSchema()), true) ? $chave : null;
    }

    /**
     * Validador do contrato, montado uma única vez por processo.
     */
    private static function validador(): ResponseValidator
    {
        static $validador = null;

        return $validador ??= (new ValidatorBuilder)->fromYamlFile(OpenApiContractTest::ARQUIVO)->getResponseValidator();
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
}
