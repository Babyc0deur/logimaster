@php($circuit = $getRecord()->loadMissing('espc'))
@include('filament.components.leaflet-map', ['points' => $circuit->mapPoints(), 'line' => true])
