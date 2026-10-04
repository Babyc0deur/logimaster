<?php

namespace App\Http\Controllers\Api;

use App\Domain\Reports\ReportBuilder;
use App\Domain\Reports\ReportService;
use App\Models\Report;
use App\Models\ReportSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Rapports : génération PDF/Excel, historique, téléchargement, envoi par email et planification. */
class ReportController extends ApiController
{
    public function __construct(private ReportService $service) {}

    public function types()
    {
        $this->requirePermission(request(), 'view_reports');

        return collect(ReportBuilder::types())->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values();
    }

    public function index(Request $request)
    {
        $this->requirePermission($request, 'view_reports');

        return Report::query()->when(! $request->user()->isNational(), fn ($q) => $q->where('user_id', $request->user()->getKey()))
            ->latest()->paginate($this->perPage($request));
    }

    /** POST /api/reports/generate {type, format, period, scope?, recipients?} */
    public function generate(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'create_reports');
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(ReportBuilder::types()))],
            'format' => ['required', Rule::in(['pdf', 'xlsx'])],
            'period' => ['required', 'date_format:Y-m'],
            'scope' => ['sometimes', 'nullable', 'array'],
            'scope.district_id' => ['nullable', 'uuid'],
            'scope.region_id' => ['nullable', 'uuid'],
            'scope.pres_id' => ['nullable', 'uuid'],
            'recipients' => ['sometimes', 'array', 'max:20'],
            'recipients.*' => ['email'],
        ]);

        $report = $this->service->generate($request->user(), $data['type'], $data['format'], CarbonImmutable::createFromFormat('Y-m', $data['period']), array_filter($data['scope'] ?? []) ?: null);
        if (! empty($data['recipients'])) {
            $this->service->email([$report], $data['recipients']);
        }

        return response()->json($report->toArray() + ['download_url' => url("/api/reports/{$report->id}/download")], 201);
    }

    public function download(Request $request, string $id)
    {
        $this->requirePermission($request, 'view_reports');
        $report = $this->visible($request)->findOrFail($id);
        abort_unless($report->exists_on_disk(), 404, 'Fichier introuvable.');

        return Storage::disk('local')->download($report->fichier_path, $this->service->filename($report));
    }

    public function email(Request $request, string $id)
    {
        $this->requirePermission($request, 'create_reports');
        $recipients = $request->validate(['recipients' => ['required', 'array', 'min:1', 'max:20'], 'recipients.*' => ['email']])['recipients'];
        $report = $this->visible($request)->findOrFail($id);
        abort_unless($report->exists_on_disk(), 404, 'Fichier introuvable.');
        $this->service->email([$report], $recipients);

        return ['sent_to' => $recipients];
    }

    // ------------------------------------------------------------------ planification

    public function schedules(Request $request)
    {
        $this->requirePermission($request, 'view_reports');

        return ReportSchedule::query()->when(! $request->user()->isNational(), fn ($q) => $q->where('user_id', $request->user()->getKey()))->latest()->get();
    }

    /** POST /api/reports/schedule */
    public function schedule(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'create_reports');
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(ReportBuilder::types()))],
            'formats' => ['required', 'array', 'min:1'],
            'formats.*' => [Rule::in(['pdf', 'xlsx'])],
            'frequence' => ['required', Rule::in(['hebdomadaire', 'mensuelle'])],
            'jour' => ['required', 'integer', 'between:1,28'],
            'destinataires' => ['required', 'array', 'min:1', 'max:20'],
            'destinataires.*' => ['email'],
            'scope' => ['sometimes', 'nullable', 'array'],
        ]);
        abort_if($data['frequence'] === 'hebdomadaire' && $data['jour'] > 7, 422, 'Pour un envoi hebdomadaire, le jour est compris entre 1 (lundi) et 7 (dimanche).');

        $schedule = ReportSchedule::create($data + ['user_id' => $request->user()->getKey(), 'scope' => array_filter($data['scope'] ?? []) ?: null]);

        return response()->json($schedule, 201);
    }

    public function destroySchedule(Request $request, string $id): JsonResponse
    {
        $this->requirePermission($request, 'create_reports');
        ReportSchedule::query()->when(! $request->user()->isNational(), fn ($q) => $q->where('user_id', $request->user()->getKey()))->findOrFail($id)->delete();

        return response()->json(null, 204);
    }

    private function visible(Request $request)
    {
        return Report::query()->when(! $request->user()->isNational(), fn ($q) => $q->where('user_id', $request->user()->getKey()));
    }
}
