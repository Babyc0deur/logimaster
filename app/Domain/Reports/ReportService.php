<?php

namespace App\Domain\Reports;

use App\Mail\ReportMail;
use App\Models\Report;
use App\Models\ReportSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Génère, stocke, télécharge et envoie les rapports ; exécute les envois planifiés. */
class ReportService
{
    public function __construct(private ReportBuilder $builder = new ReportBuilder, private ReportRenderer $renderer = new ReportRenderer) {}

    /**
     * Génère un rapport dans le format demandé et l'enregistre (disque local « reports/AAAA-MM/… »).
     *
     * @param  array<string, mixed>|null  $scope
     */
    public function generate(User $user, string $type, string $format, CarbonImmutable $month, ?array $scope = null): Report
    {
        abort_unless(in_array($format, ['pdf', 'xlsx'], true), 422, 'Format invalide (pdf ou xlsx).');
        $ids = ReportScope::ids($scope, $user);
        abort_if($ids === [], 403, 'Aucun district accessible dans ce périmètre.');

        $doc = $this->builder->build($type, $ids, $month->startOfMonth(), ReportScope::label($scope, count($ids)));
        $path = 'reports/'.$month->format('Y-m').'/'.Str::uuid().'.'.$format;
        if ($format === 'pdf') {
            Storage::disk('local')->put($path, $this->renderer->pdf($doc));
        } else {
            $tmp = $this->renderer->xlsx($doc);
            Storage::disk('local')->put($path, file_get_contents($tmp));
            @unlink($tmp);
        }

        return Report::create([
            'user_id' => $user->getKey(), 'type' => $type, 'format' => $format, 'periode' => $month->format('Y-m'),
            'scope' => $scope ?: null, 'titre' => $doc->title.' — '.$doc->subtitle, 'fichier_path' => $path, 'statut' => 'genere',
        ]);
    }

    public function filename(Report $report): string
    {
        return Str::slug($report->titre).'.'.$report->format;
    }

    /**
     * Envoie des rapports par email aux destinataires.
     *
     * @param  array<int, Report>  $reports
     * @param  array<int, string>  $recipients
     */
    public function email(array $reports, array $recipients): void
    {
        foreach ($recipients as $to) {
            Mail::to($to)->send(new ReportMail($reports));
        }
    }

    /** Envois planifiés du jour : mensuelle → mois écoulé, hebdomadaire → mois en cours. Retourne le nombre de planifications traitées. */
    public function runSchedules(?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::today();
        $sent = 0;
        foreach (ReportSchedule::with('user')->where('actif', true)->get() as $schedule) {
            if (! $schedule->isDue($today) || ! $schedule->user?->is_active) {
                continue;
            }
            $month = $schedule->frequence === 'hebdomadaire' ? $today->startOfMonth() : $today->subMonthNoOverflow()->startOfMonth();
            $reports = [];
            foreach ($schedule->formats as $format) {
                $reports[] = $this->generate($schedule->user, $schedule->type, $format, $month, $schedule->scope);
            }
            $this->email($reports, $schedule->destinataires);
            $schedule->forceFill(['dernier_envoi_at' => now()])->save();
            $sent++;
        }

        return $sent;
    }

    /**
     * Rapport mensuel DDKM automatique (PDF) envoyé le 1er du mois aux superviseurs : responsables régionaux, bailleurs, administrateurs nationaux.
     *
     * @return int nombre de rapports envoyés
     */
    public function sendMonthlyToSupervisors(?CarbonImmutable $today = null): int
    {
        $month = ($today ?? CarbonImmutable::today())->subMonthNoOverflow()->startOfMonth();
        $sent = 0;
        $users = User::role([User::ROLE_REGION_MANAGER, User::ROLE_SUPERVISEUR, User::ROLE_PRES_ADMIN])->where('is_active', true)->get();

        foreach ($users as $user) {
            // Une seule fois par utilisateur et par mois (relance sans doublon).
            $already = Report::where('user_id', $user->getKey())->where('type', 'ddkm')->where('periode', $month->format('Y-m'))->where('statut', 'envoye_auto')->exists();
            if ($already || ! $user->email || $user->accessibleDistrictIds() === []) {
                continue;
            }
            $report = $this->generate($user, 'ddkm', 'pdf', $month, null);
            $this->email([$report], [$user->email]);
            $report->update(['statut' => 'envoye_auto']);
            $sent++;
        }

        return $sent;
    }
}
