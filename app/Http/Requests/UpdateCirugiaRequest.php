<?php

namespace App\Http\Requests;

/**
 * Corregir un procedimiento exige exactamente lo mismo que registrarlo:
 * las reglas se heredan sin cambios para que una corrección nunca pueda
 * dejar en la base datos que el registro original no habría aceptado.
 */
class UpdateCirugiaRequest extends StoreCirugiaRequest {}
