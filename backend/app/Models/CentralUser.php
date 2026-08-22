<?php

namespace App\Models;

class CentralUser extends User
{
    protected $table = 'users';

    /**
     * Get the database connection name for the model.
     * Central users ALWAYS live in the central database.
     */
    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection', 'mysql');
    }
}
