<?php

namespace Drupal\Tests\bioland\Unit;

use Drupal\bioland\BiolandDocumentPreviewPolicy as Policy;
use Drupal\Core\Site\Settings;
use PHPUnit\Framework\TestCase;

/**
 * Tests the pure decision helpers behind BL-1192 document previews.
 *
 * @coversDefaultClass \Drupal\bioland\BiolandDocumentPreviewPolicy
 * @group bioland
 */
class BiolandDocumentPreviewPolicyTest extends TestCase
{
    /**
     * @covers ::qualifies
     */
    public function testQualifiesTruthTable(): void
    {
        $this->assertFalse(Policy::qualifies('node', 'document', true, true, true), 'Wrong entity type.');
        $this->assertFalse(Policy::qualifies('media', 'image', true, true, true), 'Wrong bundle.');
        $this->assertFalse(Policy::qualifies('media', 'document', false, true, true), 'No document file.');
        $this->assertFalse(Policy::qualifies('media', 'document', true, false, true), 'Toggle off.');
        $this->assertFalse(Policy::qualifies('media', 'document', true, true, false), 'Missing secret.');
        $this->assertTrue(Policy::qualifies('media', 'document', true, true, true), 'Happy path.');
    }

    /**
     * @covers ::documentChanged
     */
    public function testDocumentChanged(): void
    {
        $this->assertFalse(Policy::documentChanged(5, 5));
        $this->assertTrue(Policy::documentChanged(null, 5));
        $this->assertTrue(Policy::documentChanged(5, 6));
    }

    /**
     * @covers ::isModuleOwnedImage
     */
    public function testIsModuleOwnedImage(): void
    {
        $this->assertTrue(Policy::isModuleOwnedImage('public://bioland/document-previews/1-2.webp'));
        $this->assertFalse(Policy::isModuleOwnedImage('public://2026-09/photo.jpg'));
        $this->assertFalse(Policy::isModuleOwnedImage(''));
        $this->assertFalse(Policy::isModuleOwnedImage(null));
    }

    /**
     * @covers ::converterFor
     */
    public function testConverterFor(): void
    {
        $this->assertSame(Policy::ROUTE_DIRECT, Policy::converterFor('pdf'));
        $this->assertSame(Policy::ROUTE_DIRECT, Policy::converterFor('docx'));
        $this->assertSame(Policy::ROUTE_DIRECT, Policy::converterFor('DOCX'), 'Case-insensitive.');
        $this->assertSame(Policy::ROUTE_VIA_PDF, Policy::converterFor('txt'));
        $this->assertSame(Policy::ROUTE_VIA_PDF, Policy::converterFor('odt'));
        $this->assertNull(Policy::converterFor('exe'));
        $this->assertNull(Policy::converterFor(''));
    }

    /**
     * @covers ::buildParams
     */
    public function testBuildParamsAlwaysCarriesPageRangeOne(): void
    {
        $direct = Policy::buildParams(Policy::ROUTE_DIRECT);
        $this->assertCount(1, $direct);
        $this->assertSame('1', $direct[0]['PageRange']);

        $viaPdf = Policy::buildParams(Policy::ROUTE_VIA_PDF);
        $this->assertCount(2, $viaPdf);
        $this->assertSame('1', $viaPdf[0]['PageRange'], 'Step 1 (to pdf) must carry PageRange=1.');
        $this->assertSame('1', $viaPdf[1]['PageRange'], 'Step 2 (pdf to webp) must carry PageRange=1.');
        $this->assertTrue($viaPdf[0]['StoreFile'], 'Step 1 must store the file so step 2 can chain it.');
    }

    /**
     * @covers ::dropsScaleProportions
     */
    public function testDropsScaleProportions(): void
    {
        $this->assertTrue(Policy::dropsScaleProportions('docx'));
        $this->assertTrue(Policy::dropsScaleProportions('pptx'));
        $this->assertTrue(Policy::dropsScaleProportions('xlsx'));
        $this->assertFalse(Policy::dropsScaleProportions('pdf'));
    }

    /**
     * @covers ::altText
     * @covers ::titleText
     */
    public function testAltAndTitleTextStripTrimAndTruncate(): void
    {
        $this->assertSame('First page of Report 2026', Policy::altText('  <b>Report 2026</b>  '));
        $this->assertSame('Report', Policy::titleText('  <i>Report</i>  '));

        $long = str_repeat('a', Policy::TITLE_MAX_LENGTH + 50);
        $this->assertSame(Policy::TITLE_MAX_LENGTH, mb_strlen(Policy::titleText($long)));

        $longAlt = str_repeat('b', Policy::ALT_MAX_LENGTH + 50);
        $this->assertSame(Policy::ALT_MAX_LENGTH, mb_strlen(Policy::altText($longAlt)));
    }

    /**
     * @covers ::classifyStatus
     */
    public function testClassifyStatus(): void
    {
        $this->assertSame(Policy::STATUS_SUCCESS, Policy::classifyStatus(200));
        $this->assertSame(Policy::STATUS_TRANSIENT, Policy::classifyStatus(429));
        $this->assertSame(Policy::STATUS_TRANSIENT, Policy::classifyStatus(503));
        $this->assertSame(Policy::STATUS_PERMANENT, Policy::classifyStatus(401));
        $this->assertSame(Policy::STATUS_PERMANENT, Policy::classifyStatus(400));
    }

    /**
     * @covers ::resolveSecret
     */
    public function testResolveSecretPrefersEnvOverSettings(): void
    {
        Settings::setAll(['bioland_convert_api_secret' => 'from-settings']);
        putenv('CONVERT_API_SECRET=from-env');

        $this->assertSame('from-env', Policy::resolveSecret());

        putenv('CONVERT_API_SECRET');
        $this->assertSame('from-settings', Policy::resolveSecret());

        Settings::setAll([]);
        $this->assertSame('', Policy::resolveSecret());
    }
}
