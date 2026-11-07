<?php

/**
 * Traducciones al inglés de los textos del framework que ve el usuario final (validación, errores y
 * archivos). La app las puede sobrescribir en lang/en.php.
 */

declare(strict_types=1);

return [
    // Validación
    'El campo :attribute es obligatorio.' => 'The :attribute field is required.',
    'El campo :attribute debe ser texto.' => 'The :attribute field must be text.',
    'El campo :attribute debe ser un número entero.' => 'The :attribute field must be an integer.',
    'El campo :attribute debe ser un número.' => 'The :attribute field must be a number.',
    'El campo :attribute debe ser verdadero o falso.' => 'The :attribute field must be true or false.',
    'El campo :attribute debe ser una lista.' => 'The :attribute field must be a list.',
    'El campo :attribute debe ser un correo electrónico válido.' => 'The :attribute field must be a valid email address.',
    'El campo :attribute debe ser una URL válida.' => 'The :attribute field must be a valid URL.',
    'El campo :attribute debe tener al menos :min caracteres.' => 'The :attribute field must be at least :min characters.',
    'El campo :attribute debe ser al menos :min.' => 'The :attribute field must be at least :min.',
    'El campo :attribute debe tener al menos :min elementos.' => 'The :attribute field must have at least :min items.',
    'El archivo :attribute debe pesar al menos :min KB.' => 'The :attribute file must be at least :min KB.',
    'El campo :attribute no puede tener más de :max caracteres.' => 'The :attribute field may not be longer than :max characters.',
    'El campo :attribute no puede ser mayor que :max.' => 'The :attribute field may not be greater than :max.',
    'El campo :attribute no puede tener más de :max elementos.' => 'The :attribute field may not have more than :max items.',
    'El archivo :attribute no puede pesar más de :max KB.' => 'The :attribute file may not be larger than :max KB.',
    'El campo :attribute debe tener entre :min y :max caracteres.' => 'The :attribute field must be between :min and :max characters.',
    'El campo :attribute debe estar entre :min y :max.' => 'The :attribute field must be between :min and :max.',
    'El campo :attribute debe tener entre :min y :max elementos.' => 'The :attribute field must have between :min and :max items.',
    'El archivo :attribute debe pesar entre :min y :max KB.' => 'The :attribute file must be between :min and :max KB.',
    'El valor de :attribute no es válido.' => 'The selected :attribute is invalid.',
    'La confirmación de :attribute no coincide.' => 'The :attribute confirmation does not match.',
    'El campo :attribute debe ser una fecha válida.' => 'The :attribute field must be a valid date.',
    'El formato de :attribute no es válido.' => 'The :attribute format is invalid.',
    'Ya existe un registro con ese :attribute.' => 'The :attribute has already been taken.',
    'El :attribute seleccionado no existe.' => 'The selected :attribute does not exist.',
    'El campo :attribute debe ser un archivo.' => 'The :attribute field must be a file.',
    'El archivo :attribute debe ser una imagen (jpg, png, gif o webp).' => 'The :attribute file must be an image (jpg, png, gif or webp).',
    'El archivo :attribute debe ser de tipo: :values.' => 'The :attribute file must be of type: :values.',
    'El campo :attribute no es válido (:rule).' => 'The :attribute field is invalid (:rule).',
    'Los datos enviados no son válidos.' => 'The given data was invalid.',

    // Errores
    'Ocurrió un error inesperado. Inténtalo de nuevo más tarde.' => 'An unexpected error occurred. Please try again later.',
    'Registro no encontrado.' => 'Record not found.',
    'Necesitas iniciar sesión.' => 'You need to log in.',
    'Demasiadas peticiones. Intenta de nuevo en :seconds segundos.' => 'Too many requests. Try again in :seconds seconds.',
    'La sesión expiró. Recarga la página e inténtalo de nuevo.' => 'Your session expired. Reload the page and try again.',

    // Páginas de make:resource
    'Nuevo' => 'New',
    'Editar' => 'Edit',
    'Borrar' => 'Delete',
    'Volver' => 'Back',
    'Guardar' => 'Save',
    'Cancelar' => 'Cancel',
    'Todavía no hay registros.' => 'No records yet.',
    '¿Borrar este registro? No se puede deshacer.' => 'Delete this record? This cannot be undone.',
    'Registro creado.' => 'Record created.',
    'Registro actualizado.' => 'Record updated.',
    'Registro borrado.' => 'Record deleted.',

    // Archivos
    'El enlace no es válido o ya venció.' => 'The link is invalid or has expired.',
    'El archivo ya no existe.' => 'The file no longer exists.',
];
