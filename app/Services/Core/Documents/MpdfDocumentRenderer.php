<?php

namespace App\Services\Core\Documents;

use App\Services\Core\Documents\DTO\DocumentRenderRequest;
use App\Services\Core\Documents\DTO\DocumentRenderResult;
use Illuminate\Support\Facades\File;
use Mpdf\Mpdf;

class MpdfDocumentRenderer implements DocumentRendererInterface
{
    public function __construct(
        protected DocumentLayoutManager $layoutManager,
        protected DocumentHeaderBuilder $headerBuilder,
        protected DocumentFooterBuilder $footerBuilder
    ) {
    }

    public function render(DocumentRenderRequest $request): DocumentRenderResult
    {
        $layout = $this->layoutManager->resolve($request->layout);
        File::ensureDirectoryExists(storage_path('app/mpdf-temp'));

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => $layout['format'] ?? 'A4',
            'orientation' => $layout['orientation'] ?? 'P',
            'default_font' => config('documents.default_font', 'dejavusans'),
            'margin_left' => $layout['margin_left'] ?? 12,
            'margin_right' => $layout['margin_right'] ?? 12,
            'margin_top' => $layout['margin_top'] ?? 36,
            'margin_bottom' => $layout['margin_bottom'] ?? 28,
            'margin_header' => $layout['margin_header'] ?? 8,
            'margin_footer' => $layout['margin_footer'] ?? 8,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'tempDir' => storage_path('app/mpdf-temp'),
        ]);

        $mpdf->SetDirectionality($request->direction === 'rtl' ? 'rtl' : 'ltr');
        $mpdf->SetHTMLHeader($this->headerBuilder->build($request->data));
        $mpdf->SetHTMLFooter($this->footerBuilder->build($request->data));

        $html = view(config('documents.templates.layout'), [
            'contentView' => $request->template,
            'data' => $request->data,
            'language' => $request->language,
            'direction' => $request->direction,
        ])->render();

        $mpdf->WriteHTML($html);

        return new DocumentRenderResult($mpdf->Output('', 'S'));
    }
}
