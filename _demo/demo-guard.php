<?php
// ============================================================
// Guardarrail de la demo publica del POS.
//
// La demo entra como ADMINISTRADOR y la contrasena esta impresa en la pagina, asi
// que hay que dar por hecho que cualquier visitante va a tocar todo. Ya paso en la
// demo hermana del ERP: alguien desactivo la cuenta de acceso y la dejo muerta para
// todos los demas hasta que se reinicio el servicio.
//
// La respuesta NO es recortarle permisos al usuario de la demo (media gracia de la
// demo es poder meterse en todo), sino reparar el estado minimo que hace falta para
// que el SIGUIENTE visitante pueda entrar y ver algo:
//   - los tres usuarios anunciados existen, estan activos y con su contrasena;
//   - hay productos activos que vender.
// Todo lo demas (facturas, inventario, configuracion) se deja como lo dejo la gente:
// es un sandbox y el contenedor resiembra database.sql en cada arranque en frio.
//
// Se engancha con auto_prepend_file desde el Dockerfile para no tocar el codigo de
// la aplicacion. Corre en CADA peticion, asi que aqui dentro NADA puede lanzar: todo
// va en try/catch y solo se escribe si de verdad se encuentra algo mal.
// ============================================================

const PIXIES_POS_PASS = 'password';

// usuario => [rol_id, nombre, apellido, email]
const PIXIES_POS_USUARIOS = [
    'admin'   => [1, 'Administrador', 'Sistema', 'admin@pos.com'],
    'cajero1' => [2, 'Juan', 'Cajero', 'cajero@pos.com'],
    'mesero1' => [3, 'Maria', 'Mesero', 'mesero@pos.com'],
];

function pixies_pos_pdo()
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    try {
        $host = getenv('DB_HOST') ?: 'localhost';
        $name = getenv('DB_NAME') ?: 'pos_db';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';
        $pdo = new PDO(
            "mysql:host={$host};dbname={$name};charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (Throwable $e) {
        $pdo = false; // no reintentar en esta peticion
    }
    return $pdo ?: null;
}

function pixies_pos_reparar_usuarios(PDO $pdo)
{
    foreach (PIXIES_POS_USUARIOS as $usuario => $datos) {
        list($rolId, $nombre, $apellido, $email) = $datos;

        $st = $pdo->prepare('SELECT id, activo, password, rol_id FROM usuarios WHERE usuario = ?');
        $st->execute([$usuario]);
        $fila = $st->fetch();

        if (!$fila) {
            // Lo borraron: se vuelve a crear con su rol y su contrasena publica.
            $pdo->prepare(
                'INSERT INTO usuarios (rol_id, nombre, apellido, usuario, password, email, activo)
                 VALUES (?, ?, ?, ?, ?, ?, 1)'
            )->execute([
                $rolId, $nombre, $apellido, $usuario,
                password_hash(PIXIES_POS_PASS, PASSWORD_BCRYPT), $email,
            ]);
            continue;
        }

        $sets = [];
        $vals = [];
        if ((int) $fila['activo'] !== 1) {
            $sets[] = 'activo = 1';
        }
        if ((int) $fila['rol_id'] !== $rolId) {
            $sets[] = 'rol_id = ?';
            $vals[] = $rolId;
        }
        if (!password_verify(PIXIES_POS_PASS, (string) $fila['password'])) {
            $sets[] = 'password = ?';
            $vals[] = password_hash(PIXIES_POS_PASS, PASSWORD_BCRYPT);
        }
        if ($sets) {
            $vals[] = $fila['id'];
            $pdo->prepare('UPDATE usuarios SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
        }
    }
}

function pixies_pos_reparar_catalogo(PDO $pdo)
{
    // "Eliminar" un producto en esta app es desactivarlo. Si un visitante los apaga
    // todos, el siguiente encuentra un punto de venta sin nada que vender y parece roto.
    $pendientes = (int) $pdo->query('SELECT COUNT(*) FROM productos WHERE activo = 0')->fetchColumn();
    if ($pendientes > 0) {
        $pdo->exec('UPDATE productos SET activo = 1 WHERE activo = 0');
    }
    // Un producto sin stock tampoco se puede vender; se repone solo si quedo en cero.
    $pdo->exec('UPDATE productos SET stock_actual = 50 WHERE tiene_stock = 1 AND stock_actual <= 0');
}

function pixies_pos_reparar_config(PDO $pdo)
{
    // impuesto_porcentaje es DECIMAL(5,2) (max 999.99). Un visitante lo ponia a 99999
    // desde Configuracion y a partir de ahi NINGUNA venta se podia cobrar (INSERT fuera
    // de rango) para el resto de visitantes, hasta reparacion manual. La validacion en
    // configuracion.php ya lo impide en origen; esto repara cualquier valor malo que
    // hubiera quedado antes del arreglo o por una via distinta. Solo se toca si esta mal.
    $imp = $pdo->query("SELECT valor FROM configuracion WHERE clave = 'impuesto_porcentaje'")->fetchColumn();
    if ($imp !== false && (!is_numeric($imp) || (float) $imp < 0 || (float) $imp > 100)) {
        $pdo->prepare("UPDATE configuracion SET valor = '0' WHERE clave = 'impuesto_porcentaje'")->execute();
    }
    // empresa_nombre sale en el <title> del login: se restaura si lo dejaron vacio o con
    // etiquetas (defiguracion / intento de XSS en el titulo).
    $emp = $pdo->query("SELECT valor FROM configuracion WHERE clave = 'empresa_nombre'")->fetchColumn();
    if ($emp !== false && (trim((string) $emp) === '' || preg_match('/[<>]/', (string) $emp))) {
        $pdo->prepare("UPDATE configuracion SET valor = 'Mi Restaurante' WHERE clave = 'empresa_nombre'")->execute();
    }
}

function pixies_pos_reparar_mesas(PDO $pdo)
{
    // Un visitante podia mandar mesas a 'mantenimiento', un estado que la UI del mesero
    // no sabe deshacer (clickMesa no tiene rama para el): la mesa quedaba inutilizable
    // para el siguiente. Se devuelven a 'disponible' (no se tocan ocupada/reservada, que
    // son estados legitimos del recorrido de la demo).
    $pdo->exec("UPDATE mesas SET estado = 'disponible' WHERE estado = 'mantenimiento'");
}

function pixies_pos_reparar()
{
    $pdo = pixies_pos_pdo();
    if (!$pdo) {
        return;
    }
    try {
        pixies_pos_reparar_usuarios($pdo);
        pixies_pos_reparar_catalogo($pdo);
        pixies_pos_reparar_config($pdo);
        pixies_pos_reparar_mesas($pdo);
    } catch (Throwable $e) {
        // Una demo rota es mejor que un 500 en todo el sitio: se traga y se sigue.
    }
}

$pixies_pos_script = basename($_SERVER['SCRIPT_NAME'] ?? '');

if ($pixies_pos_script === 'index.php') {
    // Pantalla de acceso: es el momento natural para dejar la demo utilizable.
    pixies_pos_reparar();
} elseif (in_array($pixies_pos_script, ['usuarios.php', 'productos.php', 'configuracion.php', 'mesas.php'], true)) {
    // Despues de que el visitante haya podido romper algo, no antes: asi ve el efecto
    // de lo que hizo durante su visita y aun asi queda reparado para el siguiente.
    // configuracion.php y mesas.php cubren el brick del impuesto y las mesas atascadas.
    register_shutdown_function('pixies_pos_reparar');
}
