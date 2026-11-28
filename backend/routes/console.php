<?php

use App\Services\LembreteService;
use App\Services\RelatorioEstudoService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('beready:lembretes', function (LembreteService $service) {
    $this->info($service->enviar().' lembrete(s) enviado(s)');
})->purpose('Envia o lembrete de estudo para quem tem cards vencidos e parou de estudar');

Artisan::command(
    'beready:relatorio-estudo
        {--emails= : E-mails dos participantes, separados por vírgula, na ordem que define P01, P02...}
        {--arquivo= : Arquivo com um e-mail por linha (alternativa a --emails)}
        {--de= : Primeiro dia do estudo (AAAA-MM-DD)}
        {--ate= : Último dia do estudo (AAAA-MM-DD, padrão hoje)}
        {--saida= : Caminho do CSV (padrão storage/app/relatorio-estudo-AAAAMMDD.csv)}
        {--separador=, : Separador do CSV (use ";" para abrir direto no Excel em português)}',
    function (RelatorioEstudoService $service) {
        $emails = [];
        if ($this->option('arquivo')) {
            $caminho = (string) $this->option('arquivo');
            if (! is_readable($caminho)) {
                $this->error("Não consegui ler o arquivo {$caminho}");

                return 1;
            }
            $emails = (array) file($caminho, FILE_IGNORE_NEW_LINES);
        } elseif ($this->option('emails')) {
            $emails = explode(',', (string) $this->option('emails'));
        }
        $emails = array_values(array_filter(array_map('trim', $emails), fn ($e) => $e !== '' && ! str_starts_with($e, '#')));
        if ($emails === []) {
            $this->error('Informe os participantes com --emails ou --arquivo (só quem assinou o termo de consentimento).');

            return 1;
        }

        try {
            $de = CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->option('de'));
            $ate = $this->option('ate') ? CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->option('ate')) : CarbonImmutable::today();
        } catch (Throwable) {
            $de = $ate = false;
        }
        if (! $de || ! $ate || $ate->lt($de)) {
            $this->error('Informe --de (e opcionalmente --ate) no formato AAAA-MM-DD, com --ate depois de --de.');

            return 1;
        }

        $resultado = $service->gerar($emails, $de, $ate);
        foreach ($resultado['nao_encontrados'] as $posicao) {
            $this->warn("Linha {$posicao} da lista: nenhum usuário com esse e-mail (ficou fora do relatório)");
        }

        $saida = (string) ($this->option('saida') ?: storage_path('app/relatorio-estudo-'.now()->format('Ymd').'.csv'));
        $separador = (string) $this->option('separador') ?: ',';
        $arquivo = fopen($saida, 'w');
        if ($arquivo === false) {
            $this->error("Não consegui gravar em {$saida}");

            return 1;
        }
        fputcsv($arquivo, RelatorioEstudoService::COLUNAS, $separador, '"', '');
        foreach ($resultado['linhas'] as $linha) {
            fputcsv($arquivo, array_map(fn ($v) => $v === null ? '' : (string) $v, array_values($linha)), $separador, '"', '');
        }
        fclose($arquivo);

        $this->info(count($resultado['linhas'])." participante(s) exportado(s) para {$saida}");

        return 0;
    }
)->purpose('Exporta em CSV, anonimizadas, as métricas de uso dos participantes da validação com usuários');

// Em produção, um cron precisa chamar "php artisan schedule:run" a cada minuto
Schedule::command('beready:lembretes')->dailyAt('18:00')->withoutOverlapping();
