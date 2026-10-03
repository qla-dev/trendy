<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Users live on the local MySQL connection, never on Pantheon (sqlsrv).
 * The connection is pinned here so a plain `php artisan migrate` can not
 * touch the Pantheon database by accident.
 */
return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        if (Schema::connection('mysql')->hasColumn('users', 'nfc_card_uid')) {
            return;
        }

        Schema::connection('mysql')->table('users', function (Blueprint $table) {
            $table->string('nfc_card_uid', 64)->nullable()->unique()->after('role');
            $table->timestamp('nfc_card_linked_at')->nullable()->after('nfc_card_uid');
        });
    }

    public function down(): void
    {
        if (!Schema::connection('mysql')->hasColumn('users', 'nfc_card_uid')) {
            return;
        }

        Schema::connection('mysql')->table('users', function (Blueprint $table) {
            $table->dropUnique(['nfc_card_uid']);
            $table->dropColumn(['nfc_card_uid', 'nfc_card_linked_at']);
        });
    }
};
