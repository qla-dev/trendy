<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class OrderItemMoveLink extends Model
{
    use HasFactory;

    private static ?array $sourceColumnsCache = null;

    protected $connection = 'sqlsrv';
    protected $primaryKey = 'anQId';
    protected $keyType = 'int';
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
        return (string) config('workorders.order_item_move_link_table', 'tHE_LinkMoveItemOrderItem');
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
