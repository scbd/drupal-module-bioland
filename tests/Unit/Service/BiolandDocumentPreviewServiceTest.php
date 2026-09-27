<?php

namespace Drupal\Tests\bioland\Unit\Service;

use ConvertApi\Result;
use ConvertApi\ResultFile;
use Drupal\bioland\BiolandDocumentPreviewPolicy as Policy;
use Drupal\bioland\Service\BiolandDocumentPreviewService;
use Drupal\Core\Entity\ContentEntityInterface;
use PHPUnit\Framework\TestCase;

/**
 * A minimal stand-in for a Drupal field item list: target_id + isEmpty().
 */
class FakeFieldItem
{
    public $target_id;
    private bool $empty;

    public function __construct($target_id, bool $empty = false)
    {
        $this->target_id = $target_id;
        $this->empty = $empty;
    }

    public function isEmpty(): bool
    {
        return $this->empty;
    }
}

/**
 * Exposes the protected convert() calls the real service makes to ConvertAPI.
 */
class RecordingDocumentPreviewService extends BiolandDocumentPreviewService
{
    public array $convertCalls = [];

    /** @var \ConvertApi\Result[] */
    public array $convertResults = [];

    protected function convert(string $toFormat, array $params, ?string $fromFormat = null)
    {
        $this->convertCalls[] = ['to' => $toFormat, 'params' => $params, 'from' => $fromFormat];

        return array_shift($this->convertResults);
    }
}

/**
 * Tests BiolandDocumentPreviewService::process() call-shape and byte handling.
 *
 * @coversDefaultClass \Drupal\bioland\Service\BiolandDocumentPreviewService
 * @group bioland
 */
class BiolandDocumentPreviewServiceTest extends TestCase
{
    /**
     * Builds a media mock with a document fid and no existing image.
     */
    private function buildMedia(int $fid, bool $expectSave): ContentEntityInterface
    {
        $media = $this->createMock(ContentEntityInterface::class);
        $media->method('id')->willReturn(1);
        $media->method('label')->willReturn('Sample report');
        $media->method('hasField')->willReturnMap([
            [Policy::DOCUMENT_FIELD, true],
            [Policy::IMAGE_FIELD, true],
        ]);
        $media->method('get')->willReturnMap([
            [Policy::DOCUMENT_FIELD, new FakeFieldItem($fid)],
            [Policy::IMAGE_FIELD, new FakeFieldItem(null, true)],
        ]);
        $media->expects($expectSave ? $this->once() : $this->never())->method('set')->with(
            Policy::IMAGE_FIELD,
            $this->callback(function ($value) {
                return $value['target_id'] === 99
                    && $value['alt'] === 'First page of Sample report'
                    && $value['title'] === 'Sample report';
            })
        );
        $media->expects($expectSave ? $this->once() : $this->never())->method('save');

        return $media;
    }

    /**
     * Builds a service with mocked collaborators and injected convert() results.
     */
    private function buildService(array $convertResults, string $documentUri = 'public://2026-09/report.pdf', bool $expectSave = true): RecordingDocumentPreviewService
    {
        $file = $this->createMock('Drupal\file\FileInterface');
        $file->method('getFileUri')->willReturn($documentUri);
        $file->method('getFilename')->willReturn(basename($documentUri));

        $newFile = $this->createMock('Drupal\file\FileInterface');
        $newFile->method('id')->willReturn(99);

        $fileStorage = $this->createMock('Drupal\Core\Entity\EntityStorageInterface');
        $fileStorage->method('load')->willReturn($file);

        $mediaStorage = $this->createMock('Drupal\Core\Entity\EntityStorageInterface');
        $mediaStorage->method('load')->willReturn($this->buildMedia(10, $expectSave));

        $entityTypeManager = $this->createMock('Drupal\Core\Entity\EntityTypeManagerInterface');
        $entityTypeManager->method('getStorage')->willReturnMap([
            ['file', $fileStorage],
            ['media', $mediaStorage],
        ]);

        $fileSystem = $this->createMock('Drupal\Core\File\FileSystemInterface');
        $fileSystem->method('realpath')->willReturn('/tmp/report');

        $fileRepository = $this->createMock('Drupal\file\FileRepositoryInterface');
        $fileRepository->method('writeData')->willReturn($newFile);

        $loggerFactory = $this->createMock('Drupal\Core\Logger\LoggerChannelFactoryInterface');
        $loggerFactory->method('get')->willReturn($this->createMock('Drupal\Core\Logger\LoggerChannelInterface'));

        $queueFactory = $this->createMock('Drupal\Core\Queue\QueueFactory');
        $configFactory = $this->createMock('Drupal\Core\Config\ConfigFactoryInterface');
        $state = $this->createMock('Drupal\Core\State\StateInterface');

        $service = new RecordingDocumentPreviewService(
            $entityTypeManager,
            $fileSystem,
            $fileRepository,
            $loggerFactory,
            $queueFactory,
            $configFactory,
            $state
        );
        $service->convertResults = $convertResults;

        return $service;
    }

    /**
     * A direct-route extension (pdf) makes exactly one ConvertAPI call.
     *
     * @covers ::process
     */
    public function testProcessDirectExtensionMakesExactlyOneCall(): void
    {
        $result = new Result(new ResultFile('PDFBYTES'), 1);
        $service = $this->buildService([$result]);

        $service->process(['mid' => 1, 'fid' => 10, 'extension' => 'pdf']);

        $this->assertCount(1, $service->convertCalls);
        $this->assertSame('webp', $service->convertCalls[0]['to']);
        $this->assertSame('1', $service->convertCalls[0]['params']['PageRange']);
    }

    /**
     * A via-pdf extension makes exactly two chained ConvertAPI calls.
     *
     * @covers ::process
     */
    public function testProcessViaPdfExtensionMakesExactlyTwoChainedCalls(): void
    {
        $pdfFile = new ResultFile('PDFBYTES');
        $pdfResult = new Result($pdfFile, 1);
        $webpResult = new Result(new ResultFile('WEBPBYTES'), 1);

        $service = $this->buildService([$pdfResult, $webpResult], 'public://2026-09/report.odt');

        $service->process(['mid' => 1, 'fid' => 10, 'extension' => 'odt']);

        $this->assertCount(2, $service->convertCalls);
        $this->assertSame('pdf', $service->convertCalls[0]['to']);
        $this->assertSame('webp', $service->convertCalls[1]['to']);
        $this->assertSame('pdf', $service->convertCalls[1]['from']);
        $this->assertSame($pdfFile, $service->convertCalls[1]['params']['File'], 'The second call must chain the first result\'s stored file.');
        $this->assertSame('1', $service->convertCalls[0]['params']['PageRange']);
        $this->assertSame('1', $service->convertCalls[1]['params']['PageRange']);
    }

    /**
     * A direct docx conversion drops ScaleProportions, which its converter does not document.
     *
     * @covers ::process
     */
    public function testDirectDocxDropsScaleProportions(): void
    {
        $service = $this->buildService([new Result(new ResultFile('BYTES'), 1)], 'public://2026-09/report.docx');

        $service->process(['mid' => 1, 'fid' => 10, 'extension' => 'docx']);

        $this->assertArrayNotHasKey('ScaleProportions', $service->convertCalls[0]['params']);
    }

    /**
     * An unsupported extension never calls ConvertAPI at all.
     *
     * @covers ::process
     */
    public function testUnsupportedExtensionMakesNoConvertCall(): void
    {
        $service = $this->buildService([], 'public://2026-09/report.exe', false);

        $service->process(['mid' => 1, 'fid' => 10, 'extension' => 'exe']);

        $this->assertCount(0, $service->convertCalls);
    }
}
