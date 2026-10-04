@include('errors.layout', ['code' => $exception->getStatusCode(), 'title' => "Requête invalide", 'message' => "La demande n'a pas pu être traitée. Revenez à l'accueil et réessayez."])
