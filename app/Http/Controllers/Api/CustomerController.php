<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportCustomersRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Traits\ApiResponse;
use App\Models\Customer;
use App\Services\Import\CustomerImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CustomerImportService $customerImportService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Customer::orderBy('razao_social');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('razao_social', 'like', "%{$search}%")
                  ->orWhere('cpf_cnpj', 'like', "%{$search}%");
            });
        }

        $customers = $query->paginate($request->query('per_page', 50));

        return $this->success(CustomerResource::collection($customers)->response()->getData(true));
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $customer->update($request->validated());

        return $this->success(new CustomerResource($customer->fresh()), 'Tomador atualizado com sucesso.');
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $customer->delete();

        return $this->success(message: 'Tomador excluído com sucesso.');
    }

    public function importar(ImportCustomersRequest $request): JsonResponse
    {
        $file = $request->file('excel_file');
        $tempPath = $file->store('temp', 'local');
        $fullPath = storage_path('app/private/' . $tempPath);

        try {
            $count = $this->customerImportService->import($fullPath);

            return $this->success(
                ['count' => $count],
                "{$count} tomador(es) importado(s) com sucesso!",
            );
        } finally {
            @unlink($fullPath);
        }
    }
}
