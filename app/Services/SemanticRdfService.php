<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** Creates one auditable Turtle graph for every semantic evaluation. */
class SemanticRdfService
{
    public function __construct(
        private readonly FusekiClient $fuseki,
        private readonly SemanticReasonerClient $reasoner,
    ) {}

    public function persistEvaluation(array $result): array
    {
        $uuid = (string) $result['request_uuid'];
        $graphBase = rtrim((string) config('semantic.rdf.graph_base_uri'), '/').'/';
        $graphUri = $graphBase.'evaluation/'.rawurlencode($uuid);
        $triples = $this->buildTriples($result, $graphUri);
        $turtle = $this->serialize($triples);

        $directory = storage_path('app/semantic-graphs');
        File::ensureDirectoryExists($directory);
        $sourcePath = $directory.DIRECTORY_SEPARATOR.$uuid.'.source.ttl';
        $absolutePath = $directory.DIRECTORY_SEPARATOR.$uuid.'.ttl';
        File::put($sourcePath, $turtle);

        // The formal OWL 2 RL reasoner is optional and invoked through a local Java JAR.
        // If it is disabled/unavailable, retain a valid base Turtle graph and report that state.
        $reasonerStatus = 'not_configured';
        $reasonerTripleCount = null;
        $reasonerMessage = 'Reasoner OWL 2 RL belum diaktifkan; graph RDF dasar digunakan.';
        try {
            $reasoned = $this->reasoner->reason($sourcePath, $absolutePath);
            $reasonerStatus = $reasoned['status'] ?? 'not_configured';
            $reasonerTripleCount = $reasoned['triple_count'] ?? null;
            $reasonerMessage = $reasoned['message'] ?? $reasonerMessage;
            if ($reasonerStatus === 'not_configured') {
                File::copy($sourcePath, $absolutePath);
                $reasonerTripleCount = null;
            }
        } catch (Throwable $e) {
            report($e);
            File::copy($sourcePath, $absolutePath);
            $reasonerStatus = 'reasoner_failed';
            $reasonerMessage = 'Reasoner gagal; graph RDF dasar tetap disimpan. Periksa setup Java/JAR dan Laravel log.';
        }

        $graphTurtle = File::get($absolutePath);
        $tripleCount = $reasonerTripleCount ?? count($triples);
        $status = 'local_only';
        $sparqlVerified = null;
        $message = 'RDF berhasil dibuat dan disimpan sebagai file Turtle lokal. '.$reasonerMessage;
        $fusekiResult = null;

        if ($this->fuseki->isConfigured()) {
            try {
                $fusekiResult = $this->fuseki->publishTurtle($graphUri, $graphTurtle);
                $status = 'published_verified';
                $sparqlVerified = true;
                $message = 'Graph RDF berhasil dipublikasikan dan diverifikasi melalui query SPARQL. '.$reasonerMessage;
            } catch (Throwable $e) {
                report($e);
                $status = 'publish_failed';
                $sparqlVerified = false;
                $message = 'File RDF lokal tersimpan, tetapi publikasi/verifikasi Fuseki gagal. Periksa koneksi Fuseki dan log Laravel. '.$reasonerMessage;
            }
        }

        return [
            'graph_uri' => $graphUri,
            'relative_path' => 'app/semantic-graphs/'.$uuid.'.ttl',
            'source_relative_path' => 'app/semantic-graphs/'.$uuid.'.source.ttl',
            'download_url' => url('/rdf-graphs/'.$uuid),
            'triple_count' => $tripleCount,
            'reasoner_status' => $reasonerStatus,
            'reasoner_triple_count' => $reasonerTripleCount,
            'reasoner_message' => $reasonerMessage,
            'status' => $status,
            'sparql_verified' => $sparqlVerified,
            'message' => $message,
            'fuseki' => $fusekiResult,
        ];
    }

    private function buildTriples(array $result, string $graphUri): array
    {
        $w = rtrim((string) config('semantic.ontology.workflow_uri'), '#').'#';
        $s = rtrim((string) config('semantic.ontology.siakadu_uri'), '#').'#';
        $a = rtrim((string) config('semantic.ontology.alignment_uri'), '#').'#';
        $resource = rtrim((string) config('semantic.rdf.resource_base_uri'), '/').'/';
        $uuid = (string) $result['request_uuid'];
        $npm = (string) ($result['applicant_npm'] ?? 'unknown');
        $evaluationUri = $resource.'evaluation/'.rawurlencode($uuid);
        $studentUri = $resource.'mahasiswa/'.rawurlencode($npm);
        $triples = [];

        $triples[] = '<'.$evaluationUri.'> rdf:type alignment:ServiceEligibilityResult .';
        $triples[] = '<'.$evaluationUri.'> alignment:graphUri '.$this->literal($graphUri).' .';
        $triples[] = '<'.$evaluationUri.'> alignment:requestUuid '.$this->literal($uuid).' .';
        $triples[] = '<'.$evaluationUri.'> alignment:applicantNpm '.$this->literal($npm).' .';
        $triples[] = '<'.$evaluationUri.'> alignment:serviceCode '.$this->literal((string) $result['service_code']).' .';
        $triples[] = '<'.$evaluationUri.'> alignment:resultStatus '.$this->literal((string) $result['result_status']).' .';
        $triples[] = '<'.$evaluationUri.'> alignment:evaluatedAt '.$this->typedLiteral((string) $result['evaluated_at'], 'xsd:dateTime').' .';
        $triples[] = '<'.$evaluationUri.'> alignment:ontologyVersion '.$this->literal((string) $result['ontology_version']).' .';
        $triples[] = '<'.$evaluationUri.'> alignment:explanation '.$this->literal($this->finalMessage($result)).' .';

        if (is_bool($result['is_eligible'] ?? null)) {
            $triples[] = '<'.$evaluationUri.'> alignment:eligible '.$this->typedLiteral($result['is_eligible'] ? 'true' : 'false', 'xsd:boolean').' .';
        }

        $triples[] = '<'.$evaluationUri.'> alignment:hasTargetIdentity <'.$studentUri.'> .';
        $triples[] = '<'.$studentUri.'> rdf:type siakadu:Mahasiswa .';
        $triples[] = '<'.$studentUri.'> siakadu:npm '.$this->literal($npm).' .';

        $academic = $result['evidence']['academic_record'] ?? [];
        if (!empty($academic['name'])) {
            $triples[] = '<'.$studentUri.'> siakadu:nama '.$this->literal((string) $academic['name']).' .';
        }
        if (isset($academic['ipk']) && is_numeric($academic['ipk'])) {
            $triples[] = '<'.$studentUri.'> siakadu:ipk '.$this->typedLiteral(number_format((float) $academic['ipk'], 2, '.', ''), 'xsd:decimal').' .';
        }
        if (isset($academic['total_sks_lulus']) && is_numeric($academic['total_sks_lulus'])) {
            $triples[] = '<'.$studentUri.'> siakadu:totalSksLulus '.$this->typedLiteral((string) (int) $academic['total_sks_lulus'], 'xsd:integer').' .';
        }
        if (isset($academic['semester_berjalan']) && is_numeric($academic['semester_berjalan'])) {
            $triples[] = '<'.$studentUri.'> siakadu:semesterBerjalan '.$this->typedLiteral((string) (int) $academic['semester_berjalan'], 'xsd:integer').' .';
        }

        $account = $result['evidence']['applicant_account'] ?? null;
        if (is_array($account)) {
            $accountUri = $resource.'system-a/user/'.rawurlencode((string) $account['account_id']);
            $triples[] = '<'.$accountUri.'> rdf:type workflow:MahasiswaAccount .';
            $triples[] = '<'.$accountUri.'> workflow:role '.$this->literal('mahasiswa').' .';
            $triples[] = '<'.$accountUri.'> alignment:sourceAccountId '.$this->typedLiteral((string) $account['account_id'], 'xsd:integer').' .';
            $triples[] = '<'.$accountUri.'> workflow:nomorIndukMahasiswa '.$this->literal($npm).' .';
            $triples[] = '<'.$accountUri.'> workflow:userName '.$this->literal((string) $account['name']).' .';
            $triples[] = '<'.$accountUri.'> alignment:identifiesStudent <'.$studentUri.'> .';
            $triples[] = '<'.$evaluationUri.'> alignment:hasSourceIdentity <'.$accountUri.'> .';
        }

        $service = $result['evidence']['service'] ?? null;
        if (is_array($service)) {
            $serviceUri = $resource.'service/'.rawurlencode((string) $service['code']);
            $triples[] = '<'.$serviceUri.'> rdf:type workflow:ServiceType .';
            $triples[] = '<'.$serviceUri.'> workflow:serviceCode '.$this->literal((string) $service['code']).' .';
            $triples[] = '<'.$serviceUri.'> workflow:serviceLabel '.$this->literal((string) $service['name']).' .';
            $triples[] = '<'.$evaluationUri.'> alignment:hasService <'.$serviceUri.'> .';
        }

        $requestId = $result['pengajuan_id'] ?? null;
        if ($requestId !== null) {
            $requestUri = $resource.'system-a/pengajuan/'.rawurlencode((string) $requestId);
            $triples[] = '<'.$requestUri.'> rdf:type workflow:ServiceRequest .';
            $triples[] = '<'.$requestUri.'> workflow:requestId '.$this->typedLiteral((string) $requestId, 'xsd:integer').' .';
            $triples[] = '<'.$evaluationUri.'> alignment:hasRequest <'.$requestUri.'> .';
            $this->appendRequestFacts($triples, $requestUri, (int) $requestId, $w, $resource);
        }

        foreach (($result['reasoning_trace'] ?? []) as $index => $step) {
            $criterionUri = $evaluationUri.'/step/'.($index + 1);
            $triples[] = '<'.$evaluationUri.'> alignment:hasCriterionResult <'.$criterionUri.'> .';
            $triples[] = '<'.$criterionUri.'> rdf:type alignment:CriterionResult .';
            $triples[] = '<'.$criterionUri.'> alignment:criterionCode '.$this->literal((string) ($step['step'] ?? 'unknown')).' .';
            $triples[] = '<'.$criterionUri.'> alignment:criterionStatus '.$this->literal((string) ($step['status'] ?? 'unknown')).' .';
            $triples[] = '<'.$criterionUri.'> alignment:criterionMessage '.$this->literal((string) ($step['message'] ?? '')).' .';
        }

        foreach (($result['reasons'] ?? []) as $index => $reason) {
            $reasonUri = $evaluationUri.'/reason/'.($index + 1);
            $triples[] = '<'.$evaluationUri.'> alignment:hasReason <'.$reasonUri.'> .';
            $triples[] = '<'.$reasonUri.'> rdf:type alignment:EvaluationReason .';
            $triples[] = '<'.$reasonUri.'> alignment:reasonCode '.$this->literal((string) ($reason['code'] ?? 'unknown')).' .';
            $triples[] = '<'.$reasonUri.'> alignment:reasonMessage '.$this->literal((string) ($reason['message'] ?? '')).' .';
        }

        return array_values(array_unique($triples));
    }

    private function appendRequestFacts(array &$triples, string $requestUri, int $requestId, string $workflowPrefix, string $resourceBase): void
    {
        try {
            if (!Schema::connection('system_a')->hasTable('pengajuan')) {
                return;
            }
            $row = DB::connection('system_a')->table('pengajuan as p')
                ->leftJoin('status_pengajuan as sp', 'sp.id', '=', 'p.status_pengajuan_id')
                ->where('p.id', $requestId)
                ->select(['p.nomor_pengajuan', 'p.keterangan', 'p.tanggal_pengajuan', 'sp.kode as status_kode', 'sp.nama as status_nama'])
                ->first();
            if (!$row) {
                return;
            }
            if ($row->nomor_pengajuan !== null) {
                $triples[] = '<'.$requestUri.'> workflow:requestNumber '.$this->literal((string) $row->nomor_pengajuan).' .';
            }
            if ($row->keterangan !== null) {
                $triples[] = '<'.$requestUri.'> workflow:description '.$this->literal((string) $row->keterangan).' .';
            }
            if ($row->tanggal_pengajuan !== null) {
                try {
                    $date = \Illuminate\Support\Carbon::parse($row->tanggal_pengajuan)->toIso8601String();
                    $triples[] = '<'.$requestUri.'> workflow:submittedAt '.$this->typedLiteral($date, 'xsd:dateTime').' .';
                } catch (Throwable) {
                    // Keep the request graph if a legacy timestamp cannot be parsed.
                }
            }
            if ($row->status_kode !== null) {
                $statusUri = $resourceBase.'workflow-status/'.rawurlencode((string) $row->status_kode);
                $triples[] = '<'.$requestUri.'> workflow:hasWorkflowStatus <'.$statusUri.'> .';
                $triples[] = '<'.$statusUri.'> rdf:type workflow:WorkflowStatus .';
                $triples[] = '<'.$statusUri.'> workflow:statusCode '.$this->literal((string) $row->status_kode).' .';
                $triples[] = '<'.$statusUri.'> workflow:statusLabel '.$this->literal((string) ($row->status_nama ?? $row->status_kode)).' .';
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function finalMessage(array $result): string
    {
        foreach (array_reverse($result['reasoning_trace'] ?? []) as $step) {
            if (($step['step'] ?? null) === 'final_decision') {
                return (string) ($step['message'] ?? '');
            }
        }
        return 'Evaluasi layanan akademik.';
    }

    private function serialize(array $triples): string
    {
        return "@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .\n"
            ."@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .\n"
            ."@prefix owl: <http://www.w3.org/2002/07/owl#> .\n"
            ."@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .\n"
            ."@prefix workflow: <https://unila.ac.id/ontology/workflow#> .\n"
            ."@prefix siakadu: <https://unila.ac.id/ontology/siakadu#> .\n"
            ."@prefix alignment: <https://unila.ac.id/ontology/alignment#> .\n\n"
            .implode("\n", $triples)."\n";
    }

    private function literal(string $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function typedLiteral(string $value, string $datatype): string
    {
        return $this->literal($value).'^^'.$datatype;
    }
}
