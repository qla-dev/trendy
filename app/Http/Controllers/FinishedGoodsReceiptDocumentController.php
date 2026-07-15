<?php

namespace App\Http\Controllers;

class FinishedGoodsReceiptDocumentController extends ReleasedMaterialDocumentController
{
    protected const DOCUMENT_TYPE = '6100';
    protected const DATA_ROUTE = 'app-finished-goods-receipt-documents-data';
    protected const DELETE_ROUTE = 'app-finished-goods-receipt-documents-destroy';
    protected const TITLE = 'Prijem VP skladište';
    protected const SUBTITLE = 'Prijem gotovih proizvoda iz radnog naloga';
}
