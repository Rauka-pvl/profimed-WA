<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PatientDeviceToken extends Model
{
    protected $fillable = ['patient_id', 'device_token', 'device_type'];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
}
