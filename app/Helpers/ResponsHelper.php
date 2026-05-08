<?php

namespace App\Helpers;

class ResponsHelper
{
    public static function success($data, $message = 'Success', $code = 200)
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ], $code);
    }

    public static function error($message = 'Error', $code = 500)
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
        ], $code);
    }
}