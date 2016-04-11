<?php

/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2016 Luismipr <luismipr@gmail.com>.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * Lpublished by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * LeGNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Description of trazabilidad
 *
 * @author Luismipr <luismipr@gmail.com>
 */

require_model ('numeros_serie.php');

class trazabilidad extends fs_controller
{
   public $allow_delete;
   public $resultados;
   
   public $albcompra;
   public $albcompra_fecha;
   public $faccompra;
   public $faccompra_fecha;
   public $proveedor;
   
   public $albventa;
   public $albventa_fecha;
   
   
   
   
   
   //para nueva_venta.js
   public $ref;
   public $desc;
   public $pvp;
   public $dto;
   public $codimpuesto;
   public $cantidad;

   public function __construct()
   {
      parent::__construct(__CLASS__, 'Trazabilidad', 'ventas', FALSE, FALSE);
   }
   protected function private_core()
   {
      ///creamos el botón para acceder
      $this->share_extensions();
      
      /// ¿El usuario tiene permiso para eliminar en esta página?
      $this->allow_delete = $this->user->allow_delete_on(__CLASS__);
      
      $num0 = new numero_serie();
      $this->resultados = $num0->all();
      
      if( isset($_REQUEST['referencia4numserie']) )
      {
         $this->get_numserie_articulo();
      }
      
      if( isset($_REQUEST['buscar_numserie']) )
      {
         $this->buscar_numserie();
      }
   }
   
   /**
    * Añade la extensión tipo botón en artículos
    */
   private function share_extensions()
   {
      $fsext = new fs_extension();
      $fsext->name = 'btn_trazabilidad';
      $fsext->from = __CLASS__;
      $fsext->to = 'ventas_articulos';
      $fsext->type = 'button';
      $fsext->text = '<span class="glyphicon glyphicon-barcode" aria-hidden="true"></span>'
              . '<span class="hidden-xs"> &nbsp; Trazabilidad</span>';
      $fsext->save();
   }
   
   public function get_albaran_compra($linea)
   {
      $albaranc ='';
      $sql = "SELECT codigo FROM albaranesprov INNER JOIN lineasalbaranesprov on lineasalbaranesprov.idalbaran = albaranesprov.idalbaran "
              . "INNER JOIN numeros_serie on numeros_serie.idlalbcompra = lineasalbaranesprov.idlinea WHERE numeros_serie.idlalbcompra = $linea ;";
      
      $result = $this->db->select($sql);
        if($result)
         {
            $albaranc = $result[0]['codigo'];
         }
         return $albaranc;
   }
   
   public function get_factura_compra($linea)
   {
      $facturac ='';
      $sql = "SELECT codigo FROM facturasprov INNER JOIN lineasfacturasprov on lineasfacturasprov.idfactura = facturasprov.idfactura "
              . "INNER JOIN numeros_serie on numeros_serie.idlfaccompra = lineasfacturasprov.idlinea WHERE numeros_serie.idlfaccompra = $linea ;";
      
      $result = $this->db->select($sql);
        if($result)
         {
            $albaranc = $result[0]['codigo'];
         }
         return $facturac;
   } 
   
   public function get_albaran_venta($linea)
   {
      $albaranv ='';
      $sql = "SELECT codigo FROM albaranescli INNER JOIN lineasalbaranescli on lineasalbaranescli.idalbaran = albaranescli.idalbaran "
              . "INNER JOIN numeros_serie on numeros_serie.idlalbventa = lineasalbaranescli.idlinea WHERE numeros_serie.idlalbventa = $linea ;";
      
      $result = $this->db->select($sql);
        if($result)
         {
            $albaranv = $result[0]['codigo'];
         }
         return $albaranv;
   }
   
   
   public function get_factura_venta($linea)
   {
      $facturav ='';
      $sql = "SELECT codigo FROM facturascli INNER JOIN lineasfacturascli on lineasfacturascli.idfactura = facturascli.idfactura "
              . "INNER JOIN numeros_serie on numeros_serie.idlfacventa = lineasfacturascli.idlinea WHERE numeros_serie.idlfacventa = $linea ;";
      
      $result = $this->db->select($sql);
        if($result)
         {
            $facturav = $result[0]['codigo'];
         }
         return $facturav;
   }
}
