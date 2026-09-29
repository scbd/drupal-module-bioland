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

    /** The bytes handed to FileRepository::writeData(), or NULL when nothing was written. */
    public ?string $writtenBytes = null;

    /** Directories passed to FileSystem::prepareDirectory(), in call order. */
    public array $preparedDirectories = [];

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
    private function buildMedia(int $fid, bool $expectSave, ?int $imageFid = null): ContentEntityInterface
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
            [Policy::IMAGE_FIELD, $imageFid === null ? new FakeFieldItem(null, true) : new FakeFieldItem($imageFid)],
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
     *
     * @param int|null $existingImageFid
     *   When set, the media's current Image field already holds this file id
     *   at process() time (simulating an editor-chosen image set after
     *   enqueue); $existingImageUri names its (non-module) URI.
     */
    private function buildService(
        array $convertResults,
        string $documentUri = 'public://2026-09/report.pdf',
        bool $expectSave = true,
        ?int $existingImageFid = null,
        string $existingImageUri = 'public://2026-09/editor-photo.jpg',
        $queueFactory = null
    ): RecordingDocumentPreviewService {
        $file = $this->createMock('Drupal\file\FileInterface');
        $file->method('getFileUri')->willReturn($documentUri);
        $file->method('getFilename')->willReturn(basename($documentUri));

        $newFile = $this->createMock('Drupal\file\FileInterface');
        $newFile->method('id')->willReturn(99);

        $existingImageFile = $this->createMock('Drupal\file\FileInterface');
        $existingImageFile->method('getFileUri')->willReturn($existingImageUri);

        $fileStorage = $this->createMock('Drupal\Core\Entity\EntityStorageInterface');
        $fileStorage->method('load')->willReturnCallback(function ($fid) use ($file, $existingImageFid, $existingImageFile) {
            if ($existingImageFid !== null && $fid === $existingImageFid) {
                return $existingImageFile;
            }
            return $file;
        });

        $mediaStorage = $this->createMock('Drupal\Core\Entity\EntityStorageInterface');
        $mediaStorage->method('load')->willReturn($this->buildMedia(10, $expectSave, $existingImageFid));

        $entityTypeManager = $this->createMock('Drupal\Core\Entity\EntityTypeManagerInterface');
        $entityTypeManager->method('getStorage')->willReturnMap([
            ['file', $fileStorage],
            ['media', $mediaStorage],
        ]);

        $fileSystem = $this->createMock('Drupal\Core\File\FileSystemInterface');
        $fileSystem->method('realpath')->willReturn('/tmp/report');
        $fileSystem->method('prepareDirectory')->willReturnCallback(function ($directory) use (&$service) {
            $service->preparedDirectories[] = $directory;
            return true;
        });

        $fileRepository = $this->createMock('Drupal\file\FileRepositoryInterface');
        $service = null;
        $fileRepository->method('writeData')->willReturnCallback(function ($data, $destination) use ($newFile, &$service) {
            // Mirrors core: writing into an unprepared directory throws.
            if (!in_array(dirname($destination), $service->preparedDirectories, true)) {
                throw new \RuntimeException('DirectoryNotReadyException: ' . dirname($destination));
            }
            $service->writtenBytes = $data;
            return $newFile;
        });

        $loggerFactory = $this->createMock('Drupal\Core\Logger\LoggerChannelFactoryInterface');
        $loggerFactory->method('get')->willReturn($this->createMock('Drupal\Core\Logger\LoggerChannelInterface'));

        $queueFactory = $queueFactory ?: $this->createMock('Drupal\Core\Queue\QueueFactory');
        $configFactory = $this->createMock('Drupal\Core\Config\ConfigFactoryInterface');
        $configFactory->method('get')->willReturn(new \Drupal\Core\Config\ImmutableConfig('bioland.settings', ['enable_document_preview' => true]));
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
     * The preview directory is created before the converted bytes are written.
     *
     * Regression for BL-1192: on a fresh site public://bioland/document-previews
     * did not exist, so writeData() threw DirectoryNotReadyException.
     *
     * @covers ::process
     */
    public function testProcessPreparesPreviewDirectoryBeforeWriting(): void
    {
        $service = $this->buildService([new Result(new ResultFile('PDFBYTES'), 1)]);

        $service->process(['mid' => 1, 'fid' => 10, 'extension' => 'pdf']);

        $this->assertSame(['public://bioland/document-previews'], $service->preparedDirectories);
        $this->assertNotNull($service->writtenBytes);
    }

    /**
     * An inline (StoreFile=false) result is decoded from FileData, not downloaded.
     *
     * Regression for BL-1192: the webp step never stores its file, so the
     * result carries base64 FileData and no Url; convertapi-php's
     * getContents() cannot read that shape.
     *
     * @covers ::process
     */
    public function testProcessDecodesInlineFileDataWhenResultIsNotStored(): void
    {
        $result = new Result(new ResultFile([
            'FileName' => 'report.webp', 'FileSize' => 9, 'FileData' => base64_encode('WEBPBYTES'),
        ]), 1);
        $service = $this->buildService([$result]);

        $service->process(['mid' => 1, 'fid' => 10, 'extension' => 'pdf']);

        $this->assertSame('WEBPBYTES', $service->writtenBytes);
    }

    /**
     * Corrupt inline FileData fails loudly instead of writing garbage.
     *
     * @covers ::process
     */
    public function testProcessRejectsInvalidInlineFileData(): void
    {
        $result = new Result(new ResultFile(['FileName' => 'report.webp', 'FileData' => '%%not-base64%%']), 1);
        $service = $this->buildService([$result], 'public://2026-09/report.pdf', false);

        $this->expectException(\RuntimeException::class);
        $service->process(['mid' => 1, 'fid' => 10, 'extension' => 'pdf']);
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

    /**
     * A dropped extension (no verified ConvertAPI route on any tested target,
     * e.g. fodt) never calls ConvertAPI either - same as an unsupported one.
     *
     * @covers ::process
     */
    public function testDroppedExtensionMakesNoConvertCall(): void
    {
        $service = $this->buildService([], 'public://2026-09/report.fodt', false);

        $service->process(['mid' => 1, 'fid' => 10, 'extension' => 'fodt']);

        $this->assertCount(0, $service->convertCalls);
    }

    /**
     * A via-office extension (e.g. doc) makes exactly two chained calls: to
     * its office intermediate (no PageRange, that converter does not
     * document it), then intermediate to webp (chained by stored file,
     * PageRange=1 restored on this step).
     *
     * @covers ::process
     */
    public function testProcessViaOfficeExtensionMakesExactlyTwoChainedCalls(): void
    {
        $officeFile = new ResultFile('DOCXBYTES');
        $officeResult = new Result($officeFile, 1);
        $webpResult = new Result(new ResultFile('WEBPBYTES'), 1);

        $service = $this->buildService([$officeResult, $webpResult], 'public://2026-09/report.doc');

        $service->process(['mid' => 1, 'fid' => 10, 'extension' => 'doc']);

        $this->assertCount(2, $service->convertCalls);
        $this->assertSame('docx', $service->convertCalls[0]['to']);
        $this->assertSame('doc', $service->convertCalls[0]['from']);
        $this->assertArrayNotHasKey('PageRange', $service->convertCalls[0]['params'], 'doc-to-docx does not document PageRange.');
        $this->assertSame('webp', $service->convertCalls[1]['to']);
        $this->assertSame('docx', $service->convertCalls[1]['from']);
        $this->assertSame($officeFile, $service->convertCalls[1]['params']['File'], 'The second call must chain the first result\'s stored file.');
        $this->assertSame('1', $service->convertCalls[1]['params']['PageRange']);
        $this->assertArrayNotHasKey('ScaleProportions', $service->convertCalls[1]['params'], 'docx-to-webp does not document ScaleProportions.');
    }

    /**
     * If an editor sets their own Image after the item was enqueued (cron can
     * drain up to 60s later), process() must re-check ownership on the
     * CURRENT Image field and skip - never overwrite it.
     *
     * @covers ::process
     */
    public function testProcessSkipsWhenEditorSetImageSinceEnqueue(): void
    {
        $service = $this->buildService(
            [],
            'public://2026-09/report.pdf',
            false,
            77,
            'public://2026-09/editor-photo.jpg'
        );

        $service->process(['mid' => 1, 'fid' => 10, 'extension' => 'pdf']);

        $this->assertCount(0, $service->convertCalls, 'No ConvertAPI call should be made once an editor image is present.');
    }

    /**
     * A module-owned current Image (e.g. from a prior run) is not treated as
     * an editor override; process() still converts and overwrites it.
     *
     * @covers ::process
     */
    public function testProcessOverwritesItsOwnPriorPreview(): void
    {
        $service = $this->buildService(
            [new Result(new ResultFile('BYTES'), 1)],
            'public://2026-09/report.pdf',
            true,
            77,
            'public://bioland/document-previews/1-9.webp'
        );

        $service->process(['mid' => 1, 'fid' => 10, 'extension' => 'pdf']);

        $this->assertCount(1, $service->convertCalls);
    }

    /**
     * If the Image field held a module preview at enqueue time (recorded in
     * the queue payload) and is empty at process() time, an editor
     * deliberately cleared it: process() must skip, never overwrite it.
     *
     * @covers ::process
     */
    public function testProcessSkipsWhenModuleImageWasExplicitlyCleared(): void
    {
        $service = $this->buildService([], 'public://2026-09/report.pdf', false, null);

        $service->process(['mid' => 1, 'fid' => 10, 'extension' => 'pdf', 'hadModuleImage' => true]);

        $this->assertCount(0, $service->convertCalls, 'No ConvertAPI call should be made once a module image was deliberately cleared.');
    }

    /**
     * If the Image field was empty at enqueue time (no 'hadModuleImage' flag)
     * and is still empty at process() time, process() proceeds normally.
     *
     * @covers ::process
     */
    public function testProcessProceedsWhenImageWasNeverSet(): void
    {
        $service = $this->buildService([new Result(new ResultFile('BYTES'), 1)], 'public://2026-09/report.pdf', true, null);

        $service->process(['mid' => 1, 'fid' => 10, 'extension' => 'pdf']);

        $this->assertCount(1, $service->convertCalls);
    }

    /**
     * Builds a queue factory whose queue records createItem/deleteItem calls.
     *
     * @return array{0: object, 1: object}
     *   The QueueFactory mock and the QueueInterface mock.
     */
    private function buildQueue(): array
    {
        $queue = $this->createMock('Drupal\Core\Queue\QueueInterface');
        $queueFactory = $this->createMock('Drupal\Core\Queue\QueueFactory');
        $queueFactory->method('get')->with(BiolandDocumentPreviewService::QUEUE_ID)->willReturn($queue);

        return [$queueFactory, $queue];
    }

    /**
     * Builds the media entity as hook_entity_insert() hands it over.
     */
    private function buildSavedMedia(int $fid): ContentEntityInterface
    {
        $media = $this->createMock(ContentEntityInterface::class);
        $media->method('id')->willReturn(1);
        $media->method('getEntityTypeId')->willReturn('media');
        $media->method('bundle')->willReturn('document');
        $media->method('hasField')->willReturn(true);
        $media->method('get')->willReturnMap([
            [Policy::DOCUMENT_FIELD, new FakeFieldItem($fid)],
            [Policy::IMAGE_FIELD, new FakeFieldItem(null, true)],
        ]);

        return $media;
    }

    /**
     * Saving a Document media converts right after the response, not on cron:
     * destruct() runs process() and deletes the queue item it recorded.
     *
     * @covers ::enqueueIfNeeded
     * @covers ::destruct
     */
    public function testDestructConvertsPendingItemAndDeletesItFromQueue(): void
    {
        putenv('CONVERT_API_SECRET=secret');
        [$queueFactory, $queue] = $this->buildQueue();
        $queue->expects($this->once())->method('createItem')->willReturn(42);
        $queue->expects($this->once())->method('deleteItem')->with($this->callback(function ($item) {
            return $item->item_id === 42;
        }));

        $service = $this->buildService(
            [new Result(new ResultFile('BYTES'), 1)],
            'public://2026-09/report.pdf',
            true,
            null,
            'public://2026-09/editor-photo.jpg',
            $queueFactory
        );

        $service->enqueueIfNeeded($this->buildSavedMedia(10), 'insert');
        $this->assertCount(0, $service->convertCalls, 'Nothing converts inside the request.');

        $service->destruct();

        $this->assertCount(1, $service->convertCalls);
        $service->destruct();
        $this->assertCount(1, $service->convertCalls, 'A second destruct() has nothing pending.');
        putenv('CONVERT_API_SECRET');
    }

    /**
     * Two saves of the same media/document in one request enqueue one item.
     *
     * @covers ::enqueueIfNeeded
     */
    public function testEnqueueDedupesSameMediaAndDocumentWithinRequest(): void
    {
        putenv('CONVERT_API_SECRET=secret');
        [$queueFactory, $queue] = $this->buildQueue();
        $queue->expects($this->once())->method('createItem')->willReturn(7);

        $service = $this->buildService([], 'public://2026-09/report.pdf', false, null, 'public://x.jpg', $queueFactory);
        $media = $this->buildSavedMedia(10);
        $service->enqueueIfNeeded($media, 'insert');
        $service->enqueueIfNeeded($media, 'insert');
        putenv('CONVERT_API_SECRET');
    }

    /**
     * A conversion failure in destruct() leaves the item queued for the
     * cron worker's bounded retry, and never throws past the destructor.
     *
     * @covers ::destruct
     */
    public function testDestructLeavesFailedItemInQueue(): void
    {
        putenv('CONVERT_API_SECRET=secret');
        [$queueFactory, $queue] = $this->buildQueue();
        $queue->method('createItem')->willReturn(42);
        $queue->expects($this->never())->method('deleteItem');

        // No convert results injected: convert() returns null and process() fails.
        $service = $this->buildService([], 'public://2026-09/report.pdf', false, null, 'public://x.jpg', $queueFactory);
        $service->enqueueIfNeeded($this->buildSavedMedia(10), 'insert');

        $service->destruct();

        $this->assertCount(1, $service->convertCalls);
        putenv('CONVERT_API_SECRET');
    }
}
