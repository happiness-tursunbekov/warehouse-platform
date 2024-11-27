<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class Log
{
    public static function create(string $entityType, int $entityId, $data)
    {
        $data = [
            'action_by_type' => '',
            'action_by_id' => '',
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'data' => $data
        ];

        $user = request()->user();
        $system = request()->system();

        if ($user) {
            $data['action_by_type'] = 'App\Models\User';
            $data['action_by_id'] = $user->id;
        } elseif ($system) {
            $data['action_by_type'] = 'App\Models\System';
            $data['action_by_id'] = $system->id;
        }

        DB::table('logs')->create($data);
    }
}
