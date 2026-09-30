<?php

namespace Drupal\Tests\bioland\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests the pure builder behind the BL-784 media bulk action restore.
 *
 * Seeded sites excluded every media action from the media admin view's bulk
 * form, leaving the Action dropdown empty. The builder resets each display
 * that carries media_bulk_form to core's default (exclude nothing).
 *
 * @group bioland
 * @coversNothing
 */
class BiolandMediaBulkActionsTest extends TestCase
{
    /**
     * {@inheritdoc}
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__ . '/../../includes/bioland.install.views.inc';
    }

    /**
     * Builds view data with the given bulk form settings on a display.
     */
    private function view(array $bulk, string $display = 'default'): array
    {
        return [
            'id' => 'media',
            'display' => [
                $display => [
                    'display_options' => [
                        'fields' => [
                            'name' => ['id' => 'name'],
                            'media_bulk_form' => ['id' => 'media_bulk_form'] + $bulk,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * The live seeded state: exclude all four actions. Reset to exclude none.
     */
    public function testExcludeAllIsReset(): void
    {
        $data = $this->view([
            'include_exclude' => 'exclude',
            'selected_actions' => [
                'media_delete_action' => 'media_delete_action',
                'media_publish_action' => 'media_publish_action',
                'media_save_action' => 'media_save_action',
                'media_unpublish_action' => 'media_unpublish_action',
            ],
        ]);

        [$out, $changed] = _bioland_media_view_restore_bulk_actions($data);

        $field = $out['display']['default']['display_options']['fields']['media_bulk_form'];
        $this->assertSame(['default'], $changed);
        $this->assertSame('exclude', $field['include_exclude']);
        $this->assertSame([], $field['selected_actions']);
        $this->assertSame('media_bulk_form', $field['id']);
        $this->assertSame(['id' => 'name'], $out['display']['default']['display_options']['fields']['name']);
    }

    /**
     * An include whitelist that hides delete is reset too.
     */
    public function testIncludeWhitelistIsReset(): void
    {
        $data = $this->view([
            'include_exclude' => 'include',
            'selected_actions' => ['media_publish_action' => 'media_publish_action'],
        ]);

        [$out, $changed] = _bioland_media_view_restore_bulk_actions($data);

        $field = $out['display']['default']['display_options']['fields']['media_bulk_form'];
        $this->assertSame(['default'], $changed);
        $this->assertSame('exclude', $field['include_exclude']);
        $this->assertSame([], $field['selected_actions']);
    }

    /**
     * Core's default is already correct and reports no change (idempotent).
     */
    public function testCoreDefaultIsUntouched(): void
    {
        $data = $this->view(['include_exclude' => 'exclude', 'selected_actions' => []]);

        [$out, $changed] = _bioland_media_view_restore_bulk_actions($data);

        $this->assertSame([], $changed);
        $this->assertSame($data, $out);
    }

    /**
     * Running the builder on its own output changes nothing.
     */
    public function testSecondRunIsNoOp(): void
    {
        $data = $this->view([
            'include_exclude' => 'exclude',
            'selected_actions' => ['media_delete_action' => 'media_delete_action'],
        ]);

        [$once] = _bioland_media_view_restore_bulk_actions($data);
        [$twice, $changed] = _bioland_media_view_restore_bulk_actions($once);

        $this->assertSame([], $changed);
        $this->assertSame($once, $twice);
    }

    /**
     * Page displays that override the field are fixed alongside default;
     * displays without the field are left alone.
     */
    public function testOverridingDisplaysAreFixedAndOthersSkipped(): void
    {
        $bulk = [
            'include_exclude' => 'exclude',
            'selected_actions' => ['media_delete_action' => 'media_delete_action'],
        ];
        $data = $this->view($bulk);
        $data['display']['media_page_list'] = $this->view($bulk, 'media_page_list')['display']['media_page_list'];
        $data['display']['block_1'] = ['display_options' => ['fields' => ['name' => ['id' => 'name']]]];

        [$out, $changed] = _bioland_media_view_restore_bulk_actions($data);

        $this->assertSame(['default', 'media_page_list'], $changed);
        $this->assertSame([], $out['display']['media_page_list']['display_options']['fields']['media_bulk_form']['selected_actions']);
        $this->assertSame($data['display']['block_1'], $out['display']['block_1']);
    }

    /**
     * An include list with nothing selected already shows every action.
     */
    public function testIncludeWithEmptyListIsUntouched(): void
    {
        $data = $this->view(['include_exclude' => 'include', 'selected_actions' => []]);

        [$out, $changed] = _bioland_media_view_restore_bulk_actions($data);

        $this->assertSame([], $changed);
        $this->assertSame($data, $out);
    }

    /**
     * A view with no displays passes through unchanged.
     */
    public function testNoDisplaysIsNoOp(): void
    {
        [$out, $changed] = _bioland_media_view_restore_bulk_actions(['id' => 'media']);

        $this->assertSame([], $changed);
        $this->assertSame(['id' => 'media'], $out);
    }
}
