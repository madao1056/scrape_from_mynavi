<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MynaviJobs extends Model
{
    use HasFactory;
    protected $guarded = []; //[]が空であれば全て許可される→ ScrapeMynavi.phpのsaveJobs()関数のcreate()
}