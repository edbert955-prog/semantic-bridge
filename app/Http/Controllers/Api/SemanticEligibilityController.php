<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SemanticEligibilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

class SemanticEligibilityController extends Controller
{
    public function __construct(private readonly SemanticEligibilityService $eligibility) {}

    public function check(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pengajuan_id' => ['nullable', 'integer', 'min:1', 'required_without_all:npm,service_code'],
            'npm' => ['nullable', 'string', 'max:20', 'required_without:pengajuan_id'],
            'service_code' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/', 'required_without:pengajuan_id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter permintaan tidak valid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $context = [];

        if (!empty($data['pengajuan_id'])) {
            try {
                $application = DB::connection('system_a')
                    ->table('pengajuan as p')
                    ->join('users as u', 'u.id', '=', 'p.user_id')
                    ->join('jenis_layanan as jl', 'jl.id', '=', 'p.jenis_layanan_id')
                    ->where('p.id', $data['pengajuan_id'])
                    ->select([
                        'p.id as pengajuan_id', 'u.nomor_induk', 'u.role',
                        'jl.kode as service_code', 'jl.is_active as service_active',
                    ])->first();

                if (!$application) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Pengajuan tidak ditemukan di Sistem A.',
                    ], 404);
                }

                if ($application->role !== 'mahasiswa') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Pengajuan ini bukan berasal dari akun mahasiswa.',
                    ], 422);
                }

                if (!$application->service_active) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Jenis layanan pada pengajuan tidak aktif.',
                    ], 422);
                }

                $npm = trim((string) $application->nomor_induk);
                $serviceCode = (string) $application->service_code;
                $context['pengajuan_id'] = (int) $application->pengajuan_id;
            } catch (Throwable $e) {
                report($e);
                return response()->json([
                    'success' => false,
                    'message' => 'Data pengajuan Sistem A tidak dapat dibaca.',
                ], 503);
            }
        } else {
            $npm = trim((string) $data['npm']);
            $serviceCode = (string) $data['service_code'];
        }

        $result = $this->eligibility->evaluate($npm, $serviceCode, $context);

        return response()->json([
            'success' => true,
            'message' => 'Evaluasi selesai. Periksa result_status, reasons, dan evidence.',
            'data' => $result,
        ]);
    }
}
