<?php

/*
 * Copyright (C) 2016 Joe Nilson <joenilson at gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
require_model('articulo.php');
require_model('almacen.php');

/**
 * Kardex para manejo de Artículos con inventario inicial e inventario final por fecha
 *
 * @author Joe Nilson <joenilson at gmail.com>
 */
class kardex extends fs_model
{
   public $codalmacen;
   public $fecha;
   public $referencia;
   public $descripcion;
   public $cantidad_ingreso;
   public $cantidad_salida;
   public $cantidad_saldo;
   public $monto_ingreso;
   public $monto_salida;
   public $monto_saldo;
   public $fecha_inicio;
   public $fecha_fin;
   public $fecha_proceso;
   public $articulo;
   public $almacene;
   public $articulos;
   public $almacenes;
   public $kardex_setup;
   public function __construct($s=FALSE)
   {
      parent::__construct('kardex', 'plugins/facturacion_base/');
      if($s)
      {
         $this->codalmacen = $s['codalmacen'];
         $this->fecha = $s['fecha'];
         $this->referencia = $s['referencia'];
         $this->descripcion = $s['descripcion'];
         $this->cantidad_ingreso = floatval($s['cantidad_ingreso']);
         $this->cantidad_salida = floatval($s['cantidad_salida']);
         $this->cantidad_saldo = floatval($s['cantidad_saldo']);
         $this->monto_ingreso = floatval($s['monto_ingreso']);
         $this->monto_salida = floatval($s['monto_salida']);
         $this->monto_saldo = floatval($s['monto_saldo']);
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
      $this->fecha_inicio = \date('Y-m-01');
      $this->fecha_fin = \date('Y-m-d');
      $this->fecha_proceso = NULL;
      $this->articulo = new articulo();
      $this->almacen = new almacen();
   }

   public function install()
   {
      $fsvar = new fs_var();
      $config = $fsvar->array_get(array(
         'kardex_ultimo_proceso' => '',
         'kardex_procesandose' => 'FALSE',
         'kardex_usuario_procesando' => ''
      ));
      $fsvar->array_save($config);
   }

   public function exists()
   {
      $sql = "SELECT fecha FROM ".$this->table_name." WHERE "
              ." codalmacen = ".$this->var2str($this->codalmacen)
              ." AND fecha = ".$this->var2str($this->fecha)
              ." AND referencia = ".$this->var2str($this->referencia).";";
      $data = $this->db->select($sql);
      if($data)
      {
         return TRUE;
      }
      else
      {
         return FALSE;
      }
   }

   public function save(){
      if( $this->exists() )
      {
         $sql = "UPDATE ".$this->table_name
                 .", cantidad_ingreso = ".$this->var2str($this->cantidad_ingreso)
                 .", cantidad_salida = ".$this->var2str($this->cantidad_salida)
                 .", cantidad_saldo = ".$this->var2str($this->cantidad_saldo)
                 .", monto_ingreso = ".$this->var2str($this->monto_ingreso)
                 .", monto_salida = ".$this->var2str($this->monto_salida)
                 .", monto_saldo = ".$this->var2str($this->monto_saldo)
                 ."  WHERE "
                 ." fecha = ".$this->var2str($this->fecha)
                 ." and referencia = ".$this->var2str($this->referencia)
                 ." and codalmacen = ".$this->var2str($this->codalmacen).";";
         return $this->db->exec($sql);
      }
      else
      {
         $sql = "INSERT INTO ".$this->table_name." (codalmacen,fecha,referencia,descripcion,
            cantidad_ingreso,cantidad_salida,cantidad_saldo,monto_ingreso,monto_salida,monto_saldo) VALUES
                   (".$this->var2str($this->codalmacen)
                 .",".$this->var2str($this->fecha)
                 .",".$this->var2str($this->referencia)
                 .",".$this->var2str($this->descripcion)
                 .",".$this->intval($this->cantidad_ingreso)
                 .",".$this->intval($this->cantidad_salida)
                 .",".$this->intval($this->cantidad_saldo)
                 .",".$this->intval($this->monto_ingreso)
                 .",".$this->intval($this->monto_salida)
                 .",".$this->intval($this->monto_saldo).");";

         if( $this->db->exec($sql) )
         {
            return TRUE;
         }
         else
            return FALSE;
      }
   }

   public function delete()
   {
      return '';
   }

   /*
   * Este cron generará los saldos de almacen por día
   * Para que cuando soliciten el movimiento por almacen por
   * articulo se pueda extraer de aquí en forma de resumen histórico
   */
   public function cron_job()
   {
      $this->procesar_kardex();
   }

   /*
    * Actualizamos la información del Kardex con las fechas de inicio y fin
    */
   public function procesar_kardex($usuario = NULL){

      $fsvar = new fs_var();
      $this->kardex_setup = $fsvar->array_get(
         array(
         'kardex_ultimo_proceso' => $this->fecha_proceso,
         'kardex_procesandose' => 'FALSE',
         'kardex_usuario_procesando' => 'cron'
         ), FALSE
      );
      if($this->kardex_setup['kardex_procesandose'] == 'TRUE'){
         //return false;
      }
      $this->ultima_fecha();
      $intervalo = date_diff(date_create($this->fecha_inicio), date_create($this->fecha_fin));
      $dias_proceso = $intervalo->format('%a');
      $rango = $this->rango_fechas();
      ob_implicit_flush(true);
      ob_end_flush();
      $inicio_total = new DateTime('NOW');
      $contador = 0;
      foreach($rango as $fecha){
         $inicio_paso = new DateTime('NOW');
         sleep(1);
         $plural = ($contador == 0)?"":"s";
         $p = ceil((($contador+1)*100)/$dias_proceso);
         $this->fecha_proceso = $fecha->format('Y-m-d');
         //Bloqueamos el intento de procesar el Kardex por varios usuarios al mismo tiempo
         $fsvar->array_save(
            array(
               'kardex_ultimo_proceso' => $this->fecha_proceso,
               'kardex_procesandose' => 'TRUE',
               'kardex_usuario_procesando' => ($usuario)?$usuario:'cron'
            )
         );
         $this->kardex_almacen();
         $fin_paso = $inicio_paso->diff(new DateTime('NOW'));
         $tiempo_proceso = $inicio_total->diff(new DateTime('NOW'));
         $time = $fin_paso->h.':'.$fin_paso->i.':'.$fin_paso->s;
         $tiempo_en_segundos = strtotime("1970-01-01 $time UTC");
         $tiempo_estimado = gmdate("H:i:s", ($dias_proceso * $tiempo_en_segundos));
         $response = array('message' =>'Procesando <b>'.$fecha->format("Y-m-d").'</b>, '. ($contador+1) .' día'.$plural.' de <b>'.$dias_proceso.'</b> procesado'.$plural.' en '.$tiempo_proceso->format('%H:%I:%S').', a las: ' . date("h:i:s", time()).' tiempo estimado total de proceso: <b>'.$tiempo_estimado.'</b>',
                              'progress' => $p);
         echo json_encode($response);
         $contador++;
      }
      sleep(1);
      $fsvar->array_save(array(
         'kardex_ultimo_proceso' => $this->fecha_proceso,
         'kardex_procesandose' => 'FALSE',
         'kardex_usuario_procesando' => ''
      ));
      $response = array('message' => '<b>¡Tarea completada en '.$tiempo_proceso->format('%H:%I:%S').'!<b>',
                          'progress' => 100);
      echo json_encode($response);

   }

   /*
    * Generamos la información por cada almacén activo
    */
   public function kardex_almacen(){
      if($this->fecha_proceso){
         foreach($this->almacen->all() as $almacen)
         {
             $this->stock_query($almacen);
         }
         gc_collect_cycles();
      }
   }

      /*
       * Esta es la consulta multiple que utilizamos para sacar la información
       * de todos los articulos tanto ingresos como salidas
       */
      public function stock_query($almacen){
         //Generamos el select para la subconsulta de productos activos y que se controla su stock
         $productos = "SELECT referencia, descripcion, costemedio FROM articulos where bloqueado = false and nostock = false;";
         $lista_productos = $this->db->select($productos);
         if($lista_productos){
            foreach($lista_productos as $item){
               $resultados['kardex']['referencia'] = $item['referencia'];
               $resultados['kardex']['descripcion'] = $item['descripcion'];
               $resultados['kardex']['salida_cantidad'] = 0;
               $resultados['kardex']['ingreso_cantidad'] = 0;
               $resultados['kardex']['salida_monto'] = 0;
               $resultados['kardex']['ingreso_monto'] = 0;
               $resultados['kardex']['cantidad_inicial'] = 0;
               $resultados['kardex']['monto_inicial'] = 0;
              /*
               * Generamos la informacion del saldo final del dia anterior segun Inventario diario
               */
              $fechaProceso = new DateTime( $this->fecha_proceso );
              $fechaAnterior = $fechaProceso->sub(new DateInterval('P1D'))->format('Y-m-d');
              $sql_regstocks = "select referencia, descripcion, cantidad_saldo, monto_saldo
                     FROM kardex 
                     where codalmacen = '".$almacen->codalmacen."' AND fecha = '".$fechaAnterior."'
                     and referencia = '".$item['referencia']."';";
              $data = $this->db->select($sql_regstocks);
              if($data){
                  foreach($data as $linea){
                      $resultados['kardex']['referencia'] = $item['referencia'];
                      $resultados['kardex']['descripcion'] = $item['descripcion'];
                      $resultados['kardex']['cantidad_inicial'] = $linea['cantidad_saldo'];
                      $resultados['kardex']['monto_inicial'] = $linea['monto_saldo'];
                  }
              }
              
              /*
               * Generamos la informacion de las regularizaciones que se hayan hecho a los stocks
               */
              $sql_regstocks = "select l.idstock, referencia, motivo, sum(cantidadfin) as cantidad
                     from lineasregstocks AS ls
                     JOIN stocks as l ON(ls.idstock = l.idstock)
                     where codalmacen = '".$almacen->codalmacen."' AND fecha = '".$this->fecha_proceso."'
                     and referencia = '".$item['referencia']."'
                     group by l.idstock, referencia, motivo
                     order by l.idstock;";
              $data = $this->db->select($sql_regstocks);
              if($data){
                  foreach($data as $linea){
                      $resultados['kardex']['referencia'] = $item['referencia'];
                      $resultados['kardex']['descripcion'] = $item['descripcion'];
                      $resultados['kardex']['salida_cantidad'] += ($linea['cantidad']<=0)?$linea['cantidad']:0;
                      $resultados['kardex']['ingreso_cantidad'] += ($linea['cantidad']>=0)?$linea['cantidad']:0;
                      $resultados['kardex']['salida_monto'] += ($linea['cantidad']<=0)?($item['costemedio']*$linea['cantidad']):0;
                      $resultados['kardex']['ingreso_monto'] += ($linea['cantidad']>=0)?($item['costemedio']*$linea['cantidad']):0;
                  }
              }
              
              /*
               * Generamos la informacion de los albaranes de proveedor asociados a facturas no anuladas
               */
              $sql_albaranes = "select ac.idalbaran,referencia,sum(cantidad) as cantidad, sum(pvptotal) as monto
                      from albaranesprov as ac
                      join lineasalbaranesprov as l ON (ac.idalbaran=l.idalbaran)
                      where codalmacen = '".$almacen->codalmacen."' AND fecha = '".$this->fecha_proceso."'
                      and idfactura is not null
                      and referencia = '".$item['referencia']."'
                      group by ac.idalbaran,l.referencia 
                      order by ac.idalbaran;";
              $data = $this->db->select($sql_albaranes);
              if($data){
                  foreach($data as $linea){
                     $resultados['kardex']['referencia'] = $item['referencia'];
                     $resultados['kardex']['descripcion'] = $item['descripcion'];
                     $resultados['kardex']['salida_cantidad'] += ($linea['cantidad']<=0)?$linea['cantidad']:0;
                     $resultados['kardex']['ingreso_cantidad'] += ($linea['cantidad']>=0)?$linea['cantidad']:0;
                     $resultados['kardex']['salida_monto'] += ($linea['monto']<=0)?$linea['monto']:0;
                     $resultados['kardex']['ingreso_monto'] += ($linea['monto']>=0)?$linea['monto']:0;
                  }
              }

              /*
               * Generamos la informacion de las facturas de proveedor ingresadas
               * que no esten asociadas a un albaran de proveedor
               */
              $sql_facturasprov = "select fc.idfactura,referencia,sum(cantidad) as cantidad, sum(pvptotal) as monto
                      from facturasprov as fc
                      join lineasfacturasprov as l ON (fc.idfactura=l.idfactura)
                      where codalmacen = '".$almacen->codalmacen."' AND fecha = '".$this->fecha_proceso."'
                      and anulada=FALSE and idalbaran is null
                      and referencia = '".$item['referencia']."'
                      group by fc.idfactura,referencia
                      order by fc.idfactura;";
              $data = $this->db->select($sql_facturasprov);
              if($data){
                  foreach($data as $linea){
                     $resultados['kardex']['referencia'] = $item['referencia'];
                     $resultados['kardex']['descripcion'] = $item['descripcion'];
                     $resultados['kardex']['salida_cantidad'] += ($linea['cantidad']<=0)?$linea['cantidad']:0;
                     $resultados['kardex']['ingreso_cantidad'] += ($linea['cantidad']>=0)?$linea['cantidad']:0;
                     $resultados['kardex']['salida_monto'] += ($linea['monto']<=0)?$linea['monto']:0;
                     $resultados['kardex']['ingreso_monto'] += ($linea['monto']>=0)?$linea['monto']:0;
                  }
              }
              
               /*
               * Generamos la informacion de los albaranes asociados a facturas no anuladas
               */
              $sql_albaranes = "select ac.idalbaran,referencia,sum(cantidad) as cantidad, sum(pvptotal) as monto
                      from albaranescli as ac
                      join lineasalbaranescli as l ON (ac.idalbaran=l.idalbaran)
                      where codalmacen = '".$almacen->codalmacen."' AND fecha = '".$this->fecha_proceso."'
                      and idfactura is not null
                      and referencia = '".$item['referencia']."'
                      group by ac.idalbaran,referencia
                      order by ac.idalbaran;";
              $data = $this->db->select($sql_albaranes);
              if($data){
                  foreach($data as $linea){
                     $resultados['kardex']['referencia'] = $item['referencia'];
                     $resultados['kardex']['descripcion'] = $item['descripcion'];
                     $resultados['kardex']['salida_cantidad'] += ($linea['cantidad']>=0)?$linea['cantidad']:0;
                     $resultados['kardex']['ingreso_cantidad'] += ($linea['cantidad']<=0)?$linea['cantidad']:0;
                     $resultados['kardex']['salida_monto'] += ($linea['monto']>=0)?$linea['monto']:0;
                     $resultados['kardex']['ingreso_monto'] += ($linea['monto']<=0)?$linea['monto']:0;
                  }
              }

              /*
               * Generamos la informacion de las facturas que se han generado sin albaran
               */
              $sql_facturas = "select fc.idfactura,referencia,sum(cantidad) as cantidad, sum(pvptotal) as monto
                      from facturascli as fc
                      join lineasfacturascli as l ON (fc.idfactura=l.idfactura)
                      where codalmacen = '".$almacen->codalmacen."' AND fecha = '".$this->fecha_proceso."'
                      and anulada=FALSE and idalbaran is null
                      and referencia = '".$item['referencia']."'
                      group by fc.idfactura,referencia
                      order by fc.idfactura;";
              $data = $this->db->select($sql_facturas);
              if($data){
                  foreach($data as $linea){
                     $resultados['kardex']['referencia'] = $item['referencia'];
                     $resultados['kardex']['descripcion'] = $item['descripcion'];
                     $resultados['kardex']['salida_cantidad'] += ($linea['cantidad']>=0)?$linea['cantidad']:0;
                     $resultados['kardex']['ingreso_cantidad'] += ($linea['cantidad']<=0)?$linea['cantidad']:0;
                     $resultados['kardex']['salida_monto'] += ($linea['monto']>=0)?$linea['monto']:0;
                     $resultados['kardex']['ingreso_monto'] += ($linea['monto']<=0)?$linea['monto']:0;
                  }
              }
              /*
               * Guardamos el resultado de las consultas
               */
              foreach($resultados as $valores){
                 $valores['ingreso_cantidad'] = ($valores['ingreso_cantidad'])?$valores['ingreso_cantidad']:0;
                 $valores['salida_cantidad'] = ($valores['salida_cantidad'])?$valores['salida_cantidad']:0;
                 $valores['ingreso_monto'] = ($valores['ingreso_monto'])?$valores['ingreso_monto']:0;
                 $valores['salida_monto'] = ($valores['salida_monto'])?$valores['salida_monto']:0;
                 $kardex0 = new kardex();
                 $kardex0->codalmacen = $almacen->codalmacen;
                 $kardex0->fecha = $this->fecha_proceso;
                 $kardex0->referencia = $valores['referencia'];
                 $kardex0->descripcion = $valores['descripcion'];
                 $kardex0->cantidad_ingreso = $valores['ingreso_cantidad'];
                 $kardex0->cantidad_salida = $valores['salida_cantidad'];
                 $kardex0->cantidad_saldo = ($valores['cantidad_inicial']+($valores['ingreso_cantidad']-$valores['salida_cantidad']));
                 $kardex0->monto_ingreso = $valores['ingreso_monto'];
                 $kardex0->monto_salida = $valores['salida_monto'];
                 $kardex0->monto_saldo = ($valores['monto_inicial']+($valores['ingreso_monto']-$valores['salida_monto']));
                 $kardex0->save();
              }
              gc_collect_cycles();
           }
        }
    }

   /*
    * Buscamos la fecha del ultimo ingreso en el Kardex
    */
   public function ultima_fecha(){
      // Buscamos el registro más antiguo en la tabla de kardex
      $min_fecha = $this->db->select("SELECT max(fecha) as fecha FROM kardex;");
      // Si hay data, continuamos desde la siguiente fecha
      if($min_fecha[0]['fecha']){
         $this->fecha_inicio = $min_fecha[0]['fecha'];
      }
      //Si no hay nada tenemos que ejecutar un proceso para todas las fechas desde el registro más antiguo
      else
      {
         $select = "SELECT
            CASE
            WHEN min(l.fecha) <= min(ap.fecha) AND min(l.fecha) <= min(ac.fecha) AND min(l.fecha) <= min(fp.fecha) AND min(l.fecha) <= min(fc.fecha) THEN min(l.fecha)
            WHEN min(ap.fecha) <= min(ac.fecha) AND min(ap.fecha) <= min(fp.fecha) AND min(ap.fecha) <= min(fc.fecha) THEN min(ap.fecha)
            WHEN min(ac.fecha) <= min(fp.fecha) AND min(ac.fecha) <= min(fc.fecha) THEN min(ac.fecha)
            WHEN min(fp.fecha) <= min(fc.fecha) THEN min(fp.fecha)
            ELSE min(fc.fecha)
            END AS fecha
           FROM lineasregstocks as l, albaranesprov as ap, albaranescli as ac, facturasprov as fp, facturascli as fc;";
         $min_fecha = $this->db->select($select);

      }
      $this->fecha_inicio = $min_fecha[0]['fecha'];
   }

   /**
   * Generamos fecha de inicio y fecha de fin
   */
   public function rango_fechas(){
      $begin = new DateTime( $this->fecha_inicio );
      $end = new DateTime( $this->fecha_fin );
      $last = $end->modify( '+1 day' );
      $interval = new DateInterval('P1D');
      $daterange = new DatePeriod($begin, $interval ,$last);
      return $daterange;
   }
}
