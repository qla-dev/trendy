<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function resolveUserTableConnectionMeta(): array
    {
        $user = new User();
        $connectionName = $user->getConnectionName();
        $connection = DB::connection($connectionName);
        $table = $user->getTable();
        $database = $connection->getDatabaseName();

        return [
            'connection_name' => $connectionName,
            'connection' => $connection,
            'driver' => $connection->getDriverName(),
            'table' => $table,
            'database' => $database,
        ];
    }

    private function resolveSqlServerQualifiedTable($connection, string $table): string
    {
        if (str_contains($table, '.')) {
            [$schema, $tableName] = array_pad(explode('.', $table, 2), 2, null);

            return sprintf('[%s].[%s]', $schema ?: 'dbo', $tableName ?: $table);
        }

        $schemaRow = $connection->selectOne(
            'SELECT TOP 1 TABLE_SCHEMA FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = ? ORDER BY CASE WHEN TABLE_SCHEMA = ? THEN 0 ELSE 1 END, TABLE_SCHEMA',
            [$table, 'dbo']
        );

        $schema = $schemaRow->TABLE_SCHEMA ?? 'dbo';

        return sprintf('[%s].[%s]', $schema, $table);
    }

    public function up(): void
    {
        $meta = $this->resolveUserTableConnectionMeta();
        $connection = $meta['connection'];
        $driver = $meta['driver'];
        $table = $meta['table'];

        if ($driver === 'sqlsrv') {
            $qualifiedTable = $this->resolveSqlServerQualifiedTable($connection, $table);

            $connection->unprepared(<<<SQL
IF EXISTS (
    SELECT 1
    FROM sys.key_constraints
    WHERE [type] = 'UQ'
      AND [name] = 'users_email_unique'
      AND [parent_object_id] = OBJECT_ID(N'{$qualifiedTable}')
)
    ALTER TABLE {$qualifiedTable} DROP CONSTRAINT [users_email_unique];

IF EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE [name] = 'users_email_unique'
      AND [object_id] = OBJECT_ID(N'{$qualifiedTable}')
)
    DROP INDEX [users_email_unique] ON {$qualifiedTable};

ALTER TABLE {$qualifiedTable} ALTER COLUMN [email] NVARCHAR(255) NULL;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE [name] = 'users_email_unique'
      AND [object_id] = OBJECT_ID(N'{$qualifiedTable}')
)
    CREATE UNIQUE NONCLUSTERED INDEX [users_email_unique]
        ON {$qualifiedTable} ([email])
        WHERE [email] IS NOT NULL;
SQL);

            return;
        }

        if ($driver === 'mysql') {
            $connection->statement(sprintf(
                'ALTER TABLE `%s` MODIFY `email` VARCHAR(255) NULL',
                str_replace('`', '``', $table)
            ));
            return;
        }

        if ($driver === 'pgsql') {
            $connection->statement(sprintf(
                'ALTER TABLE "%s" ALTER COLUMN "email" DROP NOT NULL',
                str_replace('"', '""', $table)
            ));
        }
    }

    public function down(): void
    {
        $meta = $this->resolveUserTableConnectionMeta();
        $connection = $meta['connection'];
        $driver = $meta['driver'];
        $table = $meta['table'];

        if ($driver === 'sqlsrv') {
            $qualifiedTable = $this->resolveSqlServerQualifiedTable($connection, $table);

            $connection->unprepared(<<<SQL
IF EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE [name] = 'users_email_unique'
      AND [object_id] = OBJECT_ID(N'{$qualifiedTable}')
)
    DROP INDEX [users_email_unique] ON {$qualifiedTable};

UPDATE {$qualifiedTable}
SET [email] = CONCAT('user', CAST([id] AS NVARCHAR(20)), '@local.invalid')
WHERE [email] IS NULL OR LTRIM(RTRIM([email])) = '';

ALTER TABLE {$qualifiedTable} ALTER COLUMN [email] NVARCHAR(255) NOT NULL;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE [name] = 'users_email_unique'
      AND [object_id] = OBJECT_ID(N'{$qualifiedTable}')
)
    CREATE UNIQUE NONCLUSTERED INDEX [users_email_unique]
        ON {$qualifiedTable} ([email]);
SQL);

            return;
        }

        if ($driver === 'mysql') {
            $quotedTable = sprintf('`%s`', str_replace('`', '``', $table));
            $connection->statement("UPDATE {$quotedTable} SET `email` = CONCAT('user', `id`, '@local.invalid') WHERE `email` IS NULL OR TRIM(`email`) = ''");
            $connection->statement("ALTER TABLE {$quotedTable} MODIFY `email` VARCHAR(255) NOT NULL");
            return;
        }

        if ($driver === 'pgsql') {
            $quotedTable = sprintf('"%s"', str_replace('"', '""', $table));
            $connection->statement("UPDATE {$quotedTable} SET \"email\" = CONCAT('user', \"id\"::text, '@local.invalid') WHERE \"email\" IS NULL OR BTRIM(\"email\") = ''");
            $connection->statement("ALTER TABLE {$quotedTable} ALTER COLUMN \"email\" SET NOT NULL");
        }
    }
};
