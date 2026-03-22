<?php
/**
 * Test de verificación para uploads/verificaciones/
 * Sube este archivo a la raíz de tu proyecto en InfinityFree
 * y ábrelo en el navegador: https://quibdoconecta.infinityfree.me/test_uploads.php
 * 
 * IMPORTANTE: Borra este archivo después de probarlo por seguridad.
 */

echo "<h2>🧪 Test de carpeta uploads/verificaciones/</h2>";

$carpeta = __DIR__ . '/uploads/verificaciones/';

// 1. ¿Existe la carpeta?
if (is_dir($carpeta)) {
    echo "<p>✅ La carpeta <code>uploads/verificaciones/</code> EXISTE</p>";
} else {
    echo "<p>❌ La carpeta <code>uploads/verificaciones/</code> NO existe</p>";
    echo "<p>💡 Créala desde el File Manager de InfinityFree</p>";
    exit;
}

// 2. ¿Tiene permisos de escritura?
if (is_writable($carpeta)) {
    echo "<p>✅ La carpeta TIENE permisos de escritura</p>";
} else {
    echo "<p>❌ La carpeta NO tiene permisos de escritura</p>";
    echo "<p>💡 Cambia los permisos a 0755 desde el File Manager (clic derecho → Permissions)</p>";
    exit;
}

// 3. Intentar crear un archivo de prueba
$archivo_test = $carpeta . 'test_' . time() . '.txt';
$resultado = file_put_contents($archivo_test, '¡QuibdóConecta funciona! 🎉 Fecha: ' . date('Y-m-d H:i:s'));

if ($resultado !== false) {
    echo "<p>✅ Se creó un archivo de prueba correctamente: <code>" . basename($archivo_test) . "</code></p>";
    
    // Leer el archivo para confirmar
    $contenido = file_get_contents($archivo_test);
    echo "<p>📄 Contenido del archivo: <em>{$contenido}</em></p>";
    
    // Borrar el archivo de prueba
    unlink($archivo_test);
    echo "<p>🗑️ Archivo de prueba borrado</p>";
    
    echo "<hr>";
    echo "<h3 style='color: green;'>🎉 ¡TODO FUNCIONA PERFECTAMENTE!</h3>";
    echo "<p>La carpeta está lista para recibir documentos de verificación.</p>";
    echo "<p><strong>⚠️ IMPORTANTE:</strong> Borra este archivo <code>test_uploads.php</code> del servidor por seguridad.</p>";
} else {
    echo "<p>❌ No se pudo crear el archivo de prueba</p>";
    echo "<p>💡 Revisa los permisos de la carpeta</p>";
}
?>
