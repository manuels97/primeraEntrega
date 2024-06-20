<?php
namespace App\Controllers;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require_once __DIR__ . '/../../utilidades.php';


class PropiedadesController{
    private function existeID ($id,$tabla){
        $connection=getConnection();
        $query=$connection->query("SELECT id FROM $tabla WHERE id=$id");
        if($query->rowCount()>0) {
            return true;
        } else {
            return false;
        }
    }   
    //POST
    public function crearPropiedad(Request $request, Response $response) {
        $connection = getConnection();
        $data = $request->getParsedBody();
    
        // Validar campos requeridos

        $requiredFields = ['domicilio', 'localidad_id',  'cantidad_huespedes', 'fecha_inicio_disponibilidad', 'cantidad_dias', 'disponible', 'valor_noche',  'tipo_propiedad_id'];
        $payload=faltanDatos($requiredFields,$data);
        if (isset($payload)) {
            return responseWrite($response,$payload);
        }
        try {
            $campos = [
                'localidad_id' => 'localidades',
                'tipo_propiedad_id' => 'tipo_propiedades'
            ];
            $errores = [];
            foreach ($campos as $campo => $tabla) {
                if (!$this->existeID($data[$campo], $tabla)) {
                    $errores[] = "No existe el ID $campo ";
                }
            }
            if (!empty($errores)) {
                $status = 'Error';
                $mensaje = implode(", ", $errores);
                $payload = codeResponseGeneric($status, $mensaje, 400);
                return responseWrite($response, $payload);
            }
            // Insertar la nueva propiedad en la base de datos
            $query = $connection->prepare("INSERT INTO propiedades (domicilio, localidad_id, cantidad_habitaciones, cantidad_banios, cochera, cantidad_huespedes, fecha_inicio_disponibilidad, cantidad_dias, disponible, valor_noche, tipo_propiedad_id, imagen, tipo_imagen) VALUES (:domicilio, :localidad_id, :cantidad_habitaciones, :cantidad_banios, :cochera, :cantidad_huespedes, :fecha_inicio_disponibilidad, :cantidad_dias, :disponible, :valor_noche, :tipo_propiedad_id, :imagen, :tipo_imagen)");
    
            $valores = [
                ':domicilio' => $data['domicilio'],
                ':localidad_id' => $data['localidad_id'],
                ':cantidad_habitaciones' => $data['cantidad_habitaciones'] ?? null,
                ':cantidad_banios' => $data['cantidad_banios'] ?? null,
                ':cochera' => $data['cochera'] ?? null,
                ':cantidad_huespedes' => $data['cantidad_huespedes'],
                ':fecha_inicio_disponibilidad' => $data['fecha_inicio_disponibilidad'],
                ':cantidad_dias' => $data['cantidad_dias'],
                ':disponible' => $data['disponible'],
                ':valor_noche' => $data['valor_noche'],
                ':tipo_propiedad_id' => $data['tipo_propiedad_id'],
                ':imagen' => $data['imagen'] ?? null,
                ':tipo_imagen' => $data['tipo_imagen'] ?? null
            ];
            
            $query->execute($valores);
            // Obtener el ID de la nueva propiedad insertada
            $id = $connection->lastInsertId();
    
            $status = 'Success';
            $mensaje = 'Propiedad creada correctamente';
            $payload = ['id' => $id];
            $payload = codeResponseGeneric($status, $mensaje, 201);
            return responseWrite($response, $payload);
        } catch (\PDOException $e) {
            // Manejo de excepciones PDO
            $payload = codeResponseBad();
            return responseWrite($response, $payload);
        }
    }
    
    


    // GET

    public function listar(Request $request, Response $response) {
        $connection = getConnection();
        $params = $request->getQueryParams();
        // Construir la consulta SQL base sin ningun filtro
        $sql = "SELECT * FROM propiedades";
        // Aplicar filtros si se proporcionan
        $values = []; //aca guardamos los parametros si hay
        if (!empty($params)){
            $conditions = [];
            $condiciones=['disponible','localidad_id','fecha_inicio_disponibilidad','cantidad_huespedes'];
            foreach ($condiciones as $key) {
                if (isset($params[$key])) {
                    $conditions[] = "$key = :$key";
                    $values[":$key"] = $params[$key];
                }
            }
            // combinar Condiciones
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }
       
        try {
            // Ejecutar la consulta
            $query = $connection->prepare($sql);
            $query->execute($values);
            $propiedades = $query->fetchAll(\PDO::FETCH_ASSOC);
            // Respuesta exitosa con el listado de propiedades
            if(empty($propiedades)){
                $propiedades="No se encuentran propiedades";
            }
            $payload= codeResponseOk($propiedades);
            return responseWrite($response, $payload);
        } catch (\PDOException $e) {
            // Manejo de excepciones PDO
            $payload = codeResponseBad();
            return responseWrite($response, $payload);
        }
    }


    //GET POR ID
    public function listarPorId (Request $request, Response $response, $args) {
       
        // Obtiene la conexión a la base de datos
            
        $connection = getConnection();
        try {  
             $id = $args['id'];
             // Realiza la consulta SQL
             $query = $connection->query("SELECT * FROM propiedades WHERE id=$id");
             // Obtiene los resultados de la consulta
             $tipos = $query->fetchAll(\PDO::FETCH_ASSOC);
             if($tipos) {
                 $payload = codeResponseOk($tipos);
                return responseWrite($response, $payload);
            } else {
                $status='Error'; $mensaje='No se encontró ninguna propiead con el ID proporcionado.';
                $payload = codeResponseGeneric($status,$mensaje,400);
                return responseWrite($response, $payload);
            }
         } catch (\PDOException $e) {
                $payload= codeResponseBad();
                
                return responseWrite($response,$payload);
         }
     
    }
    
    //PUT

    public function editarPropiedad(Request $request, Response $response, $args) {
        $connection = getConnection();
        $id_url = $args['id']; 
        $data = $request->getParsedBody();
        // Validar ID numérico
        if(!is_numeric($id_url)) {
            $status = 'Error'; 
            $mensaje = 'ID no válido'; 
            $payload = codeResponseGeneric($status, $mensaje, 400);
            return responseWrite($response, $payload);
        }
    
        try {
            
            // Verificar si la propiedad existe
            $query = $connection->prepare("SELECT id FROM propiedades WHERE id=:id LIMIT 1");
            $query->bindParam(':id', $id_url, \PDO::PARAM_INT);
            $query->execute();
            if($query->rowCount() == 0) {
                $status = 'ERROR'; 
                $mensaje = 'No se encuentra el ID de la propiedad'; 
                $payload = codeResponseGeneric($status, $mensaje, 404);
                return responseWrite($response, $payload);
            }

    
            // Validar que los campos requeridos no esten vacios.
            $requiredFields = ['domicilio', 'localidad_id',  'cantidad_huespedes', 'fecha_inicio_disponibilidad', 'cantidad_dias', 'disponible', 'valor_noche',  'tipo_propiedad_id'];
            $payload=faltanDatos($requiredFields,$data);
            if (isset($payload)) {
                return responseWrite($response,$payload);
            }
            $campos = [
                'localidad_id' => 'localidades',
                'tipo_propiedad_id' => 'tipo_propiedades'
            ];
            $errores = [];
            foreach ($campos as $campo => $tabla) {
                if (!$this->existeID($data[$campo], $tabla)) {
                    $errores[] = "No existe el ID $campo";
                }
            }
            if (!empty($errores)) {
                $status = 'Error';
                $mensaje = implode(", ", $errores);
                $payload = codeResponseGeneric($status, $mensaje, 400);
                return responseWrite($response, $payload);
            }
            // Actualizar la propiedad
            $query = $connection->prepare('UPDATE propiedades SET
             domicilio=:domicilio,
             localidad_id=:localidad_id,
             cantidad_habitaciones=:cantidad_habitaciones,
             cantidad_banios=:cantidad_banios,
             cochera=:cochera,
             cantidad_huespedes=:cantidad_huespedes,
             fecha_inicio_disponibilidad=:fecha_inicio_disponibilidad,
             cantidad_dias=:cantidad_dias,
             disponible=:disponible,
             valor_noche=:valor_noche,
             tipo_propiedad_id=:tipo_propiedad_id,
             imagen=:imagen,
             tipo_imagen=:tipo_imagen 
             WHERE id=:id');
            $valores = [
                ':domicilio' => $data['domicilio'],
                ':localidad_id' => $data['localidad_id'],
                ':cantidad_habitaciones' => $data['cantidad_habitaciones'] ?? null,
                ':cantidad_banios' => $data['cantidad_banios'] ?? null,
                ':cochera' => $data['cochera'] ?? null,
                ':cantidad_huespedes' => $data['cantidad_huespedes'],
                ':fecha_inicio_disponibilidad' => $data['fecha_inicio_disponibilidad'],
                ':cantidad_dias' => $data['cantidad_dias'],
                ':disponible' => $data['disponible'],
                ':valor_noche' => $data['valor_noche'],
                ':tipo_propiedad_id' => $data['tipo_propiedad_id'],
                ':imagen' => $data['imagen'] ?? null,
                ':tipo_imagen' => $data['tipo_imagen'] ?? null,
                ':id' => $id_url
            ];
            $query->execute($valores);
            // Respuesta de éxito
            $status = 'Success';
            $mensaje = 'Propiedad editada correctamente';
            $payload = codeResponseGeneric($status, $mensaje, 200);
            return responseWrite($response, $payload);
        } catch (\PDOException $e) {
            // Manejo de excepciones PDO
            $payload = codeResponseBad();
            return responseWrite($response, $payload);
        }
    }
    
    //DELETE
    public function eliminarPropiedad(Request $request, Response $response, $args) {
        $connection = getConnection();
        $id_url = $args['id']; 
        // Validar ID numérico
        if(!is_numeric($id_url)) {
            $status = 'Error'; 
            $mensaje = 'ID no válido'; 
            $payload = codeResponseGeneric($status, $mensaje, 400);
            return responseWrite($response, $payload);
        }
    
        try {
            // Verificar si la propiedad existe
            $query = $connection->prepare("SELECT id FROM propiedades WHERE id=:id LIMIT 1");
            $query->bindParam(':id', $id_url, \PDO::PARAM_INT);
            $query->execute();
            if($query->rowCount() == 0) {
                $status = 'ERROR'; 
                $mensaje = 'No se encuentra el ID de la propiedad'; 
                $payload = codeResponseGeneric($status, $mensaje, 404);
                return responseWrite($response, $payload);
            }
    
            // Eliminar la propiedad
            $query = $connection->prepare("DELETE FROM propiedades WHERE id=:id");
            $query->bindParam(':id', $id_url, \PDO::PARAM_INT);
            $query->execute();
    
            // Respuesta de éxito
            $status = 'Success';
            $mensaje = 'Propiedad eliminada correctamente';
            $payload = codeResponseGeneric($status, $mensaje, 200);
            return responseWrite($response, $payload);
        } catch (\PDOException $e) {
            // Manejo de excepciones PDO
            $status = 'Error';
            $mensaje = 'No se pudo eliminar la propiedad.';
            $payload = codeResponseGeneric($status,$mensaje,409);
            return responseWrite($response, $payload)->withStatus(409);            ;
        }
    }
    
}