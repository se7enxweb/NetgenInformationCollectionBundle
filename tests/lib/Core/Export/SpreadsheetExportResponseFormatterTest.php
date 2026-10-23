<?php

declare(strict_types=1);

namespace Netgen\InformationCollection\Tests\Core\Export;

use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Core\Helper\TranslationHelper;
use Netgen\InformationCollection\API\Export\ExportResponseFormatter;
use Netgen\InformationCollection\API\Value\Export\Export;
use Netgen\InformationCollection\Core\Export\XlsExportResponseFormatter;
use Netgen\InformationCollection\Core\Export\XlsxExportResponseFormatter;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Slugger\AsciiSlugger;

use function file_put_contents;
use function glob;
use function is_dir;
use function mkdir;
use function ob_get_clean;
use function ob_start;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * Writes an export with each PhpSpreadsheet formatter and reads the file back, so the
 * formatters are checked against whichever PhpSpreadsheet release is installed.
 *
 * @covers \Netgen\InformationCollection\Core\Export\XlsExportResponseFormatter
 * @covers \Netgen\InformationCollection\Core\Export\XlsxExportResponseFormatter
 */
final class SpreadsheetExportResponseFormatterTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/ic-export-test-' . uniqid();
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    public function formatterProvider(): array
    {
        return [
            'xls' => ['xls', 'phpexcel_xls_export'],
            'xlsx' => ['xlsx', 'phpexcel_xlsx_export'],
        ];
    }

    /**
     * @dataProvider formatterProvider
     */
    public function testFormatToFileWritesAReadableSpreadsheet(string $type, string $identifier): void
    {
        $formatter = $this->createFormatter($type, 'Contact form');

        self::assertSame($identifier, $formatter->getIdentifier());

        $file = $formatter->formatToFile($this->createExport(), $this->createMock(Content::class), $this->directory);

        self::assertSame($type, $file->getExtension());
        $this->assertSpreadsheetContents($file->getPathname(), 'Contact form');
    }

    /**
     * @dataProvider formatterProvider
     *
     * format() sends headers, which needs a process with no output yet.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testFormatStreamsAReadableSpreadsheet(string $type): void
    {
        $formatter = $this->createFormatter($type, 'Contact form');

        ob_start();
        $response = $formatter->format($this->createExport(), $this->createMock(Content::class));
        $output = (string) ob_get_clean();

        self::assertSame('', $response->getContent());

        $path = $this->directory . '/streamed.' . $type;
        file_put_contents($path, $output);

        $this->assertSpreadsheetContents($path, 'Contact form');
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testXlsxFallsBackToADefaultSheetTitle(): void
    {
        // A colon is not allowed in a worksheet title; the formatter must catch the exception.
        $formatter = $this->createFormatter('xlsx', 'Contact: form');

        ob_start();
        $formatter->format($this->createExport(), $this->createMock(Content::class));
        $output = (string) ob_get_clean();

        $path = $this->directory . '/fallback.xlsx';
        file_put_contents($path, $output);

        $this->assertSpreadsheetContents($path, 'Information collection export');
    }

    private function createFormatter(string $type, string $contentName): ExportResponseFormatter
    {
        $translationHelper = $this->createMock(TranslationHelper::class);
        $translationHelper->method('getTranslatedContentName')->willReturn($contentName);

        return $type === 'xls'
            ? new XlsExportResponseFormatter($translationHelper, new AsciiSlugger())
            : new XlsxExportResponseFormatter($translationHelper, new AsciiSlugger());
    }

    private function createExport(): Export
    {
        return new Export(
            ['Name', 'Email', 'Message', 'Created'],
            [
                ['Ana', 'ana@example.invalid', 'Hello', '2026-10-23 10:00:00'],
                ['Ben', 'ben@example.invalid', '=1+1', '2026-10-23 11:00:00'],
            ],
        );
    }

    private function assertSpreadsheetContents(string $path, string $sheetTitle): void
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();

        self::assertSame($sheetTitle, $sheet->getTitle());
        self::assertSame(
            [
                ['Name', 'Email', 'Message', 'Created'],
                ['Ana', 'ana@example.invalid', 'Hello', '2026-10-23 10:00:00'],
                ['Ben', 'ben@example.invalid', '=1+1', '2026-10-23 11:00:00'],
            ],
            $sheet->toArray(null, false, false, false),
        );
    }
}
