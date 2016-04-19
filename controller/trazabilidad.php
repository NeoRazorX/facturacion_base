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
require_model ('articulo.php');
require_model ('albaran_cliente.php');
require_model ('factura_cliente.php');
require_model ('albaran_proveedor.php');
require_model ('factura_proveedor.php');
require_model ('linea_albaran_cliente.php');
require_model ('linea_factura_cliente.php');
require_model ('linea_albaran_proveedor.php');
require_model ('linea_factura_proveedor.php');

class trazabilidad extends fs_controller
{
   public $allow_delete;
   public $resultados;
   
   public $albcompra;
   public $faccompra;
   
   public $albventa;
   public $facventa;
   
   
   public $numserie;
   

   public function __construct()
   {
      parent::__construct(__CLASS__, 'Trazabilidad', 'ventas', FALSE, FALSE);
   }
   protected function private_core()
   {
      ///creamos el botón para acceder
      $this->share_extensions();
      $this->numserie = '';
      $this->albcompra = '';
      $this->faccompra = '';
      $this->albventa = '';
      $this->facventa = '';
      
      $numserie = new numero_serie();
      
      /// ¿El usuario tiene permiso para eliminar en esta página?
      $this->allow_delete = $this->user->allow_delete_on(__CLASS__);
     
      //mostramos datos del numserie
      if( isset($_REQUEST['numserie']) )
      {
         $this->numserie = $numserie->get($_REQUEST['numserie']);  
         $this->get_datos();
      }
      else
         $this->resultados = $numserie->all ();
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
   
   public function get_datos()
   {
      //albarán de compra
      $lac = new linea_albaran_proveedor();
      $lineaalbaranproveedor = $lac->get($this->numserie->idlalbcompra);
      if (isset($lineaalbaranproveedor))
      {
         $albaranprov0 = new albaran_proveedor();
         $this->albcompra = $albaranprov0->get($lineaalbaranproveedor->idalbaran);
      }

      //factura de compra
      $lfp = new linea_factura_proveedor();
      $lineafacturaproveedor = $lfp->get($this->numserie->idlfaccompra);
      $facturaproveedor0 = new factura_proveedor();
      $this->faccompra = $facturaproveedor0->get($lineafacturaproveedor->idfactura);
      
      //albaran de venta
      $lav = new linea_albaran_cliente();
      $lineaalbarancliente = $lav->get($this->numserie->idlalbventa);
      $albarancli0 = new albaran_cliente();
      $this->albventa = $albarancli0->get($lineaalbarancliente->idalbaran);
      
      //factura cliente
      $lfc = new linea_factura_cliente();
      $lineafacturacli0 = $lfc->get($this->numserie->idlfacventa);
      $faccli0 = new factura_cliente();
      $this->facventa = $faccli0->get($lineafacturacli0->idfactura);
   
   } 
   public function art_desc($ref)
   {
      $descripcion = '';
      $sql = "SELECT descripcion FROM articulos WHERE referencia = '$ref' ;";
      $result = $this->db->select($sql);
      if ($result)
      {
         $descripcion = $result[0]['descripcion'];
      }
         return $descripcion;
   }
   
}
