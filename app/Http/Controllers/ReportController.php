<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReportController extends Controller
{
    /**
     * Menampilkan daftar laporan.
     */
    public function index(Request $request)
    {
        // Logika berbeda berdasarkan peran pengguna
        if (auth()->user()->isAdmin() || auth()->user()->isRelawan()) {
            // Admin dan relawan dapat melihat semua laporan, dengan filter opsional
            $query = Report::with('user')->latest();
            
            // Terapkan filter jika ada
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            
            if ($request->has('problem_type')) {
                $query->where('problem_type', $request->problem_type);
            }
            
            $reports = $query->paginate(15);
        } else {
            // Pengguna biasa hanya bisa melihat laporan mereka sendiri
            $reports = Report::where('user_id', auth()->id())
                ->with('user') // Pastikan user diload
                ->latest()
                ->paginate(15);
        }
        
        return response()->json($reports);
    }

    /**
     * Menyimpan laporan baru.
     */
    public function store(Request $request)
    {
        $request->validate([
            'photo' => 'required|image|max:5120', // max 5MB
            'location' => 'required|string|max:255',
            'problem_type' => 'required|string|max:100',
            'description' => 'required|string|max:1000',
        ]);

        // Tangani upload foto
        $photoPath = $request->file('photo')->store('reports', 'public');

        // Buat laporan
        $report = Report::create([
            'user_id' => auth()->id(),
            'photo_path' => $photoPath,
            'location' => $request->location,
            'problem_type' => $request->problem_type,
            'description' => $request->description,
        ]);

        // Load the user relation
        $report->load('user');

        return response()->json([
            'message' => 'Laporan berhasil dibuat',
            'report' => $report
        ], 201);
    }

    /**
     * Menampilkan laporan tertentu.
     */
    public function show(Report $report)
    {
        // Periksa otorisasi
        if (!auth()->user()->isAdmin() && !auth()->user()->isRelawan() && auth()->id() !== $report->user_id) {
            return response()->json(['message' => 'Tidak diizinkan'], 403);
        }

        // Muat relasi pengguna
        $report->load('user');
        
        return response()->json($report);
    }

    /**
     * Memperbarui laporan tertentu.
     * Pengguna dapat memperbarui laporan mereka sendiri yang masih pending
     * Admin/relawan dapat memperbarui status dan menambahkan catatan
     */
    public function update(Request $request, Report $report)
    {
        // Periksa otorisasi
        if (auth()->user()->isAdmin() || auth()->user()->isRelawan()) {
            // Admin dan relawan dapat memperbarui status dan catatan
            $validatedData = $request->validate([
                'status' => 'sometimes|required|in:pending,in_progress,resolved,rejected',
                'admin_notes' => 'sometimes|nullable|string|max:1000',
            ]);
            
            $report->update($validatedData);
            
        } elseif (auth()->id() === $report->user_id && $report->status === 'pending') {
            // Pengguna hanya dapat memperbarui laporan mereka sendiri yang masih pending
            $validatedData = $request->validate([
                'location' => 'sometimes|required|string|max:255',
                'problem_type' => 'sometimes|required|string|max:100',
                'description' => 'sometimes|required|string|max:1000',
            ]);
            
            // Periksa apakah foto baru diunggah
            if ($request->hasFile('photo')) {
                $request->validate([
                    'photo' => 'image|max:5120', // max 5MB
                ]);
                
                // Hapus foto lama jika ada
                if ($report->photo_path) {
                    Storage::disk('public')->delete($report->photo_path);
                }
                
                $photoPath = $request->file('photo')->store('reports', 'public');
                $validatedData['photo_path'] = $photoPath;
            }
            
            $report->update($validatedData);
            
        } else {
            return response()->json(['message' => 'Tidak diizinkan'], 403);
        }

        // Load the user relation
        $report->load('user');

        return response()->json([
            'message' => 'Laporan berhasil diperbarui',
            'report' => $report
        ]);
    }

    /**
     * Menghapus laporan tertentu.
     * Hanya admin dan pemilik laporan (jika laporan masih pending) yang dapat menghapus
     */
    public function destroy(Report $report)
    {
        // Periksa otorisasi
        if (!auth()->user()->isAdmin() && !(auth()->id() === $report->user_id && $report->status === 'pending')) {
            return response()->json(['message' => 'Tidak diizinkan'], 403);
        }

        // Hapus foto terkait
        if ($report->photo_path) {
            Storage::disk('public')->delete($report->photo_path);
        }
        
        $report->delete();
        
        return response()->json(['message' => 'Laporan berhasil dihapus']);
    }
    
    /**
     * Mendapatkan daftar tipe masalah (untuk dropdown)
     */
    public function getProblemTypes()
    {
        // Daftar tipe masalah yang telah ditentukan
        $problemTypes = [
            'infrastructure' => 'Infrastruktur',
            'electricity' => 'Listrik',
            'water_supply' => 'Sumber Air',
            'waste_management' => 'Pengelolaan Sampah',
            'public_safety' => 'Keamanan Publik',
            'public_health' => 'Kesehatan Publik',
            'environmental' => 'Lingkungan',
            'other' => 'Lainnya'
        ];
        
        return response()->json($problemTypes);
    }
}