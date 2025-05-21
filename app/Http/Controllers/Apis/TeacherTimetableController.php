<?php

namespace App\Http\Controllers\Apis;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;

class TeacherTimetableController extends Controller
{
    public function todayTimetable(){
        try{
            // $apiGatewayUrl = config('services.api_gateway.url'). 'me';
            // $api_response = Http::withHeaders([
            //     'Accept' => 'application/json',
            //     'Authorization' => request()->header('Authorization'),
            // ])->post($apiGatewayUrl, []);
            
            $today = Carbon::today()->toDateString();

            

            

        }catch(Exception $e){
            return response()->json([
                'success' => false,
                'message' => 'An error occured: ' . $e->getMessage(),
            ]);
        }
    }
}
