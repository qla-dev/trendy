<?php

namespace App\Http\Controllers;

/** Lists the raw-material to WIP transfer documents created during scan. */
class WipReleaseDocumentController extends ReleasedMaterialDocumentController
{
    protected const DOCUMENT_TYPE = '2005';
    protected const DATA_ROUTE = 'app-wip-release-documents-data';
    protected const DELETE_ROUTE = 'app-wip-release-documents-destroy';
    protected const TITLE = 'Razduživanje WIP';
    protected const SUBTITLE = 'Prenos materijala u skladište proizvodnje u toku';
}
