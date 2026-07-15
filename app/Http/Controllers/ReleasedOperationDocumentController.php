<?php

namespace App\Http\Controllers;

class ReleasedOperationDocumentController extends ReleasedMaterialDocumentController
{
    protected const DOCUMENT_TYPE = '6600';
    protected const DATA_ROUTE = 'app-released-operation-documents-data';
    protected const DELETE_ROUTE = 'app-released-operation-documents-destroy';
    protected const TITLE = 'Razdužene operacije';
    protected const SUBTITLE = 'RN Rasknjiženje operacija';
}
