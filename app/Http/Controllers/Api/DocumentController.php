<?php

namespace App\Http\Controllers\Api;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class DocumentController extends ApiController
{
    public function index(Request $request)
    {
        $this->requirePermission($request, 'view_documents');

        return $this->scoped($request, Document::query())
            ->when($request->query('documentable_type'), fn ($q, $t) => $q->where('documentable_type', $t))
            ->when($request->query('documentable_id'), fn ($q, $i) => $q->where('documentable_id', $i))
            ->when($request->query('categorie'), fn ($q, $c) => $q->where('categorie', $c))
            ->latest()->paginate($this->perPage($request));
    }

    public function store(Request $request)
    {
        $this->requirePermission($request, 'create_documents');
        $data = $request->validate([
            'documentable_type' => ['required', Rule::in(['vehicle', 'driver', 'sortie'])],
            'documentable_id' => ['required', 'uuid'],
            'categorie' => ['required', 'string', 'max:50'],
            'date_expiration' => ['nullable', 'date'],
            'fichier' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
        ]);

        $target = (new ($this->documentableClass($data['documentable_type'])))->newQuery()->findOrFail($data['documentable_id']);
        $this->assertDistrictAccess($request, $target->district_id);

        $path = $request->file('fichier')->store("documents/{$target->district_id}", 'local');

        $document = Document::create([
            'documentable_type' => $data['documentable_type'],
            'documentable_id' => $target->getKey(),
            'district_id' => $target->district_id,
            'categorie' => $data['categorie'],
            'fichier_url' => $path,
            'date_expiration' => $data['date_expiration'] ?? null,
            'uploaded_by' => $request->user()->getKey(),
        ]);

        return response()->json($document, 201);
    }

    public function download(Request $request, string $id)
    {
        $this->requirePermission($request, 'view_documents');
        $document = $this->scoped($request, Document::query())->findOrFail($id);
        abort_unless(Storage::disk('local')->exists($document->fichier_url), 404, 'Fichier introuvable.');

        return Storage::disk('local')->download($document->fichier_url);
    }

    private function documentableClass(string $type): string
    {
        return match ($type) {
            'vehicle' => \App\Models\Vehicle::class,
            'driver' => \App\Models\Driver::class,
            'sortie' => \App\Models\SortieVehicule::class,
        };
    }
}
