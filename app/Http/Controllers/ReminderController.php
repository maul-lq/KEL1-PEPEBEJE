<?php

namespace App\Http\Controllers;

use App\Models\Reminder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ReminderController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();
        $reminders = Reminder::where(function ($q) use ($user) {
            $q->where('target_role', $user->role)
                ->orWhere('user_id', $user->id)
                ->orWhereNull('target_role');
        })
            ->with('pengadaan')
            ->latest()
            ->paginate(15);

        return view('reminders.index', compact('reminders'));
    }

    public function dismiss(Reminder $reminder): JsonResponse|RedirectResponse
    {
        $reminder->update([
            'is_dismissed' => true,
            'is_read' => true,
        ]);

        if (request()->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Pengingat telah ditandai selesai.');
    }
}
