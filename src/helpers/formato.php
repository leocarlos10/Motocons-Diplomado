<?php

/**
 * Formatea un número como pesos colombianos: $45.000.
 */
function formato_cop(float|int $valor): string
{
    return '$' . number_format((float)$valor, 0, ',', '.');
}
