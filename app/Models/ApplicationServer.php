<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ApplicationServer extends Pivot
{
    public $incrementing = true;
    protected $table = 'application_servers';
    protected $fillable = ['application_id','server_id','role','is_primary','is_required','notes'];
}
