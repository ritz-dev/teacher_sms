<?php

namespace App\Models;

use Ramsey\Uuid\Guid\Guid;
use App\Models\AcademicClassSection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentEnrollment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'slug',
        'student_id',
        'academic_year_id',
        'academic_class_section_id',
        'roll_number',
        'admission_date',
        'enrollment_type',
        'previous_school',
        'graduation_date',
        'status',
        'remarks',
    ];

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

    public function academicClassSection()
    {
        return $this->belongsTo(AcademicClassSection::class);
    }

}
