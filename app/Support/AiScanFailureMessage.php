<?php

namespace App\Support;

class AiScanFailureMessage
{
    public static function workOrderCreate(string $message): string
    {
        if (self::isTechnical($message)) {
            if (stripos($message, 'rtHF_WOEx_tHE_SetItem_3') !== false) {
                return 'Radni nalog nije moguće kreirati jer šifra artikla nije usklađena s Pantheon katalogom. Kontaktirajte administratora.';
            }

            if (stripos($message, 'rtHF_WOEx_tHE_CostDrv_7') !== false) {
                return 'Pantheon nije prihvatio odabranu površinsku zaštitu. Ponovo je odaberite ili kontaktirajte administratora.';
            }
        }

        return 'Radni nalog trenutno nije moguće kreirati. Pokušajte ponovo ili kontaktirajte administratora.';
    }

    public static function isTechnical(string $message): bool
    {
        return preg_match(
            '/sqlstate|queryexception|\bselect\b.+\bfrom\b|\binsert\s+statement\b|\bforeign\s+key\b|\bconstraint\b|\bdbo\.|\btable\s+[\'"`]|\bcolumn\s+[\'"`]|\bsyntax\s+error\b/iu',
            $message
        ) === 1;
    }

    public static function transfer(string $message): ?string
    {
        if (preg_match('/(?:acClassif|tHE_SetItemCateg)/iu', $message) === 1 && self::isTechnical($message)) {
            return 'Pantheon nije prihvatio klasifikaciju novog artikla. Pokušajte ponovo ili kontaktirajte administratora.';
        }

        if (self::isTechnical($message)) {
            return 'Transfer nije uspio zbog interne greške pri upisu u bazu. Pokušajte ponovo ili kontaktirajte administratora.';
        }

        return null;
    }
}
