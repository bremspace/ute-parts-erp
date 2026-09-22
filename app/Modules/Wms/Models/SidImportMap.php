<?php

namespace App\Modules\Wms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['kode_sumber', 'tabel_sumber', 'entity_type', 'entity_id'])]
class SidImportMap extends Model
{
    protected $table = 'sid_import_map';

    protected $casts = [
        'entity_id' => 'integer',
    ];

    public static function getId(string $kodeSumber, string $tabelSumber, string $entityType): ?int
    {
        return static::where('kode_sumber', $kodeSumber)
            ->where('tabel_sumber', $tabelSumber)
            ->where('entity_type', $entityType)
            ->value('entity_id');
    }

    public static function setId(string $kodeSumber, string $tabelSumber, string $entityType, int $entityId): void
    {
        static::updateOrCreate(
            ['kode_sumber' => $kodeSumber, 'tabel_sumber' => $tabelSumber],
            ['entity_type' => $entityType, 'entity_id' => $entityId]
        );
    }

    public static function exists(string $kodeSumber, string $tabelSumber): bool
    {
        return static::where('kode_sumber', $kodeSumber)
            ->where('tabel_sumber', $tabelSumber)
            ->exists();
    }
}
