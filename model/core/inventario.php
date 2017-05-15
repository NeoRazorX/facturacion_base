<?php

/*
 * Copyright (C) 2017 Joe Nilson <joenilson at gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\model;

require_model('articulo.php');
require_model('almacen.php');
require_model('empresa.php');
require_model('divisa.php');
require_model('stock.php');
/**
 * Esta tabla guardará el calculo de inventario diario de cada artículo
 * para así poder presentar la información de los ultimos 30 días de stock en
 * ventas_articulo y no recargar el view de datos
 * Para informe_articulos servirá para mostrar graficos de resumen por fecha
 * sin tener que estar calculando directamente de las tablas involucradas
 * @author Joe Nilson <joenilson at gmail.com>
 */
class inventario extends \fs_model
{
   /**
    * Codigo del almacén de inventario
    * @var type varchar(4)
    */
   public $codalmacen;
   /**
    * Fecha del inventario
    * @var type date (YYYY-dd-mm)
    */
   public $fecha;
   /**
    * codigo del artículo
    * @var type varchar(18)
    */
   public $referencia;
   /**
    * Descripción del artículo
    * @var type TEXT
    */
   public $descripcion;
   /**
    * Cantidad de ingreso de un artículo
    * para un almacén y fecha determinados
    * @var type float
    */
   public $cantidad_ingreso;
   /**
    * Cantidad de salida de un artículo
    * para un almacén y fecha determinados
    * @var type float
    */
   public $cantidad_salida;
   /**
    * Cantidad final de saldo de un artículo
    * para un almacén y fecha determinados
    * @var type float
    */
   public $cantidad_saldo;
   /**
    * Valorización en base al precio de coste
    * de un artículo
    * @var type float
    */
   public $monto_ingreso;
   /**
    * Valorización en base al precio de coste
    * de un artículo
    * @var type float
    */
   public $monto_salida;
   /**
    * Valorización en base al precio de coste
    * de un artículo
    * @var type float
    */
   public $monto_saldo;


   /**
    * Variable auxiliar interna
    * Fecha de inicio del proceso de calculo o revisión
    * del inventario
    * @var type date YYYY-mm-dd
    */
   public $fecha_inicio;
   /**
    * Variable auxliar interna
    * Fecha de fin del proceso de calculo o revisión
    * del inventario
    * @var type date YYYY-mm-dd
    */
   public $fecha_fin;
   /**
    * Variable auxiliar interna
    * Fecha de proceso actual cuando se este
    * haciendo el calculo o revisión del inventario
    * @var type date YYYY-mm-dd
    */
   public $fecha_proceso;
   /**
    * Variable para instanciar la clase articulo();
    * @var type object new articulo();
    */
   public $articulo;
   /**
    * Variable para instanciar la clase almacen();
    * @var type object new almacen();
    */
   public $almacen;
   /**
    * Variable para guardar un listado de articulos
    * @var type Array
    */
   public $articulos;
   /**
    * Variable para guardar un listado de almacenes
    * @var type Array
    */
   public $almacenes;
   /**
    * Variable para instanciar la clase empresa();
    * Esta es necesaria para convertir los precios de
    * los productos de monedas extranjeras a la moneda
    * configurada por defecto en la empresa
    * @var type object new empresa();
    */
   public $empresa;
   /**
    * Variable de configuración para el inventario
    * @var type array
    */
   public $inventario_setup;
   /**
    * Variable para saber si se está ejecutando el cron
    * del inventario en forma automática
    * @var type boolean
    */
   public $cron;
   /**
    * Variable para poder obtener el listado de tablas
    * del sistema
    * @var type array
    */
   public $tablas;
   public $stock;
   public function __construct($t = '')
   {
      parent::__construct('inventario');
      if ($t)
      {
         $this->codalmacen = $t['codalmacen'];
         $this->fecha = $t['fecha'];
         $this->referencia = $t['referencia'];
         $this->descripcion = $t['descripcion'];
         $this->cantidad_ingreso = floatval($t['cantidad_ingreso']);
         $this->cantidad_salida = floatval($t['cantidad_salida']);
         $this->cantidad_saldo = floatval($t['cantidad_saldo']);
         $this->monto_ingreso = floatval($t['monto_ingreso']);
         $this->monto_salida = floatval($t['monto_salida']);
         $this->monto_saldo = floatval($t['monto_saldo']);
      }
      else
      {
         $this->codalmacen = NULL;
         $this->fecha = NULL;
         $this->referencia = NULL;
         $this->descripcion = NULL;
         $this->cantidad_ingreso = 0;
         $this->cantidad_salida = 0;
         $this->cantidad_saldo = 0;
         $this->monto_ingreso = 0;
         $this->monto_salida = 0;
         $this->monto_saldo = 0;
      }
      $this->fecha_inicio = NULL;
      $this->fecha_fin = NULL;
      $this->fecha_proceso = NULL;
      $this->articulo = new \articulo();
      $this->almacen = new \almacen();
      $this->empresa = new \empresa();
      $this->stock = new \stock();
      $this->cron = false;
      $this->tablas = $this->db->list_tables();
      $this->almacenes = false;

   }

   protected function install()
   {
      /**
       * Al momento de instalar el modelo agregamos a fs_vars
       * las variables para control de calculo del inventario
       */
      $fsvar = new \fs_var();
      $config = $fsvar->array_get(array(
         'inventario_ultimo_proceso' => '',
         'inventario_procesandose' => 'FALSE',
         'inventario_usuario_procesando' => '',
         'inventario_cron' => '',
         'inventario_programado' => ''
      ));
      $fsvar->array_save($config);
   }

   /**
    * Verificamos que exista información en una fecha, almacén y artículo determinado
    * para de acuerdo al resultado hacer un UPDATE o un INSERT
    * @return boolean
    */
   public function exists()
   {
      $sql = "SELECT * FROM " . $this->table_name . " WHERE "
              . " codalmacen = " . $this->var2str($this->codalmacen)
              . " AND fecha = " . $this->var2str($this->fecha)
              . " AND referencia = " . $this->var2str($this->referencia) . ";";
      return $this->db->select($sql);
   }

   /**
    * guardamos la información del inventario para determinada fecha, almacén y artículo
    * @return type boolean
    */
   public function save()
   {
      if ($this->exists())
      {
         $sql = "UPDATE " . $this->table_name . " SET "
            . " cantidad_ingreso = " . $this->var2str($this->cantidad_ingreso)
            . ", cantidad_salida = " . $this->var2str($this->cantidad_salida)
            . ", cantidad_saldo = " . $this->var2str($this->cantidad_saldo)
            . ", monto_ingreso = " . $this->var2str($this->monto_ingreso)
            . ", monto_salida = " . $this->var2str($this->monto_salida)
            . ", monto_saldo = " . $this->var2str($this->monto_saldo)
            . "  WHERE "
            . " fecha = " . $this->var2str($this->fecha)
            . " and referencia = " . $this->var2str($this->referencia)
            . " and codalmacen = " . $this->var2str($this->codalmacen) . ";";
      }
      else
      {
         $sql = "INSERT INTO " . $this->table_name . " (codalmacen,fecha,referencia,descripcion,
            cantidad_ingreso,cantidad_salida,cantidad_saldo,monto_ingreso,monto_salida,monto_saldo) VALUES
               (" . $this->var2str($this->codalmacen)
             . "," . $this->var2str($this->fecha)
             . "," . $this->var2str($this->referencia)
             . "," . $this->var2str($this->descripcion)
             . "," . $this->var2str($this->cantidad_ingreso)
             . "," . $this->var2str($this->cantidad_salida)
             . "," . $this->var2str($this->cantidad_saldo)
             . "," . $this->var2str($this->monto_ingreso)
             . "," . $this->var2str($this->monto_salida)
             . "," . $this->var2str($this->monto_saldo) . ");";
      }
      return $this->db->exec($sql);
   }

   /**
    * Eliminar parte de un inventario, no es necesario
    * @return string
    */
   public function delete()
   {
      return false;
   }

   /*
    * Este cron generará los saldos de almacen por día
    * Para que cuando soliciten el movimiento por almacen por
    * articulo se pueda extraer de aquí en forma de resumen histórico
    */
   public function cron_job()
   {
      $fsvar = new \fs_var();
      $this->inventario_setup = $fsvar->array_get(
         array(
            'inventario_ultimo_proceso' => $this->fecha_proceso,
            'inventario_procesandose' => 'FALSE',
            'inventario_usuario_procesando' => 'cron',
            'inventario_cron' => '',
            'inventario_programado' => ''
         ), FALSE
      );
      if ($this->inventario_setup['inventario_procesandose'] !== 'TRUE')
      {
         if ($this->inventario_setup['inventario_cron'] == 'TRUE')
         {
            echo " * Se encontro un job para procesar\n";
            $ahora = new \DateTime('NOW');
            $horaActual = strtotime($ahora->format('H') . ':00:00');
            $horaProgramada = strtotime($this->inventario_setup['inventario_programado'] . ':00:00');
            if ($horaActual == $horaProgramada)
            {
               echo " ** Se confirma calculo de Inventario Diario\n";
               $this->cron = true;
               $this->procesar_inventario();
            }
            else
            {
               echo " ** No coincide la hora de proceso con la de ejecucion de cron se omite el calculo\n";
            }
         }
      }
   }

   /*
    * Actualizamos la información del Inventario con las fechas de inicio y fin
    * Verificamos siempre que no haya un usuario ejecutando el cron para no crear
    * bloqueos en la base de datos
    */
   public function procesar_inventario($usuario = NULL)
   {

      $fsvar = new \fs_var();
      $this->inventario_setup = $fsvar->array_get(
        array(
            'inventario_ultimo_proceso' => $this->fecha_proceso,
            'inventario_procesandose' => 'FALSE',
            'inventario_usuario_procesando' => 'cron'
        ), FALSE
      );

      /**
       * Verificamos si no hay un proceso ejecutandose
       */
      if ($this->inventario_setup['inventario_procesandose'] == 'TRUE' AND ( $this->cron))
      {
         echo " ** Hay otro proceso calculando el Inventario se cancela el proceso cron...\n";
         return false;
      }

      /**
       * Si no se ha inicializado la variable de fecha de inicio para el calculo de Inventario
       * entonces buscamos la ultima fecha ejecutada o la fecha más antigua en su defecto
       */
      if (is_null($this->fecha_inicio))
      {
         $this->ultima_fecha();
      }

      /**
       * Una vez con el rango de días generamos el intervalo para saber
       * la cantidad de días que vamos a procesar el inventario
       */
      $intervalo = date_diff(date_create($this->fecha_inicio), date_create($this->fecha_fin));
      $dias_proceso = $intervalo->format('%a') + 1;
      $rango = $this->rango_fechas();

      /**
       * Si no se va ejecutar por cron
       * inicializamos el object collector
       * para poder mostrar en pantalla
       * como avanza el calculo del inventario
       */
      if (!$this->cron)
      {
         ob_implicit_flush(true);
         ob_end_flush();
      }

      //Iniciamos el control del inventario
      $inicio_total = new \DateTime('NOW');
      //contador de días procesados
      $contador = 0;
      //Iteramos el rango de fechas
      foreach ($rango as $fecha)
      {
         $inicio_paso = new \DateTime('NOW');
         sleep(1);
         $plural = ($contador == 0) ? "" : "s";
         $p = ceil((($contador + 1) * 100) / $dias_proceso);
         $this->fecha_proceso = $fecha->format('Y-m-d');
         //Bloqueamos el intento de procesar el Inventario por varios usuarios al mismo tiempo
         $fsvar->array_save(
            array(
               'inventario_ultimo_proceso' => $this->fecha_proceso,
               'inventario_procesandose' => 'TRUE',
               'inventario_usuario_procesando' => ($usuario) ? $usuario : 'cron'
            )
         );
         /**
          * Iniciamos el calculo del inventario
          */
         $this->calcular_inventario();
         /**
          * Si no es el cron que ejecuta el inventario mandamos la cadena json de avance del mismo
          */
         if (!$this->cron)
         {
            $fin_paso = $inicio_paso->diff(new \DateTime('NOW'));
            $tiempo_proceso = $inicio_total->diff(new \DateTime('NOW'));
            $time = $fin_paso->h . ':' . $fin_paso->i . ':' . $fin_paso->s;
            $tiempo_en_segundos = strtotime("1970-01-01 $time UTC");
            $tiempo_estimado = gmdate("H:i:s", ($dias_proceso * $tiempo_en_segundos));
            $response = array('message' => 'Calculando inventario <b>' . $fecha->format("Y-m-d") . '</b>, ' . ($contador + 1) . ' dia' . $plural ." de ".' <b>' . $dias_proceso . '</b> procesando' . $plural . ' en ' . $tiempo_proceso->format('%H:%I:%S') . ', a las: ' . date("h:i:s", time()) .', tiempo estimado: <b>' . $tiempo_estimado . '</b>',
               'progress' => $p);
            echo json_encode($response);
         }
         $contador++;
      }
      sleep(1);
      /**
       * cuando termina de procesarse el inventario
       * guardamos la ultima ejecución y limpiamos las variables
       * de proceso y si no es cron el que ejecuta el calculo
       * mandamos la cadeja json de termino
       */
      $fsvar->array_save(array(
         'inventario_ultimo_proceso' => $this->fecha_proceso,
         'inventario_procesandose' => 'FALSE',
         'inventario_usuario_procesando' => ''
      ));
      if (!$this->cron)
      {
         $response = array('message' => '<b>¡Calculo de Inventario completo en '.$tiempo_proceso->format('%H:%I:%S') . '!<b>',
            'progress' => 100);
         echo json_encode($response);
      }
      else
      {
         echo " ** Proceso de Inventario diario concluido...\n";
      }
   }

   /*
    * Calculamos la información por cada almacén activo
    */
   public function calcular_inventario()
   {
      //Confirmamos que haya una fecha de proceso
      if ($this->fecha_proceso)
      {
         foreach ($this->almacenes as $almacen)
         {
            //buscamos los movimientos por almacén
            $this->calcular_movimientos($almacen);
         }
         gc_collect_cycles();
      }
   }

   /**
    * Listamos los movimientos entre 2 fechas y un almacén
    * @param type $desde date
    * @param type $hasta date
    * @param type $codalmacen string
    * @return array
    */
   public function listar_movimientos($desde, $hasta, $codalmacen='')
   {
      $lista = array();
      $desde = (!$desde)?\date('01-m-Y'):$desde;
      $hasta = (!$hasta)?\date('d-m-Y'):$hasta;
      $sql_almacen = '';
      if($codalmacen)
      {
         $sql_almacen = " AND codalmacen = ".$this->var2str($codalmacen);
      }
      $sql = "SELECT * FROM ".$this->table_name.
            " WHERE fecha between ".$this->var2str($desde). " AND ".$this->var2str($hasta).$sql_almacen.
              " ORDER BY fecha";
      $data = $this->db->select($sql);
      if($data){
         foreach($data as $d)
         {
            $lista[] = new inventario($d);
         }
      }
      return $lista;
   }

   /*
    * Esta es la consulta multiple que utilizamos para sacar la información
    * de todos los articulos
    */
   public function calcular_movimientos($almacen)
   {
      //Generamos el select para la subconsulta de productos activos y que se controla su stock
      $productos = "SELECT referencia, descripcion, costemedio FROM articulos where bloqueado = false and nostock = false;";
      $lista_productos = $this->db->select($productos);
      if ($lista_productos)
      {
         foreach ($lista_productos as $item)
         {
            //Inicializamos la clase de inventario para guardar los resultados del calculo
            $inventario = new inventario();
            $inventario->codalmacen = $almacen->codalmacen;
            $inventario->fecha = $this->fecha_proceso;
            $inventario->referencia = $item['referencia'];
            $inventario->descripcion = stripcslashes($item['descripcion']);
            $inventario->cantidad_salida = 0;
            $inventario->cantidad_ingreso = 0;
            $inventario->cantidad_saldo = 0;
            $inventario->monto_salida = 0;
            $inventario->monto_ingreso = 0;
            $inventario->monto_saldo = 0;

            /*
             * Generamos la informacion del saldo final del dia anterior segun el inventario diario
             */
            $fechaProceso = new \DateTime($this->fecha_proceso);
            $fechaAnterior = $fechaProceso->sub(new \DateInterval('P1D'))->format('Y-m-d');
            $sql_inventario = "select referencia, descripcion, cantidad_saldo , monto_saldo ".
                     " FROM inventario ".
                     " WHERE codalmacen = ".$this->var2str($almacen->codalmacen)." AND fecha = ".$this->var2str($fechaAnterior).
                     " AND referencia = " .$this->var2str($item['referencia']).";";
            $saldo_anterior = $this->db->select($sql_inventario);
            if ($saldo_anterior)
            {
                  $inventario->cantidad_saldo = $saldo_anterior[0]['cantidad_saldo'];
                  $inventario->monto_saldo = $saldo_anterior[0]['monto_saldo'];
            }

            /*
             * Generamos la informacion de los albaranes de proveedor asociados a facturas no anuladas
             */
            $sql_albaranesprov = "select referencia,coddivisa,tasaconv,sum(cantidad) as cantidad, sum(pvptotal) as monto ".
               " from albaranesprov as ac ".
               " join lineasalbaranesprov as l ON (ac.idalbaran=l.idalbaran) ".
               " where codalmacen = ".$this->var2str($almacen->codalmacen)." AND fecha = " .$this->var2str($this->fecha_proceso).
               " AND idfactura is not null ".
               " AND referencia = ".$this->var2str($item['referencia']).
               " group by l.referencia,coddivisa,tasaconv ".
               " order by coddivisa;";
            $data_albaranesprov = $this->db->select($sql_albaranesprov);
            if ($data_albaranesprov) {
               foreach ($data_albaranesprov as $linea) {
                  //Verificamos la divisa del monto
                  $linea['monto'] = $this->verificar_divisa($linea['monto'], $linea['coddivisa']);
                  $inventario->cantidad_salida += ($linea['cantidad'] <= 0) ? ($linea['cantidad'] * -1) : 0;
                  $inventario->cantidad_ingreso += ($linea['cantidad'] >= 0) ? $linea['cantidad'] : 0;
                  $inventario->monto_salida += ($linea['monto'] <= 0) ? ($linea['monto'] * -1) : 0;
                  $inventario->monto_ingreso += ($linea['monto'] >= 0) ? $linea['monto'] : 0;
               }
            }

            /*
             * Generamos la informacion de las facturas de proveedor ingresadas
             * que no esten asociadas a un albaran de proveedor
             */
            $sql_facturasprov = "select referencia,coddivisa,tasaconv,sum(cantidad) as cantidad, sum(pvptotal) as monto ".
               " FROM facturasprov as fc ".
               " JOIN lineasfacturasprov as l ON (fc.idfactura=l.idfactura) ".
               " WHERE codalmacen = ".$this->var2str($almacen->codalmacen)." AND fecha = " .$this->var2str($this->fecha_proceso).
               " and anulada=FALSE and idalbaran is null ".
               " AND referencia = ".$this->var2str($item['referencia']).
               " group by referencia,coddivisa,tasaconv ".
               " order by coddivisa;";
            $data_facturasprov = $this->db->select($sql_facturasprov);
            if ($data_facturasprov) {
               foreach ($data_facturasprov as $linea) {
                  //Verificamos la divisa del monto
                  $linea['monto'] = $this->verificar_divisa($linea['monto'], $linea['coddivisa']);
                  $inventario->cantidad_salida += ($linea['cantidad'] <= 0) ? ($linea['cantidad'] * -1) : 0;
                  $inventario->cantidad_ingreso += ($linea['cantidad'] >= 0) ? $linea['cantidad'] : 0;
                  $inventario->monto_salida += ($linea['monto'] <= 0) ? ($linea['monto'] * -1) : 0;
                  $inventario->monto_ingreso += ($linea['monto'] >= 0) ? $linea['monto'] : 0;
               }
            }

            /*
             * Generamos la informacion de los albaranes asociados a facturas no anuladas
             */
            $sql_albaranescli = "select referencia,coddivisa,tasaconv,sum(cantidad) as cantidad, sum(pvptotal) as monto ".
               " from albaranescli as ac ".
               " join lineasalbaranescli as l ON (ac.idalbaran=l.idalbaran) ".
               " WHERE codalmacen = ".$this->var2str($almacen->codalmacen)." AND fecha = " .$this->var2str($this->fecha_proceso).
               " and idfactura is not null ".
               " AND referencia = ".$this->var2str($item['referencia']).
               " group by referencia,coddivisa,tasaconv ".
               " order by coddivisa;";
            $data_albaranescli = $this->db->select($sql_albaranescli);
            if ($data_albaranescli) {
               foreach ($data_albaranescli as $linea) {
                  //Verificamos la divisa del monto
                  $linea['monto'] = $this->verificar_divisa($linea['monto'], $linea['coddivisa']);
                  $inventario->cantidad_salida += ($linea['cantidad'] >= 0) ? $linea['cantidad'] : 0;
                  $inventario->cantidad_ingreso += ($linea['cantidad'] <= 0) ? ($linea['cantidad'] * -1) : 0;
                  $inventario->monto_salida += ($linea['monto'] >= 0) ? $linea['monto'] : 0;
                  $inventario->monto_ingreso += ($linea['monto'] <= 0) ? ($linea['monto'] * -1) : 0;
               }
            }

            /*
             * Generamos la informacion de los albaranes no asociados a facturas
             */
            $sql_albaranescli2 = "select referencia,coddivisa,tasaconv,sum(cantidad) as cantidad, sum(pvptotal) as monto ".
               " from albaranescli as ac ".
               " join lineasalbaranescli as l ON (ac.idalbaran=l.idalbaran) ".
               " WHERE codalmacen = ".$this->var2str($almacen->codalmacen)." AND fecha = " .$this->var2str($this->fecha_proceso).
               " and idfactura is null ".
               " AND referencia = ".$this->var2str($item['referencia']).
               " group by referencia,coddivisa,tasaconv ".
               " order by coddivisa;";
            $data_albaranescli2 = $this->db->select($sql_albaranescli2);
            if ($data_albaranescli2) {
               foreach ($data_albaranescli2 as $linea) {
                  //Verificamos la divisa del monto
                  $linea['monto'] = $this->verificar_divisa($linea['monto'], $linea['coddivisa']);
                  $inventario->cantidad_salida += ($linea['cantidad'] >= 0) ? $linea['cantidad'] : 0;
                  $inventario->cantidad_ingreso += ($linea['cantidad'] <= 0) ? ($linea['cantidad'] * -1) : 0;
                  $inventario->monto_salida += ($linea['monto'] >= 0) ? $linea['monto'] : 0;
                  $inventario->monto_ingreso += ($linea['monto'] <= 0) ? ($linea['monto'] * -1) : 0;
               }
            }

            /*
             * Generamos la informacion de las facturas que se han generado sin albaran
             */
            $sql_facturascli = "select referencia,coddivisa,tasaconv ,sum(cantidad) as cantidad, sum(pvptotal) as monto ".
                " from facturascli as fc ".
                " join lineasfacturascli as l ON (fc.idfactura=l.idfactura) ".
                " WHERE codalmacen = ".$this->var2str($almacen->codalmacen)." AND fecha = ".$this->var2str($this->fecha_proceso).
                " and anulada=FALSE and idalbaran is null ".
                " AND referencia = ".$this->var2str($item['referencia']).
                " group by fc.idfactura,referencia,coddivisa,tasaconv ".
                " order by fc.idfactura;";
            $data_facturascli = $this->db->select($sql_facturascli);
            if ($data_facturascli) {
               foreach ($data_facturascli as $linea) {
                  //Verificamos la divisa del monto
                  $linea['monto'] = $this->verificar_divisa($linea['monto'], $linea['coddivisa']);
                  $inventario->cantidad_salida += ($linea['cantidad'] >= 0) ? $linea['cantidad'] : 0;
                  $inventario->cantidad_ingreso += ($linea['cantidad'] <= 0) ? ($linea['cantidad'] * -1) : 0;
                  $inventario->monto_salida += ($linea['monto'] >= 0) ? $linea['monto'] : 0;
                  $inventario->monto_ingreso += ($linea['monto'] <= 0) ? ($linea['monto'] * -1) : 0;
               }
            }

            //Si existen estas tablas se genera la información de las transferencias de stock
            if( $this->db->table_exists('transstock', $this->tablas) AND $this->db->table_exists('lineastransstock', $this->tablas) )
            {
                /*
                 * Generamos la informacion de las transferencias por ingresos entre almacenes que se hayan hecho a los stocks
                 */
                $sql_regstocks_ingreso = "select referencia, sum(cantidad) as cantidad ".
                  " from lineastransstock AS ls ".
                  " JOIN transstock as l ON(ls.idtrans = l.idtrans) ".
                  " where codalmadestino = ".$this->var2str($almacen->codalmacen)." AND fecha = ".$this->var2str($this->fecha_proceso).
                  " AND referencia = ".$this->var2str($item['referencia']).
                  " group by referencia;";
                $data_regstocks_ingreso = $this->db->select($sql_regstocks_ingreso);
                if ($data_regstocks_ingreso) {
                   foreach ($data_regstocks_ingreso as $linea) {
                     $inventario->cantidad_salida += 0;
                     $inventario->cantidad_ingreso += ($linea['cantidad'] >= 0) ? $linea['cantidad'] : 0;
                     $inventario->monto_salida += 0;
                     $inventario->monto_ingreso += ($linea['cantidad'] >= 0) ? ($item['costemedio'] * $linea['cantidad']) : 0;
                   }
                }

                /*
                 * Generamos la informacion de las transferencias por salidas entre almacenes que se hayan hecho a los stocks
                 */
                $sql_regstocks_salida = "select referencia, sum(cantidad) as cantidad ".
                  " from lineastransstock AS ls ".
                  " JOIN transstock as l ON(ls.idtrans = l.idtrans) ".
                  " where codalmaorigen = ".$this->var2str($almacen->codalmacen)." AND fecha = ".$this->var2str($this->fecha_proceso).
                  " AND referencia = ".$this->var2str($item['referencia']).
                  " group by referencia;";
                $data_regstocks_salida = $this->db->select($sql_regstocks_salida);
                if ($data_regstocks_salida) {
                   foreach ($data_regstocks_salida as $linea) {
                     $inventario->cantidad_salida += ($linea['cantidad'] >= 0) ? $linea['cantidad'] : 0;
                     $inventario->cantidad_ingreso += 0;
                     $inventario->monto_salida += ($linea['cantidad'] >= 0) ? ($item['costemedio'] * $linea['cantidad']) : 0;
                     $inventario->monto_ingreso += 0;
                   }
                }
            }

            /*
             * Por ultimo generamos la informacion de las regularizaciones que se hayan hecho a los stocks
             */
            $sql_regularizaciones = "select referencia,sum(cantidadfin) as cantidad ".
            " from lineasregstocks AS ls ".
            " JOIN stocks as l ON(ls.idstock = l.idstock) ".
            " where codalmacen = ".$this->var2str($almacen->codalmacen)." AND fecha = ".$this->var2str($this->fecha_proceso).
            " AND referencia = ".$this->var2str($item['referencia']).
            " group by referencia;";
            $data_regularizaciones = $this->db->select($sql_regularizaciones);
            if ($data_regularizaciones) {
               foreach ($data_regularizaciones as $linea) {
                  //Verificamos la cantidad de la regularización
                  //para asignarle una cantidad de movimiento de regularización
                  $cantidad = $inventario->cantidad_saldo-$linea['cantidad'];
                  $inventario->cantidad_salida = ($cantidad > 0)?($cantidad):0;
                  $inventario->cantidad_ingreso = ($cantidad < 0)?$cantidad*-1:0;
                  $monto = $inventario->monto_saldo-($item['costemedio'] * $linea['cantidad']);
                  $inventario->monto_salida = ($monto > 0)?$monto:0;
                  $inventario->monto_ingreso = ($monto < 0)?$monto*-1:0;

               }
            }

            //Calculamos el valor del saldo
            $inventario->cantidad_saldo = ($inventario->cantidad_saldo + $inventario->cantidad_ingreso) - $inventario->cantidad_salida;
            $inventario->monto_saldo = ($inventario->monto_saldo + $inventario->monto_ingreso) - $inventario->monto_salida;

            //Guardarmos el resultado si es diferente de cero
            if($inventario->cantidad_saldo)
            {
               $inventario->save();
               //Si la fecha de proceso es ya la de hoy, guardamos en el stock del almacén el valor del inventario
               if($this->verificar_dia())
               {
                  $stock_referencia = $this->stock->get_by_almacen($item['referencia'], $almacen->codalmacen);
                  if($stock_referencia)
                  {
                     $stock_referencia->cantidad = $inventario->cantidad_saldo;
                     $stock_referencia->disponible = $inventario->cantidad_saldo;
                     $stock_referencia->save();
                  }
                  else
                  {
                     $stock_ref = new \stock();
                     $stock_ref->referencia = $item['referencia'];
                     $stock_ref->codalmacen = $almacen->codalmacen;
                     $stock_ref->cantidad = $inventario->cantidad_saldo;
                     $stock_ref->disponible = $inventario->cantidad_saldo;
                     $stock_ref->save();
                  }
               }

            }
            gc_collect_cycles();
         }
      }
   }

   public function verificar_dia()
   {
      $ok = false;
      //Si la fecha de proceso es ya la de hoy, guardamos en el stock del almacén el valor del inventario
      $fecha1 = new \DateTime($this->fecha_proceso);
      $fecha2 = new \DateTime('NOW');
      if($fecha1->format('Y-m-d') == $fecha2->format('Y-m-d'))
      {
         $ok = true;
      }
      return $ok;
   }

   /**
    * Ultima vez que se proceso el Inventario
    * @return boolean
    */
   public function ultimo_proceso() {
      $sql = "SELECT max(fecha) as fecha FROM " . $this->table_name . ";";
      $data = $this->db->select($sql);
      if ($data) {
         return $data[0]['fecha'];
      } else {
         return FALSE;
      }
   }

   /*
    * Buscamos la fecha del ultimo ingreso en el Inventario
    */
   public function ultima_fecha() {
      // Buscamos el registro más antiguo en la tabla de inventario
      $min_fecha = $this->db->select("SELECT max(fecha) as fecha FROM ".$this->table_name.";");
      // Si hay data, continuamos desde la siguiente fecha
      if ($min_fecha[0]['fecha']) {
         $min_fecha_inicio0 = new \DateTime($min_fecha[0]['fecha']);
         $min_fecha_inicio1 = $min_fecha_inicio0->modify('+1 day');
         $min_fecha_inicio = $min_fecha_inicio1->format('Y-m-d');
      }
      //Si no hay nada tenemos que ejecutar un proceso para todas las fechas desde el registro más antiguo
      else {
         $select = "SELECT min(fecha) as fecha FROM ( ".
            " SELECT min(fecha) AS fecha FROM lineasregstocks ".
            " UNION ".
            " SELECT min(fecha) AS fecha FROM albaranesprov ".
            " UNION ".
            " SELECT min(fecha) AS fecha FROM albaranescli ".
            " UNION ".
            " SELECT min(fecha) AS fecha FROM facturasprov ".
            " UNION ".
            " SELECT min(fecha) AS fecha FROM facturascli ".
            " ) AS t1;";
         $min_fecha = $this->db->select($select);
         $min_fecha_inicio0 = new \DateTime($min_fecha[0]['fecha']);
         $min_fecha_inicio = $min_fecha_inicio0->format('Y-m-d');
      }
      $this->fecha_inicio = $min_fecha_inicio;
   }

   /**
    * Verificamos si la divisa del item es igual al de la empresa
    * si no lo es, hacemos las tareas de conversión de valores
    * @param type $monto float
    * @param type $divisa string
    * @return type float
    */
   private function verificar_divisa($monto,$divisa){
      if(($divisa!=$this->empresa->coddivisa))
      {
         $monto = $this->euro_convert($this->divisa_convert($monto, $divisa, 'EUR'));
      }
      return $monto;
   }

   public function euro_convert($precio, $coddivisa = NULL, $tasaconv = NULL)
   {
      if($this->empresa->coddivisa == 'EUR')
      {
         return $precio;
      }
      else if($coddivisa AND $tasaconv)
      {
         if($this->empresa->coddivisa == $coddivisa)
         {
            return $precio * $tasaconv;
         }
         else
         {
            $original = $precio * $tasaconv;
            return $this->divisa_convert($original, $coddivisa, $this->empresa->coddivisa);
         }
      }
      else
      {
         return $this->divisa_convert($precio, 'EUR', $this->empresa->coddivisa);
      }
   }

   /**
    * Convierte un precio de la divisa_desde a la divisa especificada
    * @param type $precio
    * @param type $coddivisa_desde
    * @param type $coddivisa
    * @return type
    */
   public function divisa_convert($precio, $coddivisa_desde, $coddivisa)
   {
      if($coddivisa_desde != $coddivisa)
      {
         $div0 = new divisa();
         $divisa_desde = $div0->get($coddivisa_desde);
         if($divisa_desde)
         {
            $divisa = $div0->get($coddivisa);
            if($divisa)
            {
               $precio = $precio / $divisa_desde->tasaconv * $divisa->tasaconv;
            }
         }
      }

      return $precio;
   }

   /**
    * Obtenemos el rango de fechas a procesar
    * @return \DatePeriod
    */
   public function rango_fechas() {
      $begin = new \DateTime($this->fecha_inicio);
      $end = new \DateTime($this->fecha_fin);
      $end->modify("+1 day");
      $interval = new \DateInterval('P1D');
      $daterange = new \DatePeriod($begin, $interval, $end);
      return $daterange;
   }
}
