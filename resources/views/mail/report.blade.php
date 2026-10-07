<!doctype html>
<html lang="fr">
<body style="font-family:Arial,Helvetica,sans-serif;color:#111827;font-size:14px;line-height:1.5">
    <div style="border-bottom:3px solid #2563eb;padding-bottom:8px;margin-bottom:14px">
        <strong style="color:#2563eb;letter-spacing:1px">LOGIMASTER PRO</strong>
    </div>
    <p>Bonjour,</p>
    <p>Veuillez trouver ci-joint {{ count($reports) > 1 ? 'les rapports suivants' : 'le rapport suivant' }} :</p>
    <ul>
        @foreach ($reports as $report)
            <li><strong>{{ $report->titre }}</strong> ({{ strtoupper($report->format) }})</li>
        @endforeach
    </ul>
    <p style="color:#6b7280;font-size:12px">Ce message est envoyé automatiquement par la plateforme LogiMaster. Les données sont celles du mois indiqué, arrêtées à la date de génération.</p>
</body>
</html>
