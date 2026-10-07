<?php

namespace App\Models;

use App\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Etiqueta extends Model
{
    /** @use HasFactory<\Database\Factories\EtiquetaFactory> */
    use BelongsToUser, HasFactory;

    protected $table = 'etiquetas';

    protected $fillable = [
        'user_id',
        'name',
        'color',
    ];

    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(ChartOfAccount::class, 'account_etiqueta', 'etiqueta_id', 'account_id');
    }
}
