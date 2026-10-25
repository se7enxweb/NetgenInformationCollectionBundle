<?php

namespace Netgen\Bundle\InformationCollectionBundle\Tests\Core\Export;

use eZ\Publish\API\Repository\Values\Content\Content;
use eZ\Publish\Core\Helper\TranslationHelper;
use Netgen\Bundle\InformationCollectionBundle\API\Value\Export\Export;
use Netgen\Bundle\InformationCollectionBundle\Core\Export\XlsExportResponseFormatter;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\TestCase;

/**
 * Writes an export with the XLS formatter and reads the file back with PhpSpreadsheet, so the
 * formatter is checked against whichever PhpSpreadsheet release is installed.
 *
 * format() sends headers, which needs a process with no output yet.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class XlsExportResponseFormatterTest extends TestCase
{
    public function testIdentifier()
    {
        $this->assertSame('xls_export', $this->createFormatter('Contact form')->getIdentifier());
    }

    public function testFormatStreamsAReadableXlsFile()
    {
        $this->assertSheet($this->streamExport('Contact form'), 'Contact form');
    }

    public function testFormatFallsBackToADefaultSheetTitle()
    {
        // A colon is not allowed in a worksheet title; the formatter catches the exception.
        $this->assertSheet($this->streamExport('Contact: form'), 'Information collection export');
    }

    private function streamExport($contentName)
    {
        $export = new Export(
            array(
                'header' => array('Name', 'Email', 'Message', 'Created'),
                'contents' => array(
                    array('Ana', 'ana@example.invalid', 'Hello', '2026-10-24 10:00:00'),
                    array('Ben', 'ben@example.invalid', 'Bye', '2026-10-24 11:00:00'),
                ),
            )
        );

        ob_start();
        $response = $this->createFormatter($contentName)->format($export, $this->getMockBuilder(Content::class)->getMock());
        $output = (string) ob_get_clean();

        $this->assertSame('', $response->getContent());
        $this->assertSame("\xD0\xCF\x11\xE0", substr($output, 0, 4), 'An XLS (BIFF8, OLE) file is written');

        $path = tempnam(sys_get_temp_dir(), 'ic-xls-');
        file_put_contents($path, $output);

        return $path;
    }

    private function assertSheet($path, $sheetTitle)
    {
        $sheet = IOFactory::createReader('Xls')->load($path)->getActiveSheet();
        unlink($path);

        $this->assertSame($sheetTitle, $sheet->getTitle());
        $this->assertSame(
            array(
                array('Name', 'Email', 'Message', 'Created'),
                array('Ana', 'ana@example.invalid', 'Hello', '2026-10-24 10:00:00'),
                array('Ben', 'ben@example.invalid', 'Bye', '2026-10-24 11:00:00'),
            ),
            $sheet->toArray(null, false, false, false)
        );
    }

    private function createFormatter($contentName)
    {
        $translationHelper = $this->getMockBuilder(TranslationHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $translationHelper->method('getTranslatedContentName')->willReturn($contentName);

        return new XlsExportResponseFormatter($translationHelper);
    }
}
