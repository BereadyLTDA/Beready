<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\PromptsTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\PromptsTable Test Case
 */
class PromptsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\PromptsTable
     */
    protected $Prompts;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected array $fixtures = [
        'app.Prompts',
        'app.Usuarios',
        'app.Flashcards',
        'app.FrasesSemelhantes',
        'app.ImagensGeradas',
        'app.Traducoes',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('Prompts') ? [] : ['className' => PromptsTable::class];
        $this->Prompts = $this->getTableLocator()->get('Prompts', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->Prompts);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @link \App\Model\Table\PromptsTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test buildRules method
     *
     * @return void
     * @link \App\Model\Table\PromptsTable::buildRules()
     */
    public function testBuildRules(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
