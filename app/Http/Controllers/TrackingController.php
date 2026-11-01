<?php

namespace App\Http\Controllers;

use App\Models\Procurement;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        
        // Query data berdasarkan role user
        $query = Procurement::with('histories');

        if ($user->role === 'perencanaan') {
            // Role perencanaan hanya melihat pengajuan milik sendiri/unitnya
            $query->where('user_id', $user->id);
        }

        // Filter pencarian
        if ($request->filled('keyword')) {
            $query->where('title', 'like', '%' . $request->keyword . '%');
        }
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        $applications = $query->latest()->get();
        
        // Ambil item terpilih berdasarkan ID atau panggil item pertama
        $selectedApplication = $request->filled('id') 
            ? $applications->firstWhere('id', $request->id) 
            : $applications->first();

        // Panggil view di folder permohonan/index.blade.php
        return view('permohonan.index', compact('applications', 'selectedApplication'));
    }

    public function show($id)
    {
        $selectedApplication = Procurement::with(['histories', 'documents'])->findOrFail($id);
        
        // Panggil view di folder permohonan/show.blade.php
        return view('permohonan.show', compact('selectedApplication'));
    }
}