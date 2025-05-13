<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{
    /**
     * Menampilkan daftar laporan.
     */
    public function index(Request $request)
    {
        try {
            // Query sederhana tanpa model untuk memeriksa jika ada data
            $dbReports = DB::table('reports')->get();
            
            if ($dbReports->isEmpty()) {
                // Jika benar-benar tidak ada data di database
                return response()->json([
                    'success' => true,
                    'message' => 'Tidak ada laporan yang ditemukan',
                    'reports' => [],
                    'count' => 0
                ]);
            }
            
            // Jika ada data, kita ambil dengan cara biasa
            if (auth()->user()->isAdmin() || auth()->user()->isRelawan()) {
                // Admin dan relawan dapat melihat semua laporan
                $reports = Report::with('user')->latest()->get();
            } else {
                // Pengguna biasa hanya bisa melihat laporan mereka sendiri
                $reports = Report::where('user_id', auth()->id())
                    ->with('user')
                    ->latest()
                    ->get();
            }
            
            // Transform data untuk frontend
            $transformedReports = [];
            foreach ($reports as $report) {
                $transformedReports[] = [
                    'id' => $report->id,
                    'user' => [
                        'id' => $report->user->id,
                        'name' => $report->user->name,
                        'email' => $report->user->email,
                        'role' => $report->user->role,
                    ],
                    'photo_url' => $report->photo_url,
                    'location' => $report->location,
                    'problem_type' => $report->problem_type,
                    'description' => $report->description,
                    'status' => $report->status,
                    'admin_notes' => $report->admin_notes,
                    'created_at' => $report->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $report->updated_at->format('Y-m-d H:i:s'),
                ];
            }
            
            return response()->json([
                'success' => true,
                'count' => count($transformedReports),
                'reports' => $transformedReports
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error saat mengambil laporan', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data laporan: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menyimpan laporan baru.
     */
    public function store(Request $request)
    {
        try {
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
                'success' => true,
                'message' => 'Laporan berhasil dibuat',
                'report' => [
                    'id' => $report->id,
                    'user' => [
                        'id' => $report->user->id,
                        'name' => $report->user->name,
                        'email' => $report->user->email,
                        'role' => $report->user->role,
                    ],
                    'photo_url' => $report->photo_url,
                    'location' => $report->location,
                    'problem_type' => $report->problem_type,
                    'description' => $report->description,
                    'status' => $report->status,
                    'admin_notes' => $report->admin_notes,
                    'created_at' => $report->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $report->updated_at->format('Y-m-d H:i:s'),
                ]
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error saat membuat laporan', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat membuat laporan: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menampilkan laporan tertentu.
     */
    public function show($reportId)
    {
        try {
            $report = Report::with('user')->findOrFail($reportId);
            
            // Periksa otorisasi
            if (!auth()->user()->isAdmin() && !auth()->user()->isRelawan() && auth()->id() !== $report->user_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak diizinkan melihat laporan ini'
                ], 403);
            }
            
            return response()->json([
                'success' => true,
                'report' => [
                    'id' => $report->id,
                    'user' => [
                        'id' => $report->user->id,
                        'name' => $report->user->name,
                        'email' => $report->user->email,
                        'role' => $report->user->role,
                    ],
                    'photo_url' => $report->photo_url,
                    'location' => $report->location,
                    'problem_type' => $report->problem_type,
                    'description' => $report->description,
                    'status' => $report->status,
                    'admin_notes' => $report->admin_notes,
                    'created_at' => $report->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $report->updated_at->format('Y-m-d H:i:s'),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error saat melihat laporan', [
                'report_id' => $reportId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat melihat laporan: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 404 : 500);
        }
    }

    /**
     * Memperbarui laporan tertentu.
     */
    public function update(Request $request, $reportId)
    {
        try {
            $report = Report::findOrFail($reportId);
            
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
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak diizinkan mengubah laporan ini'
                ], 403);
            }

            // Load the user relation
            $report->load('user');

            return response()->json([
                'success' => true,
                'message' => 'Laporan berhasil diperbarui',
                'report' => [
                    'id' => $report->id,
                    'user' => [
                        'id' => $report->user->id,
                        'name' => $report->user->name,
                        'email' => $report->user->email,
                        'role' => $report->user->role,
                    ],
                    'photo_url' => $report->photo_url,
                    'location' => $report->location,
                    'problem_type' => $report->problem_type,
                    'description' => $report->description,
                    'status' => $report->status,
                    'admin_notes' => $report->admin_notes,
                    'created_at' => $report->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $report->updated_at->format('Y-m-d H:i:s'),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error saat memperbarui laporan', [
                'report_id' => $reportId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui laporan: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 404 : 500);
        }
    }

    /**
     * Menghapus laporan tertentu.
     */
    public function destroy($reportId)
    {
        try {
            $report = Report::findOrFail($reportId);
            
            // Periksa otorisasi
            if (!auth()->user()->isAdmin() && !(auth()->id() === $report->user_id && $report->status === 'pending')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak diizinkan menghapus laporan ini'
                ], 403);
            }

            // Hapus foto terkait
            if ($report->photo_path) {
                Storage::disk('public')->delete($report->photo_path);
            }
            
            $report->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Laporan berhasil dihapus',
                'report_id' => $reportId
            ]);
        } catch (\Exception $e) {
            Log::error('Error saat menghapus laporan', [
                'report_id' => $reportId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus laporan: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 404 : 500);
        }
    }
    
    /**
     * Mendapatkan daftar tipe masalah (untuk dropdown)
     */
    public function getProblemTypes()
    {
        try {
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
            
            return response()->json([
                'success' => true,
                'problem_types' => $problemTypes
            ]);
        } catch (\Exception $e) {
            Log::error('Error saat mengambil tipe masalah', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil daftar tipe masalah',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}