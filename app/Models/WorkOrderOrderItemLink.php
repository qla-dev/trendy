<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class WorkOrderOrderItemLink extends Model
{
    use HasFactory;

    private static ?array $sourceColumnsCache = null;
    private static ?string $sourceTableCache = null;

    protected $connection = 'sqlsrv';
    protected $primaryKey = 'acKey';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    public function getTable(): string
    {
        return self::qualifiedSourceTableName();
    }

    public static function sourceSchema(): string
    {
        return (string) config('workorders.schema', 'dbo');
    }

    public static function sourceTableName(): string
    {
        if (self::$sourceTableCache !== null) {
            return self::$sourceTableCache;
        }

        $configuredTable = trim((string) config('workorders.work_order_order_item_link_table', 'vHF_LinkWOExOrderItem'));
        $candidates = array_values(array_unique(array_filter([
            $configuredTable,
            'vHF_LinkWOExOrderItem',
            'tHF_LinkWOExOrderItem',
        ])));

        foreach ($candidates as $candidate) {
            $exists = self::db()
                ->table('INFORMATION_SCHEMA.COLUMNS')
                ->where('TABLE_SCHEMA', self::sourceSchema())
                ->where('TABLE_NAME', $candidate)
                ->exists();

            if ($exists) {
                self::$sourceTableCache = (string) $candidate;

                return self::$sourceTableCache;
            }
        }

        self::$sourceTableCache = $configuredTable !== '' ? $configuredTable : 'tHF_LinkWOExOrderItem';

        return self::$sourceTableCache;
    }

    public static function qualifiedSourceTableName(): string
    {
        return self::sourceSchema() . '.' . self::sourceTableName();
    }

    public static function sourceColumns(): array
    {
        if (self::$sourceColumnsCache !== null) {
            return self::$sourceColumnsCache;
        }

        self::$sourceColumnsCache = self::db()
            ->table('INFORMATION_SCHEMA.COLUMNS')
            ->where('TABLE_SCHEMA', self::sourceSchema())
            ->where('TABLE_NAME', self::sourceTableName())
            ->pluck('COLUMN_NAME')
            ->map(function ($columnName) {
                return (string) $columnName;
            })
            ->values()
            ->all();

        return self::$sourceColumnsCache;
    }

    public static function newSourceQuery(): QueryBuilder
    {
        return self::db()->table(self::qualifiedSourceTableName());
    }

    private static function db()
    {
        return DB::connection((new static())->getConnectionName());
    }
}
