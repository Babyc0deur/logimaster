@php($espc = $getRecord())
@include('filament.components.leaflet-map', [
    'points' => $espc->hasGps() ? [['lat' => $espc->gps_lat, 'lon' => $espc->gps_lon, 'label' => $espc->nom, 'n' => '★', 'type' => 'etape']] : [],
    'line' => false,
    'height' => '280px',
])
