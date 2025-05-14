<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

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
                return response()->json([]);
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
                    'photo_path' => $report->photo_path,
                    'location' => $report->location,
                    'problem_type' => $report->problem_type,
                    'description' => $report->description,
                    'status' => $report->status,
                    'admin_notes' => $report->admin_notes,
                    'created_at' => $report->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $report->updated_at->format('Y-m-d H:i:s'),
                ];
            }
            
            // Langsung kembalikan array tanpa wrapper tambahan
            return response()->json($transformedReports);
            
        } catch (\Exception $e) {
            Log::error('Error saat mengambil laporan', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Terjadi kesalahan saat mengambil data laporan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menyimpan laporan baru.
     */
    public function store(Request $request)
    {
        try {
            // Validasi data yang masuk dengan problem_type yang opsional
            $validator = Validator::make($request->all(), [
                'photo_path' => 'required|string',
                'location' => 'required|string|max:255',
                'problem_type' => 'nullable|string|max:100',
                'description' => 'required|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Buat laporan dengan default kosong jika tidak ada problem_type
            $report = Report::create([
                'user_id' => auth()->id(),
                'photo_path' => $request->photo_path,
                'location' => $request->location,
                'problem_type' => $request->problem_type ?? '',
                'description' => $request->description,
                'status' => 'pending', // Default status
            ]);

            // Load the user relation
            $report->load('user');

            // Kembalikan format lama untuk kompatibilitas
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
                    'photo_path' => $report->photo_path,
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
     * Upload foto untuk laporan.
     * Method ini digunakan sebelum membuat laporan.
     */
    public function uploadPhoto(Request $request)
    {
        try {
            // Validasi file foto
            $validator = Validator::make($request->all(), [
                'photo' => 'required|image|max:5120', // max 5MB
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Upload foto
            $photoPath = $request->file('photo')->store('reports', 'public');

            // Kembalikan format lama untuk kompatibilitas
            return response()->json([
                'success' => true,
                'message' => 'Foto berhasil diupload',
                'photo_path' => $photoPath,
                'photo_url' => url('storage/' . $photoPath)
            ]);
        } catch (\Exception $e) {
            Log::error('Error saat mengupload foto', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengupload foto: ' . $e->getMessage(),
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
            
            // Kembalikan format lama untuk kompatibilitas
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
                    'photo_path' => $report->photo_path,
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
                $validator = Validator::make($request->all(), [
                    'status' => 'sometimes|required|in:pending,in_progress,resolved,rejected',
                    'admin_notes' => 'sometimes|nullable|string|max:1000',
                ]);
                
                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validasi gagal',
                        'errors' => $validator->errors()
                    ], 422);
                }
                
                $report->update($validator->validated());
                
            } elseif (auth()->id() === $report->user_id && $report->status === 'pending') {
                // Pengguna hanya dapat memperbarui laporan mereka sendiri yang masih pending
                $validator = Validator::make($request->all(), [
                    'location' => 'sometimes|required|string|max:255',
                    'problem_type' => 'nullable|string|max:100',
                    'description' => 'sometimes|required|string|max:1000',
                    'photo_path' => 'sometimes|required|string',
                ]);
                
                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validasi gagal',
                        'errors' => $validator->errors()
                    ], 422);
                }
                
                $validatedData = $validator->validated();
                
                // Pastikan problem_type tidak null
                if (isset($validatedData['problem_type']) && $validatedData['problem_type'] === null) {
                    $validatedData['problem_type'] = '';
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

            // Kembalikan format lama untuk kompatibilitas
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
                    'photo_path' => $report->photo_path,
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
            
            // Kembalikan format lama untuk kompatibilitas
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
            
            // Kembalikan format lama untuk kompatibilitas
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