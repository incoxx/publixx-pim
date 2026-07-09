<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Collections Sub-Flags
    |--------------------------------------------------------------------------
    |
    | Feingranulare Schalter fuer risikoreiche Teilfaehigkeiten des Collections-
    | Moduls. Diese wirken zusaetzlich zur (uebergeordneten) Enterprise-Lizenz
    | fuer das Modul 'collections' (siehe config/license.php) -- niemals anstelle
    | davon. So kann ein Deployment z.B. Collections lizenziert haben, aber den
    | RFQ-Import oder den openTRANS-Export separat ausgeschaltet lassen.
    |
    */

    'import_enabled' => env('COLLECTIONS_IMPORT_ENABLED', false),

    'snapshot_enabled' => env('COLLECTIONS_SNAPSHOT_ENABLED', false),

    'export_opentrans_enabled' => env('COLLECTIONS_EXPORT_OPENTRANS_ENABLED', false),

];
