<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\FrasesSemelhantesTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\FrasesSemelhantesTable Test Case
 */
class FrasesSemelhantesTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\FrasesSemelhantesTable
     */
    protected $FrasesSemelhantes;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected array $fixtures = [
        'app.FrasesSemelhantes',
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
        $config = $this->getTableLocator()->exists('FrasesSemelhantes') ? [] : ['className' => FrasesSemelhantesTable::class];
        $this->FrasesSemelhantes = $this->getTableLocator()->get('FrasesSemelhantes', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->FrasesSemelhantes);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @link \App\Model\Table\FrasesSemelhantesTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test buildRules method
     *
     * @return void
     * @link \App\Model\Table\FrasesSemelhantesTable::buildRules()
     */
    public function testBuildRules(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
