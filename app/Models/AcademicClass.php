<?php

namespace App\Models;

use Ramsey\Uuid\Guid\Guid;
use App\Models\AcademicClassSection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AcademicClass extends Model
{
    use SoftDeletes;

    protected $fillable = ['slug','name'];

    protected $hidden = ["id","created_at","updated_at","deleted_at"];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if(empty($model->slug)){
                $model->slug = (string) Guid::uuid4();
            }
        });
    }

    public function academicClassSections()
    {
        return $this->hasMany(AcademicClassSection::class);
    }
}
