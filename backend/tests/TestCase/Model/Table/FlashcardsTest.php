<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\FlashcardTagsTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\FlashcardTagsTable Test Case
 */
class FlashcardTagsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\FlashcardTagsTable
     */
    protected $FlashcardTags;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected array $fixtures = [
        'app.flashcard_tags',
        'app.Flashcards',
        'app.Flashcardsflashcard_tags',
        'app.Usuarios', // se houver relação
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('flashcard_tags') ? [] : ['className' => flashcardsTable::class];
        $this->FlashcardTags = $this->getTableLocator()->get('FlashcardTags', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->FlashcardTags);
        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test buildRules method
     *
     * @return void
     */
    public function testBuildRules(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
