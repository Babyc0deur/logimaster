@php
    $user = $personnel->user;
    $temporary = $user?->must_change_password && filled($personnel->code_acces);
    $state = match (true) {
        ! $user => ['Aucun accès', '#6b7280'],
        ! $user->is_active => ['Suspendu (fiche inactive ou fonction non convoyeur)', '#dc2626'],
        $temporary => ['Code à remettre : le convoyeur ne s\'est pas encore connecté', '#d97706'],
        $user->last_mobile_login_at => ['Actif · dernière connexion '.$user->last_mobile_login_at->format('d/m/Y H:i'), '#16a34a'],
        default => ['Mot de passe choisi', '#16a34a'],
    };
    $box = 'background:rgba(127,127,127,.12);border-radius:10px;padding:10px 14px;font-family:ui-monospace,Consolas,monospace;font-size:1.15rem;letter-spacing:.04em';
@endphp
<div style="display:flex;flex-direction:column;gap:14px">
    <div style="font-size:.9rem;color:{{ $state[1] }};font-weight:600">{{ $state[0] }}</div>
    <div>
        <div style="font-size:.8rem;opacity:.7;margin-bottom:4px">Identifiant</div>
        <div style="{{ $box }}">{{ $personnel->identifiant }}</div>
    </div>
    @if ($temporary)
        <div>
            <div style="font-size:.8rem;opacity:.7;margin-bottom:4px">Code d'accès provisoire</div>
            <div style="{{ $box }}">{{ $personnel->code_acces }}</div>
            <div style="font-size:.8rem;opacity:.7;margin-top:6px">À remettre au convoyeur avec le QR code d'installation. Il choisira son mot de passe à la première connexion, et ce code disparaîtra d'ici.</div>
        </div>
    @elseif ($user?->is_active)
        <div style="font-size:.85rem;opacity:.75">Le convoyeur a choisi son mot de passe : en cas d'oubli, utilisez « Réinitialiser le code ».</div>
    @endif
</div>
