<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Province extends Model
{
    protected $fillable = ['name', 'accounting_code'];

    public function displayLabel(): string
    {
        if ($this->accounting_code) {
            return $this->name . ' (' . $this->accounting_code . ')';
        }

        return (string) $this->name;
    }

    public function cities()
    {
        return $this->hasMany(City::class);
    }

    public function counties()
    {
        return $this->hasMany(County::class);
    }
}
