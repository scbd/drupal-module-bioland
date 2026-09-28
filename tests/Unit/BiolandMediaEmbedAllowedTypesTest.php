<?php

namespace Drupal\Tests\bioland\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests the pure builder behind the BL-1206 media_embed allowed types fix.
 *
 * CKEditor's "Insert Media" dialog should offer every media type except
 * 'document' (documents are inserted/linked, not embedded inline). The
 * builder must derive the list from whatever media types actually exist on
 * the site, never a hard-coded list.
 *
 * @group bioland
 * @coversDefaultClass \_bioland_media_embed_allowed_types
 */
class BiolandMediaEmbedAllowedTypesTest extends TestCase
{
    /**
     * {@inheritdoc}
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__ . '/../../includes/bioland.install.editor.inc';
    }

    /**
     * Every type is allowed except 'document', stable sorted.
     */
    public function testAllowsEveryTypeExceptDocument(): void
    {
        $result = _bioland_media_embed_allowed_types([
            'remote_video',
            'document',
            'image',
            'hero',
        ]);

        $this->assertSame(
            [
                'hero' => 'hero',
                'image' => 'image',
                'remote_video' => 'remote_video',
            ],
            $result
        );
    }

    /**
     * Order of the input never affects the (sorted) output.
     */
    public function testResultIsStableSortedRegardlessOfInputOrder(): void
    {
        $a = _bioland_media_embed_allowed_types(['remote_video', 'audio', 'image']);
        $b = _bioland_media_embed_allowed_types(['image', 'audio', 'remote_video']);

        $this->assertSame($a, $b);
        $this->assertSame(['audio', 'image', 'remote_video'], array_keys($a));
    }

    /**
     * Empty input produces empty output.
     */
    public function testEmptyInputReturnsEmptyArray(): void
    {
        $this->assertSame([], _bioland_media_embed_allowed_types([]));
    }

    /**
     * An install with only 'document' is left with nothing allowed.
     */
    public function testOnlyDocumentTypeReturnsEmptyArray(): void
    {
        $this->assertSame([], _bioland_media_embed_allowed_types(['document']));
    }

    /**
     * A future media type the module has never seen is included automatically.
     */
    public function testUnknownFutureMediaTypeIsIncluded(): void
    {
        $result = _bioland_media_embed_allowed_types(['image', 'some_new_type']);

        $this->assertSame(
            ['image' => 'image', 'some_new_type' => 'some_new_type'],
            $result
        );
    }
}
