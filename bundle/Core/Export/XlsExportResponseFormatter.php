<?php

namespace Netgen\Bundle\InformationCollectionBundle\Core\Export;

use eZ\Publish\API\Repository\Values\Content\Content;
use eZ\Publish\Core\Helper\TranslationHelper;
use Netgen\Bundle\InformationCollectionBundle\API\Export\ExportResponseFormatter;
use Netgen\Bundle\InformationCollectionBundle\API\Value\Export\Export;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use Symfony\Component\HttpFoundation\Response;

final class XlsExportResponseFormatter implements ExportResponseFormatter
{
    /**
     * @var \eZ\Publish\Core\Helper\TranslationHelper
     */
    private $translationHelper;

    /**
     * XlsExportResponseFormatter constructor.
     *
     * @param \eZ\Publish\Core\Helper\TranslationHelper $translationHelper
     */
    public function __construct(TranslationHelper $translationHelper)
    {
        $this->translationHelper = $translationHelper;
    }

    public function getIdentifier()
    {
        return 'xls_export';
    }

    public function format(Export $export, Content $content)
    {
        $contentName = $this->translationHelper->getTranslatedContentName($content);

        array_unshift($export->contents, $export->header);
        $spreadsheet = new Spreadsheet();
        $spreadsheet->setActiveSheetIndex(0);

        $activeSheet = $spreadsheet->getActiveSheet();

        try {
            $activeSheet->setTitle(substr($contentName, 0, 30));
        } catch (\Exception $exception) {
            $activeSheet->setTitle('Information collection export');
        }

        $activeSheet->fromArray($export->contents);
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $contentName . '.xls"');
        header('Cache-Control: max-age=0');

        $writer = new Xls($spreadsheet);

        $writer->save('php://output');
        unset($writer);

        return new Response('');
    }
}
