<?php

namespace App\Http\Controllers;

class ScrapReceiptDocumentController extends ReleasedMaterialDocumentController
{
    protected const DOCUMENT_TYPE = '7100';
    protected const DATA_ROUTE = 'app-scrap-receipt-documents-data';
    protected const DELETE_ROUTE = 'app-scrap-receipt-documents-destroy';
    protected const TITLE = 'Prijem škarta';
    protected const SUBTITLE = 'Prijem škarta iz radnog naloga';
}
