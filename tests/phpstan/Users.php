<?php

class Users extends \Illuminate\Database\Eloquent\Model
{
    use \Zap\Models\Concerns\HasSchedules;

    protected $table = 'users';

    protected $fillable = ['name', 'email'];

    public function getKey()
    {
        return 1;
    }
}
