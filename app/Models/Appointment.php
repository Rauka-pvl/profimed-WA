<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'doctor_id',
        'patient_id',
        'service',
        'cabinet',
        'date',
        'time',
        'status',
        'reminder_24h_sent',
        'reminder_3h_sent',
    ];

    protected $casts = [
        'date' => 'date',
        'reminder_24h_sent' => 'boolean',
        'reminder_3h_sent' => 'boolean',
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
