<?php

namespace App\Http\Controllers\Api;

use App\Domain\Import\ImportException;
use App\Domain\Import\ImportRegistry;
use App\Domain\Import\SpreadsheetImporter;
use App\Domain\Import\Workbook\WorkbookExporter;
use App\Domain\Import\Workbook\WorkbookImporter;
use Illuminate\Http\Request;

/** Modèles d'import, import en masse et export au format d'import (véhicules, chauffeurs, circuits, ESPC, chronogramme). */
class ImportController extends ApiController
{
    public function entities()
    {
        return collect(ImportRegistry::all())->map(fn ($class, $key) => ['key' => $key, 'label' => (new $class)->label(), 'columns' => (new $class)->columns()])->values();
    }

    private const WORKBOOK_MODULES = ['vehicles', 'drivers', 'circuits', 'espc', 'chronogrammes', 'sorties', 'ravitaillements', 'expenses', 'vidanges', 'immobilisations'];

    /** Modèle vide du classeur Logimaster (mêmes onglets et en-têtes que le modèle Excel des districts). */
    public function workbookTemplate(Request $request)
    {
        $this->requirePermission($request, 'view_vehicles');

        return response()->download((new WorkbookExporter)->template(), 'modele-logimaster.xlsx')->deleteFileAfterSend();
    }

    public function workbookExport(Request $request)
    {
        $this->requirePermission($request, 'view_vehicles');
        $districtId = $request->validate(['district_id' => ['required', 'uuid', 'exists:districts,id']])['district_id'];
        $this->assertDistrictAccess($request, $districtId);

        return response()->download((new WorkbookExporter)->export($districtId), "logimaster-{$districtId}.xlsx")->deleteFileAfterSend();
    }

    /** Importe le classeur complet (.xlsx / .xlsm) d'un district. Tout ou rien par défaut. */
    public function workbookImport(Request $request)
    {
        foreach (self::WORKBOOK_MODULES as $module) {
            $this->requirePermission($request, "create_{$module}");
        }
        $data = $request->validate([
            'district_id' => ['required', 'uuid', 'exists:districts,id'],
            'file' => ['required', 'file', 'max:51200', 'extensions:xlsx,xlsm'],
            'skip_invalid' => ['sometimes', 'boolean'],
        ]);
        $this->assertDistrictAccess($request, $data['district_id']);

        $file = $request->file('file')->move(sys_get_temp_dir(), uniqid('wb_', true).'.xlsx');
        try {
            $report = (new WorkbookImporter)->import($file->getPathname(), $data['district_id'], (bool) ($data['skip_invalid'] ?? false));
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Classeur illisible : '.$e->getMessage()], 422);
        } finally {
            @unlink($file->getPathname());
        }

        return response()->json($report->toArray(), $report->committed ? 200 : 422);
    }

    public function template(Request $request, string $entity, SpreadsheetImporter $importer)
    {
        $def = ImportRegistry::get($entity);
        $this->requirePermission($request, "view_{$def->module()}");

        return response()->download($importer->template($def), "modele-import-{$entity}.xlsx")->deleteFileAfterSend();
    }

    public function export(Request $request, string $entity, SpreadsheetImporter $importer)
    {
        $def = ImportRegistry::get($entity);
        $this->requirePermission($request, "view_{$def->module()}");
        $districtId = $request->validate(['district_id' => ['required', 'uuid']])['district_id'];
        $this->assertDistrictAccess($request, $districtId);

        return response()->download($importer->export($def, $districtId), "{$entity}-donnees.xlsx")->deleteFileAfterSend();
    }

    /** Importe un fichier .xlsx/.csv. Tout ou rien par défaut ; skip_invalid=1 importe les lignes valides. */
    public function import(Request $request, string $entity, SpreadsheetImporter $importer)
    {
        $def = ImportRegistry::get($entity);
        $this->requirePermission($request, "create_{$def->module()}");
        $data = $request->validate([
            'district_id' => ['required', 'uuid', 'exists:districts,id'],
            'file' => ['required', 'file', 'max:10240', 'extensions:xlsx,csv'],
            'skip_invalid' => ['sometimes', 'boolean'],
        ]);
        $this->assertDistrictAccess($request, $data['district_id']);

        // Le lecteur choisit xlsx/csv d'après l'extension : on la conserve sur la copie temporaire.
        $file = $request->file('file')->move(sys_get_temp_dir(), uniqid('import_', true).'.'.strtolower($request->file('file')->getClientOriginalExtension()));
        try {
            $report = $importer->import($def, $file->getPathname(), $data['district_id'], (bool) ($data['skip_invalid'] ?? false));
        } catch (ImportException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } finally {
            @unlink($file->getPathname());
        }

        return response()->json($report->toArray(), $report->committed ? 200 : 422);
    }
}
