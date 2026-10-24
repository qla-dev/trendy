<?php

namespace App\Support;

class AiScanFailureMessage
{
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
