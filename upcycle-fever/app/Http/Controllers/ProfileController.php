<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * F-01 Technical Task: Profile Management
 * - Endpoint update profil pemain (username, avatar, pengaturan suara/grafis)
 * - Halaman/modal pengaturan profil
 * - Integrasi perubahan data profil frontend dengan database backend
 */
class ProfileController extends Controller
{
    /**
     * GET /profile — Tampilkan halaman profil pemain.
     */
    public function show(Request $request)
    {
        $user = $request->user();
        return view('profile.show', compact('user'));
    }

    /**
     * PUT /profile — Update profil pemain (username, avatar, pengaturan suara/grafis).
     * Acceptance Criteria: perubahan data tersimpan ke database.
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'            => ['required', 'string', 'min:2', 'max:50'],
            'avatar'          => ['nullable', 'string', 'max:10'],   // emoji atau kode avatar
            'sound_enabled'   => ['nullable', 'boolean'],
            'graphic_quality' => ['nullable', Rule::in(['low', 'medium', 'high'])],
            // Opsional: ganti password
            'current_password'      => ['nullable', 'string'],
            'password'              => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        // Validasi current_password jika user ingin ganti password
        if (!empty($validated['password'])) {
            if (empty($validated['current_password']) || !Hash::check($validated['current_password'], $user->password)) {
                return back()->withErrors(['current_password' => 'Password saat ini tidak sesuai.'])->withInput();
            }
            $user->password = Hash::make($validated['password']);
        }

        $user->name = $validated['name'];

        // Simpan avatar & pengaturan ke kolom preferences (JSON) jika ada
        $preferences = $user->preferences ?? [];
        if (isset($validated['avatar']))          $preferences['avatar']          = $validated['avatar'];
        if (isset($validated['sound_enabled']))   $preferences['sound_enabled']   = (bool) $validated['sound_enabled'];
        if (isset($validated['graphic_quality'])) $preferences['graphic_quality'] = $validated['graphic_quality'];

        // Simpan preferences jika kolom ada, skip jika belum ada (graceful)
        if (array_key_exists('preferences', $user->getAttributes()) || isset($user->preferences)) {
            $user->preferences = $preferences;
        }

        $user->save();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Profil berhasil diperbarui.',
                'user'    => [
                    'name'            => $user->name,
                    'avatar'          => $preferences['avatar'] ?? null,
                    'sound_enabled'   => $preferences['sound_enabled'] ?? true,
                    'graphic_quality' => $preferences['graphic_quality'] ?? 'medium',
                ],
            ]);
        }

        return back()->with('success', 'Profil berhasil diperbarui!');
    }
}
