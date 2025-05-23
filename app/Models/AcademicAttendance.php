<?php

namespace App\Models;

use Ramsey\Uuid\Guid\Guid;
use App\Models\DailySchedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AcademicAttendance extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'slug',
        'previous_hash',
        'hash',
        'attendee_type',
        'attendee_id',
        'schedule_id',
        'status',
        'date',
        'remark',
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

    public function schedule()
    {
        return $this->belongsTo(DailySchedule::class, 'schedule_id');
    }
}
