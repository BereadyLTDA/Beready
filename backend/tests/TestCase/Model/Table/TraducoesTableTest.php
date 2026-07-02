<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\TraducoesTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\TraducoesTable Test Case
 */
class TraducoesTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\TraducoesTable
     */
    protected $Traducoes;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected array $fixtures = [
        'app.Traducoes',
        'app.Prompts',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('Traducoes') ? [] : ['className' => TraducoesTable::class];
        $this->Traducoes = $this->getTableLocator()->get('Traducoes', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->Traducoes);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @link \App\Model\Table\TraducoesTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test buildRules method
     *
     * @return void
     * @link \App\Model\Table\TraducoesTable::buildRules()
     */
    public function testBuildRules(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
