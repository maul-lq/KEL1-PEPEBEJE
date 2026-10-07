<?php

namespace App\Http\Controllers;

use App\Models\Reminder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReminderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Reminder::with(['paket', 'user']);

        if ($request->filled('target_role')) {
            $query->where('target_role', $request->target_role);
        }

        if ($request->filled('tipe_reminder')) {
            $query->where('tipe_reminder', $request->tipe_reminder);
        }

        if ($request->filled('is_read')) {
            $query->where('is_read', $request->boolean('is_read'));
        }

        if ($request->filled('is_dismissed')) {
            $query->where('is_dismissed', $request->boolean('is_dismissed'));
        }

        $reminders = $query->latest('tanggal_pemicu')->paginate(15);

        return response()->json([
            'status' => 'success',
            'data' => $reminders,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'roles' => [
                Reminder::ROLE_PPBJ,
                Reminder::ROLE_PP,
                Reminder::ROLE_PPK,
                Reminder::ROLE_KEUANGAN,
            ],
            'types' => [
                Reminder::TIPE_PENGIRIMAN,
                Reminder::TIPE_H_MINUS_2_TERMIN,
                Reminder::TIPE_BULANAN_TAHUNAN,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nomor_paket' => ['required', 'exists:paket_pengadaan,nomor_paket'],
            'target_role' => ['required', 'string', Rule::in([
                Reminder::ROLE_PPBJ,
                Reminder::ROLE_PP,
                Reminder::ROLE_PPK,
                Reminder::ROLE_KEUANGAN,
            ])],
            'tipe_reminder' => ['required', 'string', Rule::in([
                Reminder::TIPE_PENGIRIMAN,
                Reminder::TIPE_H_MINUS_2_TERMIN,
                Reminder::TIPE_BULANAN_TAHUNAN,
            ])],
            'judul_alert' => ['required', 'string', 'max:150'],
            'pesan_alert' => ['required', 'string'],
            'tanggal_pemicu' => ['required', 'date'],
            'is_read' => ['nullable', 'boolean'],
            'is_dismissed' => ['nullable', 'boolean'],
            'id_user' => ['nullable', 'exists:users,id_user'],
        ]);

        $validated['is_read'] = $request->boolean('is_read', false);
        $validated['is_dismissed'] = $request->boolean('is_dismissed', false);

        $reminder = Reminder::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Pengingat (reminder) berhasil dibuat',
            'data' => $reminder,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id): JsonResponse
    {
        $reminder = Reminder::with(['paket', 'user'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $reminder,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id): JsonResponse
    {
        $reminder = Reminder::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $reminder,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $reminder = Reminder::findOrFail($id);

        $validated = $request->validate([
            'target_role' => ['sometimes', 'required', 'string', Rule::in([
                Reminder::ROLE_PPBJ,
                Reminder::ROLE_PP,
                Reminder::ROLE_PPK,
                Reminder::ROLE_KEUANGAN,
            ])],
            'tipe_reminder' => ['sometimes', 'required', 'string', Rule::in([
                Reminder::TIPE_PENGIRIMAN,
                Reminder::TIPE_H_MINUS_2_TERMIN,
                Reminder::TIPE_BULANAN_TAHUNAN,
            ])],
            'judul_alert' => ['sometimes', 'required', 'string', 'max:150'],
            'pesan_alert' => ['sometimes', 'required', 'string'],
            'tanggal_pemicu' => ['sometimes', 'required', 'date'],
            'is_read' => ['nullable', 'boolean'],
            'is_dismissed' => ['nullable', 'boolean'],
            'id_user' => ['nullable', 'exists:users,id_user'],
        ]);

        if ($request->has('is_read')) {
            $validated['is_read'] = $request->boolean('is_read');
        }

        if ($request->has('is_dismissed')) {
            $validated['is_dismissed'] = $request->boolean('is_dismissed');
        }

        $reminder->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Pengingat berhasil diperbarui',
            'data' => $reminder,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id): JsonResponse
    {
        $reminder = Reminder::findOrFail($id);
        $reminder->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Pengingat berhasil dihapus',
        ]);
    }
}
