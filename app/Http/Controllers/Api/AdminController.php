<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\District;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminController extends ApiController
{
    public function auditLogs(Request $request)
    {
        $this->requirePermission($request, 'view_audit_logs');
        $request->validate(['period' => ['nullable', 'date_format:Y-m']]);

        return AuditLog::query()
            ->when($request->query('user_id'), fn ($q, $v) => $q->where('user_id', $v))
            ->when($request->query('module'), fn ($q, $v) => $q->where('module', $v))
            ->when($request->query('period'), fn ($q, $p) => $q->whereBetween('created_at', [
                \Carbon\Carbon::createFromFormat('Y-m', $p)->startOfMonth(),
                \Carbon\Carbon::createFromFormat('Y-m', $p)->endOfMonth(),
            ]))
            ->with('user:id,name')->latest()->paginate($this->perPage($request));
    }

    public function devices(Request $request, string $districtId)
    {
        $this->requirePermission($request, 'view_districts');
        $this->assertDistrictAccess($request, $districtId);

        return District::findOrFail($districtId)->devices()->orderByDesc('last_sync_at')->get();
    }

    /** Génère un nouveau mot de passe de sync ; il n'est renvoyé qu'une seule fois (seul le hash est stocké). */
    public function rotateSyncCredentials(Request $request, string $districtId)
    {
        $this->requirePermission($request, 'manage_districts');
        $this->assertDistrictAccess($request, $districtId);

        $district = District::findOrFail($districtId);
        $password = Str::password(16, symbols: false);
        $district->update(['sync_password_hash' => Hash::make($password), 'sync_password_rotated_at' => now()]);

        return ['sync_id' => $district->sync_id, 'sync_password' => $password, 'rotated_at' => $district->sync_password_rotated_at];
    }
}
