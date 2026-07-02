<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\VocabularioTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\VocabularioTable Test Case
 */
class VocabularioTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\VocabularioTable
     */
    protected $Vocabulario;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected array $fixtures = [
        'app.Vocabulario',
        'app.Usuarios',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('Vocabulario') ? [] : ['className' => VocabularioTable::class];
        $this->Vocabulario = $this->getTableLocator()->get('Vocabulario', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->Vocabulario);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @link \App\Model\Table\VocabularioTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test buildRules method
     *
     * @return void
     * @link \App\Model\Table\VocabularioTable::buildRules()
     */
    public function testBuildRules(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
