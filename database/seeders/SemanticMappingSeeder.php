<?php

namespace Database\Seeders;

use App\Models\SemanticMapping;
use Illuminate\Database\Seeder;

class SemanticMappingSeeder extends Seeder
{
    public function run(): void
    {
        $base = 'https://unila.ac.id/ontology/';

        $mappings = [
            [
                'source_system' => 'system_a', 'source_table' => 'users',
                'source_column' => 'nomor_induk', 'target_system' => 'system_b',
                'target_entity' => 'Mahasiswa', 'target_property' => 'npm',
                'predicate_uri' => $base.'alignment#studentIdentifier',
                'mapping_type' => 'conditional-property-mapping',
                'condition_expression' => "Gunakan hanya jika users.role = 'mahasiswa'.",
                'description' => 'Nomor induk pada akun mahasiswa Sistem A dipadankan dengan NPM Sistem B.',
            ],
            [
                'source_system' => 'system_a', 'source_table' => 'users',
                'source_column' => 'name', 'target_system' => 'system_b',
                'target_entity' => 'Mahasiswa', 'target_property' => 'nama',
                'predicate_uri' => $base.'alignment#studentName',
                'mapping_type' => 'conditional-property-mapping',
                'condition_expression' => "Gunakan hanya jika role = 'mahasiswa' dan NPM cocok.",
                'description' => 'Nama akun diselaraskan setelah identitas NPM dicocokkan.',
            ],
            [
                'source_system' => 'system_a', 'source_table' => 'users',
                'source_column' => 'nomor_induk', 'target_system' => 'system_b',
                'target_entity' => 'Dosen', 'target_property' => 'nip',
                'predicate_uri' => $base.'alignment#lecturerIdentifier',
                'mapping_type' => 'conditional-property-mapping',
                'condition_expression' => "Gunakan hanya jika users.role = 'dosen_pa'; cocokkan dengan NIP, bukan ID database.",
                'description' => 'Identitas Dosen PA antar sistem dicocokkan melalui NIP.',
            ],
            [
                'source_system' => 'system_a', 'source_table' => 'jenis_layanan',
                'source_column' => 'kode', 'target_system' => 'ontology',
                'target_entity' => 'ServiceType', 'target_property' => 'serviceCode',
                'predicate_uri' => $base.'workflow#serviceCode',
                'mapping_type' => 'vocabulary-mapping',
                'condition_expression' => null,
                'description' => 'Kode layanan dipetakan ke identitas konseptual jenis layanan.',
            ],
            [
                'source_system' => 'system_a', 'source_table' => 'jenis_layanan',
                'source_column' => 'nama', 'target_system' => 'ontology',
                'target_entity' => 'ServiceType', 'target_property' => 'label',
                'predicate_uri' => $base.'workflow#serviceLabel',
                'mapping_type' => 'vocabulary-mapping',
                'condition_expression' => null,
                'description' => 'Nama layanan dipetakan ke label konsep layanan.',
            ],
            [
                'source_system' => 'system_a', 'source_table' => 'status_pengajuan',
                'source_column' => 'kode', 'target_system' => 'ontology',
                'target_entity' => 'WorkflowStatus', 'target_property' => 'statusCode',
                'predicate_uri' => $base.'workflow#statusCode',
                'mapping_type' => 'vocabulary-mapping',
                'condition_expression' => null,
                'description' => 'Kode status pengajuan dipetakan ke vocabulary status workflow.',
            ],
            [
                'source_system' => 'system_a', 'source_table' => 'status_pengajuan',
                'source_column' => 'nama', 'target_system' => 'ontology',
                'target_entity' => 'WorkflowStatus', 'target_property' => 'label',
                'predicate_uri' => $base.'workflow#statusLabel',
                'mapping_type' => 'vocabulary-mapping',
                'condition_expression' => null,
                'description' => 'Nama status dipetakan ke label status workflow.',
            ],
            [
                'source_system' => 'system_a', 'source_table' => 'pengajuan',
                'source_column' => 'nomor_pengajuan', 'target_system' => 'ontology',
                'target_entity' => 'ServiceRequest', 'target_property' => 'requestNumber',
                'predicate_uri' => $base.'workflow#requestNumber',
                'mapping_type' => 'vocabulary-mapping',
                'condition_expression' => null,
                'description' => 'Nomor pengajuan menjadi identifier konseptual workflow.',
            ],
            [
                'source_system' => 'system_a', 'source_table' => 'pengajuan',
                'source_column' => 'tanggal_pengajuan', 'target_system' => 'ontology',
                'target_entity' => 'ServiceRequest', 'target_property' => 'submittedAt',
                'predicate_uri' => $base.'workflow#submittedAt',
                'mapping_type' => 'vocabulary-mapping',
                'condition_expression' => null,
                'description' => 'Waktu pengajuan dipetakan sebagai waktu pengiriman permohonan.',
            ],
        ];

        foreach ($mappings as $mapping) {
            SemanticMapping::updateOrCreate([
                'source_system' => $mapping['source_system'],
                'source_table' => $mapping['source_table'],
                'source_column' => $mapping['source_column'],
                'target_system' => $mapping['target_system'],
                'target_entity' => $mapping['target_entity'],
                'target_property' => $mapping['target_property'],
            ], $mapping + ['is_active' => true]);
        }
    }
}
