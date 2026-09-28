<?php

namespace Drupal\Tests\bioland\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests the pure builder behind the BL-1206 media_embed allowed types fix.
 *
 * CKEditor's "Insert Media" dialog must offer image + remote_video only,
 * the allowlist BL-1205 established. document (inserted / linked, not
 * embedded) and hero (a page banner, never inline body content) must never
 * be re-admitted, and the allowlist is intersected with the media types
 * that actually exist on the site.
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
     * Only image + remote_video survive; document and hero are dropped.
     */
    public function testAllowsOnlyImageAndRemoteVideo(): void
    {
        $result = _bioland_media_embed_allowed_types([
            'remote_video',
            'document',
            'image',
            'hero',
        ]);

        $this->assertSame(
            [
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
        $a = _bioland_media_embed_allowed_types(['remote_video', 'hero', 'image']);
        $b = _bioland_media_embed_allowed_types(['image', 'hero', 'remote_video']);

        $this->assertSame($a, $b);
        $this->assertSame(['image', 'remote_video'], array_keys($a));
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
     * An allowlisted type that does not exist on the site is never written.
     */
    public function testMissingAllowlistedTypeIsNotInvented(): void
    {
        $this->assertSame(
            ['image' => 'image'],
            _bioland_media_embed_allowed_types(['image', 'document', 'hero'])
        );
    }

    /**
     * A future media type the module has never seen is NOT auto-admitted.
     */
    public function testUnknownFutureMediaTypeIsExcluded(): void
    {
        $result = _bioland_media_embed_allowed_types(['image', 'some_new_type']);

        $this->assertSame(['image' => 'image'], $result);
    }

    /**
     * The allowlist constant is the single source of truth.
     */
    public function testAllowlistConstantIsImageAndRemoteVideo(): void
    {
        $this->assertSame(['image', 'remote_video'], BIOLAND_MEDIA_EMBED_ALLOWED_TYPES);
    }
}
