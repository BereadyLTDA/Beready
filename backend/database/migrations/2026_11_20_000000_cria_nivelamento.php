<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Teste de nivelamento adaptativo (níveis do CEFR, A1 a C1). As questões ficam aqui para existirem
 * iguais em todo ambiente; revise-as antes de usar o resultado em pesquisa.
 */
return new class extends Migration
{
    /**
     * [nível, categoria, enunciado, alternativas, índice da correta]
     */
    private const QUESTOES = [
        ['A1', 'gramatica', 'She ___ a teacher.', ['are', 'is', 'am', 'be'], 1],
        ['A1', 'gramatica', 'I ___ two brothers.', ['have', 'has', 'having', 'haves'], 0],
        ['A1', 'gramatica', 'What ___ your name?', ['do', 'are', 'does', 'is'], 3],
        ['A1', 'vocabulario', 'The opposite of "big" is ___.', ['tall', 'long', 'small', 'fast'], 2],
        ['A1', 'gramatica', 'We ___ football on Sundays.', ['plays', 'play', 'playing', 'is play'], 1],
        ['A1', 'gramatica', 'There ___ a cat on the sofa.', ['are', 'be', 'have', 'is'], 3],

        ['A2', 'gramatica', 'Yesterday I ___ to the cinema.', ['went', 'go', 'gone', 'going'], 0],
        ['A2', 'gramatica', 'She is ___ than her sister.', ['more tall', 'tallest', 'taller', 'tall'], 2],
        ['A2', 'gramatica', 'I have lived here ___ 2019.', ['for', 'since', 'from', 'during'], 1],
        ['A2', 'gramatica', 'Look! It ___ outside.', ['rains', 'rain', 'rained', 'is raining'], 3],
        ['A2', 'vocabulario', 'How ___ money do you have?', ['much', 'many', 'lot', 'few'], 0],
        ['A2', 'gramatica', 'We ___ visit our grandparents next weekend.', ['going to', 'are going to', 'go to', 'will to'], 1],

        ['B1', 'gramatica', 'If it rains tomorrow, we ___ at home.', ['would stay', 'stayed', 'will stay', 'stay'], 2],
        ['B1', 'gramatica', 'I have never ___ sushi before.', ['ate', 'eaten', 'eat', 'eating'], 1],
        ['B1', 'gramatica', 'The book ___ by millions of people.', ['has been read', 'has read', 'have been read', 'is reading'], 0],
        ['B1', 'gramatica', 'She asked me where I ___.', ['live', 'do live', 'living', 'lived'], 3],
        ['B1', 'gramatica', 'I am not used to ___ up so early.', ['get', 'getting', 'got', 'have got'], 1],
        ['B1', 'vocabulario', '"Reluctant" means ___.', ['excited', 'careless', 'unwilling', 'generous'], 2],

        ['B2', 'gramatica', 'If I ___ more time, I would learn Japanese.', ['have', 'would have', 'will have', 'had'], 3],
        ['B2', 'gramatica', 'By the time we arrived, the film ___.', ['had already started', 'already started', 'has already started', 'was already starting'], 0],
        ['B2', 'gramatica', 'He denied ___ the window.', ['to break', 'breaking', 'break', 'broke'], 1],
        ['B2', 'gramatica', 'You ___ have told me earlier; I could have helped.', ['must', 'can', 'should', 'would'], 2],
        ['B2', 'vocabulario', 'The project was ___ due to lack of funding.', ['called up', 'called in', 'called for', 'called off'], 3],
        ['B2', 'gramatica', 'Hardly ___ the house when it started to rain.', ['had we left', 'we had left', 'we left', 'did we left'], 0],

        ['C1', 'gramatica', 'Had I known about the delay, I ___ a later train.', ['would take', 'would have taken', 'will have taken', 'had taken'], 1],
        ['C1', 'gramatica', 'Not only ___ late, but he also forgot the tickets.', ['he was', 'he is', 'was he', 'did he'], 2],
        ['C1', 'vocabulario', 'Her explanation was so ___ that nobody could follow it.', ['concise', 'lucid', 'succinct', 'convoluted'], 3],
        ['C1', 'gramatica', 'It is high time we ___ a decision.', ['made', 'make', 'will make', 'have made'], 0],
        ['C1', 'vocabulario', 'The new policy is likely to ___ widespread criticism.', ['drag', 'draw', 'pull', 'carry'], 1],
        ['C1', 'gramatica', '___ the circumstances, she handled the situation remarkably well.', ['Giving', 'Gave', 'Given', 'To give'], 2],
    ];

    public function up(): void
    {
        Schema::create('nivelamento_questoes', function (Blueprint $table) {
            $table->id();
            $table->string('nivel', 2)->index();
            $table->string('categoria', 20);
            $table->text('enunciado');
            $table->jsonb('alternativas');
            $table->unsignedTinyInteger('correta');
        });

        Schema::create('nivelamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->string('nivel_atual', 2);
            $table->unsignedTinyInteger('acertos_seguidos')->default(0);
            $table->unsignedTinyInteger('erros_seguidos')->default(0);
            // [{questao_id, nivel, correta}] na ordem em que foram respondidas
            $table->jsonb('respostas')->default('[]');
            $table->unsignedBigInteger('questao_atual_id')->nullable();
            $table->string('nivel_resultado', 2)->nullable();
            $table->timestamp('iniciado_em')->useCurrent();
            $table->timestamp('concluido_em')->nullable();
            $table->index(['usuario_id', 'concluido_em']);
        });

        DB::table('nivelamento_questoes')->insert(array_map(fn (array $q) => [
            'nivel' => $q[0],
            'categoria' => $q[1],
            'enunciado' => $q[2],
            'alternativas' => json_encode($q[3]),
            'correta' => $q[4],
        ], self::QUESTOES));
    }

    public function down(): void
    {
        Schema::dropIfExists('nivelamentos');
        Schema::dropIfExists('nivelamento_questoes');
    }
};
