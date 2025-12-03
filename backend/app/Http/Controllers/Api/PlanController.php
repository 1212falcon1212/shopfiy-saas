<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $plans = Plan::where('is_active', true)->get();

        // Özellikleri ve isimleri frontend'in kolay işlemesi için olduğu gibi (JSON) döndürüyoruz.
        // Frontend tarafında dil seçimine göre gösterilecek.
        
        return response()->json([
            'success' => true,
            'data' => $plans
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $plan = Plan::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $plan
        ]);
    }
}
