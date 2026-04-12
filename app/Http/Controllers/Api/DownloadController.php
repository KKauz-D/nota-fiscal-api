<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Batch;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadController extends Controller
{
    use ApiResponse;

    public function xml(Batch $batch): StreamedResponse|JsonResponse
    {
        if (! $batch->xml_file || ! Storage::disk('xml')->exists($batch->xml_file)) {
            return $this->error('Arquivo XML não encontrado.', 404);
        }

        return Storage::disk('xml')->download($batch->xml_file, $batch->xml_file, [
            'Content-Type' => 'application/xml',
        ]);
    }
}
