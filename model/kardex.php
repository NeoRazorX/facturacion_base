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

/**
 * Kardex para manejo de Artículos
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
   }

   public function install()
   {
      return '';
   }

   public function exists()
   {
      return true;
   }

   public function save(){

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
   public function procesar_kardex(){
      $fechaHoy = \Date('Y-m-d');
      $diaHoy = \Date('d');
      $mesHoy = \Date('m');
      $anhoHoy = \Date('Y');
      $min_fecha = $this->ultima_fecha();
      $this->fecha_inicio = $min_fecha[0]['fecha'];
      $interval = date_diff(date_create($this->fecha_inicio), date_create($this->fecha_fin));
      //echo $interval->format('%a');
      $intervalo = $this->rango_fechas();
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
         $intervalo = $this->rango_fechas();
         echo count($intervalo);
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
      return $min_fecha;
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
