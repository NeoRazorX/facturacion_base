<?php
/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2014-2015  Carlos Garcia Gomez  neorazorx@gmail.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require_model('albaran_cliente.php');
require_model('albaran_proveedor.php');

class informe_albaranes extends fs_controller
{
   public $stats;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, ucfirst(FS_ALBARANES), 'informes', FALSE, TRUE);
   }
   
   protected function private_core()
   {
      /// declaramos los objetos sólo para asegurarnos de que existen las tablas
      $albaran_cli = new albaran_cliente();
      $albaran_pro = new albaran_proveedor();
      
      $this->stats = array(
          'alb_pendientes' => 0,
          'alb_pendientes_total' => 0,
          'ped_pendientes' => 0,
          'ped_pendientes_total' => 0,
          'pre_pendientes' => 0,
          'pre_pendientes_total' => 0,
          'media_ventas_dia' => 0,
          'media_compras_dia' => 0,
          'media_ventas_mes' => 0,
          'media_compras_mes' => 0,
      );
      
      /// comprobamos los albaranes pendientes
      $sql = "SELECT COUNT(idalbaran) as num, SUM(total) as total FROM albaranescli WHERE ptefactura = true;";
      $data = $this->db->select($sql);
      if($data)
      {
         $this->stats['alb_pendientes'] = intval($data[0]['num']);
         $this->stats['alb_pendientes_total'] = floatval($data[0]['total']);
      }
      
      if( $this->db->table_exists('pedidoscli') )
      {
         /// comprobamos los pedidos pendientes
         $sql = "SELECT COUNT(idpedido) as num, SUM(total) as total FROM pedidoscli WHERE idalbaran IS NULL AND status=0;";
         $data = $this->db->select($sql);
         if($data)
         {
            $this->stats['ped_pendientes'] = intval($data[0]['num']);
            $this->stats['ped_pendientes_total'] = floatval($data[0]['total']);
         }
      }
      
      if( $this->db->table_exists('presupuestoscli') )
      {
         /// comprobamos los presupuestos pendientes
         $sql = "SELECT COUNT(idpresupuesto) as num, SUM(total) as total FROM presupuestoscli WHERE idpedido IS NULL AND status=0;";
         $data = $this->db->select($sql);
         if($data)
         {
            $this->stats['pre_pendientes'] = intval($data[0]['num']);
            $this->stats['pre_pendientes_total'] = floatval($data[0]['total']);
         }
      }
   }
   
   public function stats_last_days()
   {
      $stats = array();
      $stats_cli = $this->stats_last_days_aux('albaranescli');
      $stats_pro = $this->stats_last_days_aux('albaranesprov');
      
      foreach($stats_cli as $i => $value)
      {
         $stats[$i] = array(
             'day' => $value['day'],
             'total_cli' => $value['total'],
             'total_pro' => 0
         );
      }
      
      foreach($stats_pro as $i => $value)
      {
         $stats[$i]['total_pro'] = $value['total'];
      }
      
      /// leemos para completar $this->stats
      $num = 0;
      foreach($stats as $st)
      {
         $this->stats['media_ventas_dia'] += $st['total_cli'];
         $this->stats['media_compras_dia'] += $st['total_pro'];
         $num++;
      }
      
      if($num > 0)
      {
         $this->stats['media_ventas_dia'] = $this->stats['media_ventas_dia'] / $num;
         $this->stats['media_compras_dia'] = $this->stats['media_compras_dia'] / $num;
      }
      
      return $stats;
   }
   
   public function stats_last_days_aux($table_name='albaranescli', $numdays = 25)
   {
      $stats = array();
      $desde = Date('d-m-Y', strtotime( Date('d-m-Y').'-'.$numdays.' day'));
      
      foreach($this->date_range($desde, Date('d-m-Y'), '+1 day', 'd') as $date)
      {
         $i = intval($date);
         $stats[$i] = array('day' => $i, 'total' => 0);
      }
      
      if( strtolower(FS_DB_TYPE) == 'postgresql')
      {
         $sql_aux = "to_char(fecha,'FMDD')";
      }
      else
         $sql_aux = "DATE_FORMAT(fecha, '%d')";
      
      $data = $this->db->select("SELECT ".$sql_aux." as dia, sum(total) as total
         FROM ".$table_name." WHERE fecha >= ".$this->empresa->var2str($desde)."
         AND fecha <= ".$this->empresa->var2str(Date('d-m-Y'))."
         GROUP BY ".$sql_aux." ORDER BY dia ASC;");
      if($data)
      {
         foreach($data as $d)
         {
            $i = intval($d['dia']);
            $stats[$i] = array(
                'day' => $i,
                'total' => floatval($d['total'])
            );
         }
      }
      return $stats;
   }
   
   public function stats_last_months()
   {
      $stats = array();
      $stats_cli = $this->stats_last_months_aux('albaranescli');
      $stats_pro = $this->stats_last_months_aux('albaranesprov');
      $meses = array(
          1 => 'ene',
          2 => 'feb',
          3 => 'mar',
          4 => 'abr',
          5 => 'may',
          6 => 'jun',
          7 => 'jul',
          8 => 'ago',
          9 => 'sep',
          10 => 'oct',
          11 => 'nov',
          12 => 'dic'
      );
      
      foreach($stats_cli as $i => $value)
      {
         $stats[$i] = array(
             'month' => $meses[ $value['month'] ],
             'total_cli' => round($value['total'], 2),
             'total_pro' => 0
         );
      }
      
      foreach($stats_pro as $i => $value)
      {
         $stats[$i]['total_pro'] = round($value['total'], 2);
      }
      
      /// leemos para completar $this->stats
      $num = 0;
      foreach($stats as $st)
      {
         $this->stats['media_ventas_mes'] += $st['total_cli'];
         $this->stats['media_compras_mes'] += $st['total_pro'];
         $num++;
      }
      
      if($num > 0)
      {
         $this->stats['media_ventas_mes'] = $this->stats['media_ventas_mes'] / $num;
         $this->stats['media_compras_mes'] = $this->stats['media_compras_mes'] / $num;
      }
      
      return $stats;
   }
   
   public function stats_last_months_aux($table_name='albaranescli', $num = 11)
   {
      $stats = array();
      $desde = Date('d-m-Y', strtotime( Date('01-m-Y').'-'.$num.' month'));
      
      foreach($this->date_range($desde, Date('d-m-Y'), '+1 month', 'm') as $date)
      {
         $i = intval($date);
         $stats[$i] = array('month' => $i, 'total' => 0);
      }
      
      if( strtolower(FS_DB_TYPE) == 'postgresql')
      {
         $sql_aux = "to_char(fecha,'FMMM')";
      }
      else
         $sql_aux = "DATE_FORMAT(fecha, '%m')";
      
      $data = $this->db->select("SELECT ".$sql_aux." as mes, sum(total) as total
         FROM ".$table_name." WHERE fecha >= ".$this->empresa->var2str($desde)."
         AND fecha <= ".$this->empresa->var2str(Date('d-m-Y'))."
         GROUP BY ".$sql_aux." ORDER BY mes ASC;");
      if($data)
      {
         foreach($data as $d)
         {
            $i = intval($d['mes']);
            $stats[$i] = array(
                'month' => $i,
                'total' => floatval($d['total'])
            );
         }
      }
      return $stats;
   }
   
   public function stats_last_years()
   {
      $stats = array();
      $stats_cli = $this->stats_last_years_aux('albaranescli');
      $stats_pro = $this->stats_last_years_aux('albaranesprov');
      
      foreach($stats_cli as $i => $value)
      {
         $stats[$i] = array(
             'year' => $value['year'],
             'total_cli' => round($value['total'], 2),
             'total_pro' => 0
         );
      }
      
      foreach($stats_pro as $i => $value)
      {
         $stats[$i]['total_pro'] = round($value['total'], 2);
      }
      
      return $stats;
   }
   
   public function stats_last_years_aux($table_name='albaranescli', $num = 4)
   {
      $stats = array();
      $desde = Date('d-m-Y', strtotime( Date('d-m-Y').'-'.$num.' year'));
      
      foreach($this->date_range($desde, Date('d-m-Y'), '+1 year', 'Y') as $date)
      {
         $i = intval($date);
         $stats[$i] = array('year' => $i, 'total' => 0);
      }
      
      if( strtolower(FS_DB_TYPE) == 'postgresql')
      {
         $sql_aux = "to_char(fecha,'FMYYYY')";
      }
      else
         $sql_aux = "DATE_FORMAT(fecha, '%Y')";
      
      $data = $this->db->select("SELECT ".$sql_aux." as ano, sum(total) as total
         FROM ".$table_name." WHERE fecha >= ".$this->empresa->var2str($desde)."
         AND fecha <= ".$this->empresa->var2str(Date('d-m-Y'))."
         GROUP BY ".$sql_aux." ORDER BY ano ASC;");
      if($data)
      {
         foreach($data as $d)
         {
            $i = intval($d['ano']);
            $stats[$i] = array(
                'year' => $i,
                'total' => floatval($d['total'])
            );
         }
      }
      return $stats;
   }
   
   private function date_range($first, $last, $step = '+1 day', $format = 'd-m-Y' )
   {
      $dates = array();
      $current = strtotime($first);
      $last = strtotime($last);
      
      while( $current <= $last )
      {
         $dates[] = date($format, $current);
         $current = strtotime($step, $current);
      }
      
      return $dates;
   }
}
