<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class SessionUser
{
    public static function uuid(): ?string
    {
        $id = session('auth_user_id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    public static function binary(): ?string
    {
        $id = self::uuid();

        return $id ? UuidBin::to($id) : null;
    }

    public static function actorBinary(): ?string
    {
        $bin = self::binary();
        if ($bin) {
            return $bin;
        }

        $row = DB::table('users')->orderBy('created_at')->first();

        return $row->id ?? null;
    }

    public static function profile(): ?array
    {
        $bin = self::binary();
        if (! $bin) {
            return null;
        }

        $row = DB::table('users')->where('id', $bin)->first();
        if (! $row) {
            return null;
        }

        return [
            'id' => UuidBin::from($row->id),
            'username' => $row->username,
            'first_name' => $row->first_name,
            'last_name' => $row->last_name,
            'name' => trim(($row->first_name ?? '').' '.($row->last_name ?? '')),
        ];
    }
}
