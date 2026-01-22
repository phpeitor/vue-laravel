<?php

namespace App\Jobs;

use App\Models\CampaignUpload;
use App\Models\CampaignLog;
use App\Models\CampaignRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as SpreadsheetReaderException;

class ProcessCampaignUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uploadId;
    public int $timeout = 120; // segundos
    public int $tries = 3;

    public function __construct(int $uploadId)
    {
        $this->uploadId = $uploadId;
    }

    public function handle(): void
    {
        $upload = CampaignUpload::findOrFail($this->uploadId);
        $campaign = $upload->campaign;

        $chunkSize = 100;

        DB::beginTransaction();

        try {
            // 1️⃣ PROCESSING
            $campaign->update(['status' => 'PROCESSING']);

            CampaignLog::create([
                'campaign_id' => $campaign->id,
                'type' => 'PROCESSING',
                'message' => 'Inicio de procesamiento del Excel.',
            ]);

            // 2️⃣ Leer Excel
            $filePath = storage_path('app/public/' . $upload->file_path);

            try {
                $spreadsheet = IOFactory::load($filePath);
            } catch (SpreadsheetReaderException $e) {
                $this->failUpload($campaign, 'No se pudo leer el Excel', $e);
                DB::commit();
                return;
            }

            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);

            array_shift($rows); // quitar header

            $total = 0;
            $valid = 0;
            $invalid = 0;
            $batch = [];
            $now = now();

            foreach ($rows as $row) {
                if (empty(array_filter($row))) continue;

                $total++;
                $phone = trim((string) ($row['A'] ?? ''));

                if (!preg_match('/^[0-9]{9,15}$/', $phone)) {
                    $invalid++;
                    continue;
                }

                $variables = [];
                $index = 1;

                foreach ($row as $column => $value) {
                    if ($column === 'A') continue;
                    $variables[(string)$index] = $value !== null ? (string)$value : null;
                    $index++;
                }

                $batch[] = [
                    'campaign_id' => $campaign->id,
                    'campaign_upload_id' => $upload->id,
                    'phone' => $phone,
                    'variables' => json_encode($variables),
                    'status' => 'PENDING',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $valid++;

                if (count($batch) >= $chunkSize) {
                    CampaignRecipient::insert($batch);
                    $batch = [];
                }
            }

            if (!empty($batch)) {
                CampaignRecipient::insert($batch);
            }

            // 3️⃣ Métricas
            $upload->update([
                'total_rows' => $total,
                'valid_rows' => $valid,
                'invalid_rows' => $invalid,
            ]);

            // 4️⃣ FINISHED
            $campaign->update(['status' => 'FINISHED']);

            CampaignLog::create([
                'campaign_id' => $campaign->id,
                'type' => 'FINISHED',
                'message' => 'Procesamiento del Excel finalizado.',
                'meta' => compact('total', 'valid', 'invalid'),
            ]);

            DB::commit();

            CampaignRecipient::where('campaign_id', $campaign->id)
            ->where('status', 'PENDING')
            ->select('id')
            ->chunkById(100, function ($recipients) {
                foreach ($recipients as $recipient) {
                    SendCampaignRecipient::dispatch($recipient->id);
                }
            });
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->failUpload($campaign, 'Error inesperado durante procesamiento', $e);
            throw $e;
        }
    }

    private function failUpload($campaign, string $message, \Throwable $e): void
    {
        $campaign->update(['status' => 'FAILED']);

        CampaignLog::create([
            'campaign_id' => $campaign->id,
            'type' => 'FAILED',
            'message' => $message,
            'meta' => ['error' => $e->getMessage()],
        ]);
    }
}
