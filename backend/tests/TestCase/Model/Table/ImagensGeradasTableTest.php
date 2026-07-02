<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\ImagensGeradasTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\ImagensGeradasTable Test Case
 */
class ImagensGeradasTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\ImagensGeradasTable
     */
    protected $ImagensGeradas;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected array $fixtures = [
        'app.ImagensGeradas',
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
        $config = $this->getTableLocator()->exists('ImagensGeradas') ? [] : ['className' => ImagensGeradasTable::class];
        $this->ImagensGeradas = $this->getTableLocator()->get('ImagensGeradas', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->ImagensGeradas);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @link \App\Model\Table\ImagensGeradasTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test buildRules method
     *
     * @return void
     * @link \App\Model\Table\ImagensGeradasTable::buildRules()
     */
    public function testBuildRules(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
