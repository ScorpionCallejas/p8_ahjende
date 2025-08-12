<?php
// Configuración de la base de datos
$host = "localhost";
$port = 3306;
$user = "root";
$pass = "";
$database = "db_test";

// Establecer conexión
$conn = new mysqli($host, $user, $pass, $database, $port);
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}
$conn->set_charset("utf8");

// Crear tabla si no existe
$conn->query("CREATE TABLE IF NOT EXISTS ejecutivo (
    id_eje INT(11) AUTO_INCREMENT PRIMARY KEY,
    nom_eje VARCHAR(255) NOT NULL,
    tel_eje VARCHAR(15) NOT NULL,
    eli_eje INT DEFAULT 1,
    id_padre INT NULL,
    FOREIGN KEY (id_padre) REFERENCES ejecutivo(id_eje) ON DELETE SET NULL
) ENGINE=InnoDB");

// Manejo de acciones
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    $action = $_GET['action'];
    
    if ($action == 'get_tree') {
        $query = "SELECT id_eje as id, nom_eje as text, id_padre 
                  FROM ejecutivo 
                  WHERE eli_eje = 1 
                  ORDER BY nom_eje";
        $result = $conn->query($query);
        
        if (!$result) {
            echo json_encode(array('error' => 'Error al obtener datos: ' . $conn->error));
            exit;
        }
        
        $ejecutivos = array();
        while($row = $result->fetch_assoc()) {
            $ejecutivos[] = $row;
        }
        
        // Construir árbol jerárquico
        function buildTree($elements, $parentId = null) {
            $branch = array();
            foreach ($elements as $element) {
                if ($element['id_padre'] == $parentId) {
                    $children = buildTree($elements, $element['id']);
                    if ($children) {
                        $element['children'] = $children;
                    }
                    $branch[] = $element;
                }
            }
            return $branch;
        }
        
        echo json_encode(buildTree($ejecutivos));
    }
    elseif ($action == 'create') {
        $nombre = isset($_POST['nombre']) ? $conn->real_escape_string($_POST['nombre']) : '';
        $telefono = isset($_POST['telefono']) ? $conn->real_escape_string($_POST['telefono']) : '';
        $padre = isset($_POST['padre']) && $_POST['padre'] ? (int)$_POST['padre'] : null;
        
        if (empty($nombre) || empty($telefono)) {
            echo json_encode(array('error' => 'Nombre y teléfono son obligatorios'));
            exit;
        }
        
        $stmt = $conn->prepare("INSERT INTO ejecutivo (nom_eje, tel_eje, id_padre) VALUES (?, ?, ?)");
        if (!$stmt) {
            echo json_encode(array('error' => 'Error al preparar consulta: ' . $conn->error));
            exit;
        }
        
        $stmt->bind_param("ssi", $nombre, $telefono, $padre);
        
        if ($stmt->execute()) {
            echo json_encode(array('success' => true, 'id' => $stmt->insert_id));
        } else {
            echo json_encode(array('error' => 'Error al crear ejecutivo: ' . $stmt->error));
        }
    }
    elseif ($action == 'update') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $nombre = isset($_POST['nombre']) ? $conn->real_escape_string($_POST['nombre']) : '';
        $telefono = isset($_POST['telefono']) ? $conn->real_escape_string($_POST['telefono']) : '';
        
        if ($id <= 0 || empty($nombre) || empty($telefono)) {
            echo json_encode(array('error' => 'Datos incompletos'));
            exit;
        }
        
        $stmt = $conn->prepare("UPDATE ejecutivo SET nom_eje = ?, tel_eje = ? WHERE id_eje = ?");
        if (!$stmt) {
            echo json_encode(array('error' => 'Error al preparar consulta: ' . $conn->error));
            exit;
        }
        
        $stmt->bind_param("ssi", $nombre, $telefono, $id);
        
        if ($stmt->execute()) {
            echo json_encode(array('success' => true));
        } else {
            echo json_encode(array('error' => 'Error al actualizar ejecutivo: ' . $stmt->error));
        }
    }
    elseif ($action == 'delete') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        if ($id <= 0) {
            echo json_encode(array('error' => 'ID inválido'));
            exit;
        }
        
        // Iniciar transacción para operación atómica
        $conn->begin_transaction();
        
        try {
            // 1. Obtener el padre del nodo que vamos a eliminar
            $stmt = $conn->prepare("SELECT id_padre FROM ejecutivo WHERE id_eje = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $id_padre = $row['id_padre'];
            
            // 2. Actualizar los hijos para que apunten al abuelo
            $stmt = $conn->prepare("UPDATE ejecutivo SET id_padre = ? WHERE id_padre = ?");
            $stmt->bind_param("ii", $id_padre, $id);
            if (!$stmt->execute()) {
                throw new Exception('Error al actualizar hijos: ' . $stmt->error);
            }
            
            // 3. Eliminar el ejecutivo (borrado lógico)
            $stmt = $conn->prepare("UPDATE ejecutivo SET eli_eje = 0 WHERE id_eje = ?");
            $stmt->bind_param("i", $id);
            if (!$stmt->execute()) {
                throw new Exception('Error al eliminar ejecutivo: ' . $stmt->error);
            }
            
            $conn->commit();
            echo json_encode(array('success' => true));
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(array('error' => $e->getMessage()));
        }
    }
    elseif ($action == 'move_node') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $parent = isset($_POST['parent']) ? ($_POST['parent'] === '#' ? null : (int)$_POST['parent']) : null;
        
        if ($id <= 0) {
            echo json_encode(array('error' => 'ID inválido'));
            exit;
        }
        
        // Verificación de bucle jerárquico
        if ($parent !== null) {
            $current = $parent;
            $checked = array($id => true);
            $max_depth = 100;
            
            while ($current !== null && $max_depth-- > 0) {
                if (isset($checked[$current])) {
                    echo json_encode(array('error' => 'No se puede mover: crearía un bucle jerárquico'));
                    exit;
                }
                $checked[$current] = true;
                
                $stmt = $conn->prepare("SELECT id_padre FROM ejecutivo WHERE id_eje = ?");
                $stmt->bind_param("i", $current);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) break;
                
                $row = $result->fetch_assoc();
                $current = $row['id_padre'];
            }
        }
        
        $stmt = $conn->prepare("UPDATE ejecutivo SET id_padre = ? WHERE id_eje = ?");
        if (!$stmt) {
            echo json_encode(array('error' => 'Error al preparar consulta: ' . $conn->error));
            exit;
        }
        
        $stmt->bind_param("ii", $parent, $id);
        
        if ($stmt->execute()) {
            echo json_encode(array('success' => true));
        } else {
            echo json_encode(array('error' => 'Error al mover el nodo: ' . $stmt->error));
        }
    }
    elseif ($action == 'get_details') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($id <= 0) {
            echo json_encode(array('error' => 'ID inválido'));
            exit;
        }
        
        $stmt = $conn->prepare("SELECT id_eje as id, nom_eje as nombre, tel_eje as telefono, id_padre 
                               FROM ejecutivo 
                               WHERE id_eje = ? AND eli_eje = 1");
        if (!$stmt) {
            echo json_encode(array('error' => 'Error al preparar consulta: ' . $conn->error));
            exit;
        }
        
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            echo json_encode(array(
                'success' => true,
                'nombre' => $row['nombre'],
                'telefono' => $row['telefono'],
                'id_padre' => $row['id_padre']
            ));
        } else {
            echo json_encode(array('error' => 'Ejecutivo no encontrado'));
        }
    }
    else {
        echo json_encode(array('error' => 'Acción no válida'));
    }
    
    $conn->close();
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Ejecutivos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.12/themes/default/style.min.css" />
    <style>
        :root {
            --primary-color: #6D4C41;
            --secondary-color: #8D6E63;
            --light-color: #D7CCC8;
            --background-color: #EFEBE9;
            --text-dark: #3E2723;
            --text-light: #FFFFFF;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--background-color);
            color: var(--text-dark);
            padding-top: 20px;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            background-color: white;
        }
        
        .card-header {
            background-color: var(--primary-color);
            color: var(--text-light);
            border-radius: 10px 10px 0 0 !important;
            font-weight: 600;
            padding: 12px 20px;
        }
        
        #ejecutivosTree {
            padding: 15px;
            background-color: white;
            border-radius: 5px;
            min-height: 500px;
            border: 1px solid var(--light-color);
            overflow: auto;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .btn-danger {
            background-color: #D32F2F;
            border-color: #D32F2F;
        }
        
        .btn-outline-secondary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-outline-secondary:hover {
            background-color: var(--primary-color);
            color: white;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(109, 76, 65, 0.25);
        }
        
        #selectedParent {
            padding: 8px;
            background-color: var(--background-color);
            border-radius: 4px;
            margin-bottom: 15px;
            min-height: 38px;
            border: 1px dashed var(--secondary-color);
        }
        
        .jstree-anchor {
            font-size: 14px;
        }
        
        .action-buttons {
            margin-top: 20px;
        }
        
        .status-message {
            display: none;
            margin-top: 15px;
        }
        
        .jstree-drop-ok {
            border: 1px dashed var(--primary-color) !important;
            background-color: rgba(109, 76, 65, 0.1) !important;
        }
        
        .jstree-wholerow-clicked {
            background: var(--light-color);
        }
        
        .jstree-wholerow-hovered {
            background: var(--background-color);
        }
        
        h1 {
            color: var(--primary-color);
            font-weight: 700;
        }
        
        .refresh-btn {
            background-color: var(--light-color);
            color: var(--text-dark);
        }
        
        .refresh-btn:hover {
            background-color: var(--secondary-color);
            color: var(--text-light);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row mb-4">
            <div class="col-12 text-center">
                <h1><i class="fas fa-sitemap me-2"></i> Estructura Jerárquica</h1>
                <p class="text-muted">Gestión completa de ejecutivos con eliminación inteligente</p>
            </div>
        </div>
        
        <div class="row">
            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-network-wired me-2"></i> Árbol Organizacional</span>
                        <button id="refresh" class="btn btn-sm refresh-btn">
                            <i class="fas fa-sync-alt"></i> Actualizar
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="ejecutivosTree"></div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="fas fa-user-tie me-2"></i>
                        <span id="formTitle">Nuevo Ejecutivo</span>
                    </div>
                    <div class="card-body">
                        <form id="ejecutivoForm">
                            <input type="hidden" id="ejecutivoId" value="0">
                            
                            <div class="mb-3">
                                <label for="nombre" class="form-label">Nombre</label>
                                <input type="text" class="form-control" id="nombre" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="telefono" class="form-label">Teléfono</label>
                                <input type="text" class="form-control" id="telefono" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Supervisor</label>
                                <div id="selectedParent" class="p-2">
                                    <span class="text-muted">Ninguno (raíz)</span>
                                </div>
                                <small class="text-muted">Seleccione un supervisor en el árbol</small>
                            </div>
                            
                            <div class="action-buttons d-flex flex-wrap gap-2">
                                <button type="submit" id="saveBtn" class="btn btn-primary flex-grow-1">
                                    <i class="fas fa-save me-1"></i> Guardar
                                </button>
                                <button type="button" id="cancelBtn" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i> Cancelar
                                </button>
                                <button type="button" id="deleteBtn" class="btn btn-danger" style="display: none;">
                                    <i class="fas fa-trash-alt me-1"></i> Eliminar
                                </button>
                            </div>
                            
                            <div id="statusMessage" class="status-message alert alert-dismissible fade show mt-3">
                                <span id="messageText"></span>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.12/jstree.min.js"></script>
    <script>
    $(function() {
        // Variables de estado
        var selectedNode = null;
        var selectedParent = null;
        var updatingTree = false;
        
        // Inicializar jsTree
        $('#ejecutivosTree').jstree({
            'core': {
                'data': {
                    'url': '?action=get_tree',
                    'dataType': 'json',
                    'cache': false
                },
                'check_callback': function (operation, node, parent, position, more) {
                    if (operation === 'move_node') {
                        if (node.id === parent.id) return false;
                        
                        var current = parent;
                        while (current) {
                            if (current.id === node.id) return false;
                            current = $('#ejecutivosTree').jstree('get_node', current.parent);
                        }
                        return true;
                    }
                    return true;
                },
                'themes': {
                    'responsive': true,
                    'dots': true,
                    'icons': true
                },
                'multiple': false
            },
            'plugins': ['dnd', 'wholerow', 'types'],
            'types': {
                'default': { 'icon': 'fas fa-user' }
            },
            'dnd': {
                'copy': false,
                'inside_pos': 'last',
                'is_draggable': function(node) {
                    return true;
                }
            }
        })
        .on('move_node.jstree', function(e, data) {
            $.post('?action=move_node', {
                id: data.node.id,
                parent: data.parent === '#' ? null : data.parent
            }, function(response) {
                if (!response.success) {
                    showMessage(response.error || 'Error al mover el nodo', 'danger');
                    $('#ejecutivosTree').jstree('refresh');
                }
            }, 'json');
        })
        .on('changed.jstree', function(e, data) {
            if (updatingTree || !data.selected.length) return;
            
            selectedNode = data.instance.get_node(data.selected[0]);
            loadEjecutivoDetails(selectedNode.id);
        });
        
        // Manejar selección de padre en modo creación
        $('#ejecutivosTree').on('select_node.jstree', function(e, data) {
            if ($('#ejecutivoId').val() == '0') {
                selectedParent = data.node;
                $('#selectedParent').html('<i class="fas fa-level-up-alt me-1"></i> ' + data.node.text);
            }
        });
        
        // Función para cargar detalles del ejecutivo
        function loadEjecutivoDetails(id) {
            $.get('?action=get_details&id=' + id, function(data) {
                if (data.success) {
                    updatingTree = true;
                    
                    $('#ejecutivoId').val(id);
                    $('#nombre').val(data.nombre);
                    $('#telefono').val(data.telefono);
                    $('#formTitle').html('<i class="fas fa-user-edit me-2"></i> Editar Ejecutivo');
                    $('#saveBtn').html('<i class="fas fa-save me-1"></i> Actualizar');
                    $('#deleteBtn').show();
                    
                    if (data.id_padre) {
                        var parentNode = $('#ejecutivosTree').jstree('get_node', data.id_padre);
                        if (parentNode) {
                            selectedParent = parentNode;
                            $('#selectedParent').html('<i class="fas fa-level-up-alt me-1"></i> ' + parentNode.text);
                        }
                    } else {
                        selectedParent = null;
                        $('#selectedParent').html('<span class="text-muted">Ninguno (raíz)</span>');
                    }
                    
                    updatingTree = false;
                }
            }, 'json').fail(function() {
                showMessage('Error al cargar detalles', 'danger');
            });
        }
        
        // Botón Actualizar
        $('#refresh').click(function() {
            refreshTree();
        });
        
        // Función para refrescar el árbol
        function refreshTree() {
            $('#ejecutivosTree').jstree('refresh');
            resetForm();
            showMessage('Árbol actualizado', 'success');
        }
        
        // Enviar formulario
        $('#ejecutivoForm').submit(function(e) {
            e.preventDefault();
            
            var id = $('#ejecutivoId').val();
            var nombre = $('#nombre').val().trim();
            var telefono = $('#telefono').val().trim();
            var padre = selectedParent ? selectedParent.id : null;
            
            if (!nombre || !telefono) {
                showMessage('Nombre y teléfono son obligatorios', 'danger');
                return;
            }
            
            var url = id == '0' ? '?action=create' : '?action=update';
            var data = {
                nombre: nombre,
                telefono: telefono
            };
            
            if (id == '0') {
                data.padre = padre;
            } else {
                data.id = id;
            }
            
            $.post(url, data, function(response) {
                if (response.success) {
                    refreshTree();
                    showMessage(id == '0' ? 'Ejecutivo creado correctamente' : 'Ejecutivo actualizado correctamente', 'success');
                } else {
                    showMessage(response.error || 'Error desconocido', 'danger');
                }
            }, 'json').fail(function() {
                showMessage('Error en la comunicación con el servidor', 'danger');
            });
        });
        
        // Botón Cancelar
        $('#cancelBtn').click(function() {
            resetForm();
        });
        
        // Botón Eliminar
        $('#deleteBtn').click(function() {
            var id = $('#ejecutivoId').val();
            if (id && id != '0') {
                if (confirm('¿Está seguro de eliminar este ejecutivo? Todos sus subordinados pasarán a depender de su superior.')) {
                    $.post('?action=delete', { id: id }, function(response) {
                        if (response.success) {
                            refreshTree();
                            showMessage('Ejecutivo eliminado correctamente. Los subordinados fueron reasignados.', 'success');
                        } else {
                            showMessage(response.error || 'Error al eliminar', 'danger');
                        }
                    }, 'json');
                }
            }
        });
        
        // Función para resetear el formulario
        function resetForm() {
            updatingTree = true;
            
            $('#ejecutivoId').val('0');
            $('#nombre').val('');
            $('#telefono').val('');
            $('#formTitle').html('<i class="fas fa-user-plus me-2"></i> Nuevo Ejecutivo');
            $('#saveBtn').html('<i class="fas fa-save me-1"></i> Guardar');
            $('#deleteBtn').hide();
            selectedNode = null;
            selectedParent = null;
            $('#selectedParent').html('<span class="text-muted">Ninguno (raíz)</span>');
            
            $('#ejecutivosTree').jstree('deselect_all', true);
            
            updatingTree = false;
        }
        
        // Función para mostrar mensajes de estado
        function showMessage(text, type) {
            var $msg = $('#statusMessage');
            $msg.removeClass('alert-success alert-danger alert-warning')
                .addClass('alert-' + type)
                .show();
            $('#messageText').text(text);
            setTimeout(function() {
                $msg.fadeOut();
            }, 5000);
        }
    });
    </script>
</body>
</html>