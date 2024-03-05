<?php

namespace Dimita\BusinessOrchestration\Models;

use Illuminate\Database\Eloquent\Model;

class Dependency extends Model
{
    protected $fillable = ['source_model', 'target_model', 'rule'];
}