<?php

namespace App\Services;

use App\Models\SemanticInferenceLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Evaluates configured service rules with an explicit open-world policy.
 * A missing identity/service/source record is never treated as an academic failure.
 */
class SemanticEligibilityService
{
    public function __construct(
        private readonly SemanticRdfService $rdfService,
    ) {}

    public function evaluate(string $npm, string $serviceCode, array $context = []): array
    {
        $uuid = (string) Str::uuid();
        $rules = config("semantic.services.{$serviceCode}");
        $blocking = [];
        $failures = [];
        $unknowns = [];
        $trace = [];
        $evidence = [];
        $systemAUser = null;
        $service = null;
        $student = null;

        $this->trace($trace, 'request_received', 'pass', 'Permintaan evaluasi diterima oleh Semantic Bridge.');

        if (!is_array($rules)) {
            $issue = $this->issue('rule_not_configured', "Aturan layanan {$serviceCode} belum dikonfigurasi.");
            $blocking[] = $issue;
            $this->trace($trace, 'service_rules', 'unknown', $issue['message']);
        } else {
            $this->trace($trace, 'service_rules', 'pass', 'Aturan layanan ditemukan di konfigurasi Semantic Bridge.');
        }

        // 1. The service must exist and be active in System A.
        try {
            if (!Schema::connection('system_a')->hasTable('jenis_layanan')) {
                throw new \RuntimeException('Tabel jenis_layanan tidak tersedia.');
            }

            $service = DB::connection('system_a')->table('jenis_layanan')
                ->where('kode', $serviceCode)
                ->first(['id', 'kode', 'nama', 'is_active']);

            if (!$service || !$service->is_active) {
                $issue = $this->issue(
                    'service_not_registered',
                    "Layanan {$serviceCode} tidak ditemukan atau tidak aktif pada Sistem A."
                );
                $blocking[] = $issue;
                $this->trace($trace, 'service_catalog', 'unknown', $issue['message']);
            } else {
                $evidence['service'] = [
                    'id' => (int) $service->id,
                    'code' => (string) $service->kode,
                    'name' => (string) $service->nama,
                    'active' => (bool) $service->is_active,
                ];
                $this->trace($trace, 'service_catalog', 'pass', 'Layanan ditemukan dan berstatus aktif pada Sistem A.');
            }
        } catch (Throwable $e) {
            report($e);
            $issue = $this->issue('system_a_service_unavailable', 'Katalog layanan Sistem A tidak dapat diperiksa.');
            $blocking[] = $issue;
            $this->trace($trace, 'service_catalog', 'unknown', $issue['message']);
        }

        // 2. Resolve the applicant through the business identity, not database IDs.
        try {
            if (!Schema::connection('system_a')->hasTable('users')) {
                throw new \RuntimeException('Tabel users tidak tersedia.');
            }

            $systemAUser = DB::connection('system_a')->table('users')
                ->where('nomor_induk', $npm)
                ->where('role', 'mahasiswa')
                ->first(['id', 'name', 'nomor_induk', 'role']);

            if (!$systemAUser) {
                $issue = $this->issue(
                    'applicant_account_not_found',
                    'Akun pemohon dengan role mahasiswa dan nomor induk tersebut tidak ditemukan pada Sistem A.'
                );
                $blocking[] = $issue;
                $this->trace($trace, 'applicant_identity', 'unknown', $issue['message']);
            } else {
                $evidence['applicant_account'] = [
                    'account_id' => (int) $systemAUser->id,
                    'name' => (string) $systemAUser->name,
                    'nomor_induk' => (string) $systemAUser->nomor_induk,
                    'role' => (string) $systemAUser->role,
                ];
                $this->trace($trace, 'applicant_identity', 'pass', 'Akun mahasiswa ditemukan; NPM cocok dengan nomor_induk.');
            }
        } catch (Throwable $e) {
            report($e);
            $issue = $this->issue('system_a_identity_unavailable', 'Identitas pemohon pada Sistem A tidak dapat diperiksa.');
            $blocking[] = $issue;
            $this->trace($trace, 'applicant_identity', 'unknown', $issue['message']);
        }

        // 3. Read the academic source record from System B.
        try {
            if (!Schema::connection('system_b')->hasTable('mahasiswa')) {
                throw new \RuntimeException('Tabel mahasiswa tidak tersedia.');
            }

            $student = DB::connection('system_b')->table('mahasiswa')
                ->where('npm', $npm)
                ->first();

            if (!$student) {
                $issue = $this->issue('student_not_found', 'NPM tidak ditemukan di tabel mahasiswa Sistem B.');
                $blocking[] = $issue;
                $this->trace($trace, 'student_record', 'unknown', $issue['message']);
            } else {
                $sks = $this->numericValue($student, 'total_sks_lulus');
                $ipk = $this->numericValue($student, 'ipk');
                $evidence['academic_record'] = [
                    'npm' => $npm,
                    'name' => $this->value($student, 'nama'),
                    'ipk' => $ipk,
                    'total_sks_lulus' => $sks === null ? null : (int) $sks,
                    'semester_berjalan' => $this->numericValue($student, 'semester_berjalan'),
                ];
                $this->trace($trace, 'student_record', 'pass', 'Rekaman mahasiswa ditemukan pada Sistem B melalui NPM.');

                if (is_array($rules) && array_key_exists('min_sks_lulus', $rules)) {
                    $minimumSks = (int) $rules['min_sks_lulus'];
                    if ($sks === null) {
                        $issue = $this->issue('total_sks_unavailable', 'Kolom total_sks_lulus tidak tersedia atau nilainya kosong.');
                        $unknowns[] = $issue;
                        $this->trace($trace, 'minimum_sks', 'unknown', $issue['message']);
                    } elseif ($sks < $minimumSks) {
                        $issue = $this->issue('insufficient_sks', "Total SKS lulus {$this->displayNumber($sks)}, sedangkan persyaratan minimal {$minimumSks} SKS.");
                        $failures[] = $issue;
                        $this->trace($trace, 'minimum_sks', 'fail', $issue['message']);
                    } else {
                        $this->trace($trace, 'minimum_sks', 'pass', "Total SKS lulus {$this->displayNumber($sks)} memenuhi batas minimal {$minimumSks} SKS.");
                    }
                }

                if (is_array($rules) && array_key_exists('min_ipk', $rules)) {
                    $minimumIpk = (float) $rules['min_ipk'];
                    if ($ipk === null) {
                        $issue = $this->issue('ipk_unavailable', 'IPK tidak tersedia atau nilainya kosong.');
                        $unknowns[] = $issue;
                        $this->trace($trace, 'minimum_ipk', 'unknown', $issue['message']);
                    } elseif ($ipk < $minimumIpk) {
                        $issue = $this->issue('insufficient_ipk', "IPK {$this->displayNumber($ipk)} berada di bawah batas minimal {$this->displayNumber($minimumIpk)}.");
                        $failures[] = $issue;
                        $this->trace($trace, 'minimum_ipk', 'fail', $issue['message']);
                    } else {
                        $this->trace($trace, 'minimum_ipk', 'pass', "IPK {$this->displayNumber($ipk)} memenuhi batas minimal {$this->displayNumber($minimumIpk)}.");
                    }
                }
            }
        } catch (Throwable $e) {
            report($e);
            $issue = $this->issue('system_b_unavailable', 'Data mahasiswa Sistem B tidak dapat diperiksa.');
            $blocking[] = $issue;
            $this->trace($trace, 'student_record', 'unknown', $issue['message']);
        }

        // 4. Required final grade. Missing grade is unknown, never equivalent to A.
        if (is_array($rules) && ($rules['requires_skripsi_grade_a'] ?? false) === true) {
            $grade = $this->findSkripsiGrade($npm);
            $evidence['skripsi_grade'] = $grade;

            if ($grade === null) {
                $issue = $this->issue('skripsi_grade_unavailable', 'Nilai Skripsi/Tugas Akhir tidak ditemukan atau belum dapat dibuktikan dari data Sistem B.');
                $unknowns[] = $issue;
                $this->trace($trace, 'skripsi_grade', 'unknown', $issue['message']);
            } elseif (strtoupper(trim($grade)) !== 'A') {
                $issue = $this->issue('skripsi_grade_not_a', "Nilai Skripsi/Tugas Akhir tercatat {$grade}; aturan prototipe mensyaratkan A.");
                $failures[] = $issue;
                $this->trace($trace, 'skripsi_grade', 'fail', $issue['message']);
            } else {
                $this->trace($trace, 'skripsi_grade', 'pass', 'Nilai Skripsi/Tugas Akhir yang ditemukan adalah A.');
            }
        }

        // 5. The current source schemas do not contain an authoritative plagiarism-clearance field.
        if (is_array($rules) && ($rules['requires_plagiarism_clearance'] ?? false) === true) {
            $evidence['plagiarism_clearance'] = null;
            $issue = $this->issue(
                'plagiarism_evidence_unavailable',
                'Bukti bebas plagiarisme belum memiliki sumber/status verifikasi yang dapat dipercaya pada sistem sumber.'
            );
            $unknowns[] = $issue;
            $this->trace($trace, 'plagiarism_clearance', 'unknown', $issue['message']);
        }

        // Gate order matters: unresolved identity/catalog/source means manual review,
        // even if an academic value also fails. Never reject an unverified request.
        if (count($blocking) > 0) {
            $status = 'needs_manual_review';
            $isEligible = null;
            $decisionMessage = 'Evaluasi bisnis ditahan karena identitas, katalog layanan, atau sumber data belum tervalidasi.';
        } elseif (count($failures) > 0) {
            $status = 'ineligible';
            $isEligible = false;
            $decisionMessage = 'Sedikitnya satu persyaratan wajib terbukti tidak terpenuhi. Bukti tambahan yang belum tersedia tetap dicatat terpisah.';
        } elseif (count($unknowns) > 0) {
            $status = 'needs_manual_review';
            $isEligible = null;
            $decisionMessage = 'Belum ada persyaratan yang terbukti gagal, tetapi bukti wajib belum lengkap.';
        } else {
            $status = 'eligible';
            $isEligible = true;
            $decisionMessage = 'Semua sumber dan persyaratan wajib yang dikonfigurasi berhasil diverifikasi.';
        }

        $this->trace($trace, 'final_decision', $status === 'eligible' ? 'pass' : ($status === 'ineligible' ? 'fail' : 'unknown'), $decisionMessage);

        $reasons = array_values(array_merge($blocking, $failures, $unknowns));
        $result = [
            'request_uuid' => $uuid,
            'pengajuan_id' => $context['pengajuan_id'] ?? null,
            'service_code' => $serviceCode,
            'applicant_npm' => $npm,
            'result_status' => $status,
            'is_eligible' => $isEligible,
            'reasons' => $reasons,
            'evidence' => $evidence,
            'reasoning_trace' => $trace,
            'ontology_version' => config('semantic.ontology.version', '2.0.0'),
            'evaluated_at' => now()->toIso8601String(),
        ];

        // Persist the business result first. RDF publication is best-effort and
        // cannot overwrite or turn a failed business evaluation into a success.
        $log = SemanticInferenceLog::create([
            'request_uuid' => $uuid,
            'service_code' => $serviceCode,
            'applicant_npm' => $npm,
            'result_status' => $status,
            'is_eligible' => $isEligible,
            'reasons' => $reasons,
            'evidence' => $evidence,
            'source_snapshot' => [
                'system_a_user_found' => $systemAUser !== null,
                'system_a_service_found' => $service !== null,
                'system_b_student_found' => $student !== null,
                'pengajuan_id' => $context['pengajuan_id'] ?? null,
                'identity_key' => 'npm',
            ],
            'reasoning_trace' => $trace,
            'ontology_version' => $result['ontology_version'],
            'evaluated_at' => now(),
            'rdf_status' => 'pending',
        ]);

        try {
            $rdfMetadata = $this->rdfService->persistEvaluation($result);
            $log->forceFill([
                'rdf_graph_uri' => $rdfMetadata['graph_uri'] ?? null,
                'rdf_file_path' => $rdfMetadata['relative_path'] ?? null,
                'rdf_triple_count' => $rdfMetadata['triple_count'] ?? null,
                'rdf_status' => $rdfMetadata['status'] ?? 'generation_failed',
                'sparql_verified' => $rdfMetadata['sparql_verified'] ?? null,
                'reasoner_status' => $rdfMetadata['reasoner_status'] ?? 'not_configured',
                'reasoner_triple_count' => $rdfMetadata['reasoner_triple_count'] ?? null,
            ])->save();
            $result['rdf'] = $rdfMetadata;
        } catch (Throwable $e) {
            report($e);
            $log->forceFill(['rdf_status' => 'generation_failed', 'sparql_verified' => false])->save();
            $result['rdf'] = [
                'status' => 'generation_failed',
                'message' => 'Hasil evaluasi tersimpan, tetapi pembuatan RDF gagal. Periksa Laravel log.',
            ];
        }

        return $result;
    }

    private function trace(array &$trace, string $step, string $status, string $message): void
    {
        $trace[] = compact('step', 'status', 'message');
    }

    private function issue(string $code, string $message): array
    {
        return compact('code', 'message');
    }

    private function value(object $record, string $column): mixed
    {
        return property_exists($record, $column) ? $record->{$column} : null;
    }

    private function numericValue(object $record, string $column): ?float
    {
        $value = $this->value($record, $column);
        return $value !== null && is_numeric($value) ? (float) $value : null;
    }

    private function displayNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    private function findSkripsiGrade(string $npm): ?string
    {
        try {
            $required = [
                'krs' => ['id_krs', 'npm', 'kode_semester'],
                'krs_detail' => ['id_krs', 'id_kelas', 'nilai_huruf'],
                'kelas_kuliah' => ['id_kelas', 'id_mk'],
                'mata_kuliah' => ['id_mk', 'nama_mk'],
            ];
            foreach ($required as $table => $columns) {
                if (!Schema::connection('system_b')->hasTable($table)) {
                    return null;
                }
                foreach ($columns as $column) {
                    if (!Schema::connection('system_b')->hasColumn($table, $column)) {
                        return null;
                    }
                }
            }

            $grade = DB::connection('system_b')->table('krs_detail as kd')
                ->join('krs as k', 'k.id_krs', '=', 'kd.id_krs')
                ->join('kelas_kuliah as kk', 'kk.id_kelas', '=', 'kd.id_kelas')
                ->join('mata_kuliah as mk', 'mk.id_mk', '=', 'kk.id_mk')
                ->where('k.npm', $npm)
                ->whereNotNull('kd.nilai_huruf')
                ->where(function ($query) {
                    $query->whereRaw('LOWER(mk.nama_mk) LIKE ?', ['%skripsi%'])
                        ->orWhereRaw('LOWER(mk.nama_mk) LIKE ?', ['%tugas akhir%']);
                })
                ->orderByDesc('k.kode_semester')
                ->value('kd.nilai_huruf');

            return $grade !== null ? (string) $grade : null;
        } catch (Throwable $e) {
            report($e);
            return null;
        }
    }
}
