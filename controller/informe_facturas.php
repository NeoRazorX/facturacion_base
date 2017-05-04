<?php

/*
 * This file is part of facturacion_base
 * Copyright (C) 2013-2017    Carlos Garcia Gomez  neorazorx@gmail.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require_once __DIR__.'/informe_albaranes.php';
require_model('factura_cliente.php');
require_model('factura_proveedor.php');

/**
 * Heredamos del controlador de informe_albaranes, para reaprovechar el código.
 */
class informe_facturas extends informe_albaranes
{
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Facturas', 'informes');
   }
   
   protected function private_core()
   {
      /// declaramos los objetos sólo para asegurarnos de que existen las tablas
      $factura_cli = new factura_cliente();
      $factura_pro = new factura_proveedor();
      
      $this->nombre_docs = 'Facturas';
      $this->table_compras = 'facturasprov';
      $this->table_ventas = 'facturascli';
      
      parent::private_core();
   }
   
   public function stats_series($tabla = 'facturasprov')
   {
      return parent::stats_series($tabla);
   }

   public function stats_agentes($tabla = 'facturasprov')
   {
      return parent::stats_agentes($tabla);
   }
   
   public function stats_almacenes($tabla = 'facturasprov')
   {
      return parent::stats_almacenes($tabla);
   }

   public function stats_formas_pago($tabla = 'facturasprov')
   {
      return parent::stats_formas_pago($tabla);
   }
   
   public function stats_estados($tabla = 'facturasprov')
   {
      $stats = array();
      
      $where = $this->where_compras;
      if($tabla == $this->table_ventas)
      {
         $where = $this->where_ventas;
      }
      
      /// aprobados
      $sql  = "select sum(neto) as total from ".$tabla;
		$sql .= $where;
   	$sql .=" and idfactura is not null order by total desc;";
      
      $data = $this->db->select($sql);
      if($data)
      {
         if( floatval($data[0]['total']) )
         {
            $stats[] = array(
                'txt' => 'facturado',
                'total' => round( floatval($data[0]['total']), FS_NF0)
            );
         }
      }
      
      /// pendientes
      $sql  = "select sum(neto) as total from ".$tabla;
		$sql .= $where;
   	$sql .=" and idfactura is null order by total desc;";
      
      $data = $this->db->select($sql);
      if($data)
      {
         if( floatval($data[0]['total']) )
         {
            $stats[] = array(
                'txt' => 'no facturado',
                'total' => round( floatval($data[0]['total']), FS_NF0)
            );
         }
      }
   
      return $stats;
   }
   
   protected function get_documentos($tabla)
   {
      $doclist = array();
      
      $where = $this->where_compras;
      if($tabla == $this->table_ventas)
      {
         $where = $this->where_ventas;
      }
      
      $sql  = "select * from ".$tabla.$where." order by fecha asc, hora asc;";
      $data = $this->db->select($sql);
      if($data)
      {
         foreach($data as $d)
         {
            if($tabla == $this->table_ventas)
            {
               $doclist[] = new factura_cliente($d);
            }
            else
            {
               $doclist[] = new factura_proveedor($d);
            }
         }
      }
      
      return $doclist;
   }
}
