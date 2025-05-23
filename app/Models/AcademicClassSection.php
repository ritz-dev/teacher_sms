<?php

namespace App\Models;

use App\Models\Section;
use Ramsey\Uuid\Guid\Guid;
use App\Models\AcademicYear;
use App\Models\AcademicClass;
use App\Models\DailySchedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AcademicClassSection extends Model
{
    //
    use SoftDeletes;

    protected $fillable = ['id','slug'];

    protected $hidden = ["academic_year_id","class_id","section_id","created_at","updated_at","deleted_at"];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if(empty($model->slug)){
                $model->slug = (string) Guid::uuid4();
            }
        });
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function class()
    {
        return $this->belongsTo(AcademicClass::class,'class_id','id');
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function dailySchedules()
    {
        return $this->hasMany(DailySchedule::class);
    }

}
