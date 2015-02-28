<?php
/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2014  Carlos Garcia Gomez  neorazorx@gmail.com
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

require_model('agente.php');
require_model('articulo.php');
require_model('factura_proveedor.php');
require_model('proveedor.php');

class compras_facturas extends fs_controller
{
   public $agente;
   public $articulo;
   public $buscar_lineas;
   public $factura;
   public $offset;
   public $proveedor;
   public $resultados;

   public function __construct()
   {
      parent::__construct(__CLASS__, 'Facturas de proveedor', 'compras', FALSE, TRUE);
   }
   
   protected function process()
   {
      $this->factura = new factura_proveedor();
      
      $this->offset = 0;
      if( isset($_GET['offset']) )
         $this->offset = intval($_GET['offset']);
      
      if( isset($_POST['buscar_lineas']) )
      {
         $this->buscar_lineas();
      }
      else if( isset($_GET['codagente']) )
      {
         $this->template = 'extension/compras_facturas_agente';
         
         $agente = new agente();
         $this->agente = $agente->get($_GET['codagente']);
         $this->resultados = $this->factura->all_from_agente($_GET['codagente'], $this->offset);
      }
      else if( isset($_GET['codproveedor']) )
      {
         $this->template = 'extension/compras_facturas_proveedor';
         
         $proveedor = new proveedor();
         $this->proveedor = $proveedor->get($_GET['codproveedor']);
         $this->resultados = $this->factura->all_from_proveedor($_GET['codproveedor'], $this->offset);
      }
      else if( isset($_GET['ref']) )
      {
         $this->template = 'extension/compras_facturas_articulo';
         
         $articulo = new articulo();
         $this->articulo = $articulo->get($_GET['ref']);
         
         $linea = new linea_factura_proveedor();
         $this->resultados = $linea->all_from_articulo($_GET['ref'], $this->offset);
      }
      else
      {
         $this->share_extension();
         
         if( isset($_GET['delete']) )
         {
            $fact = $this->factura->get($_GET['delete']);
            if($fact)
            {
               if( $fact->delete() )
               {
                  $this->new_message("Factura eliminada correctamente.");
               }
               else
                  $this->new_error_msg("¡Imposible eliminar la factura!");
            }
            else
               $this->new_error_msg("Factura no encontrada.");
         }
         
         if($this->query != '')
         {
            $this->resultados = $this->factura->search($this->query, $this->offset);
         }
         else if( isset($_GET['sinpagar']) )
         {
            $this->resultados = $this->factura->all_sin_pagar($this->offset);
         }
         else
            $this->resultados = $this->factura->all($this->offset);
      }
   }
   
   public function anterior_url()
   {
      $url = '';
      $extra = '';
      
      if( isset($_GET['sinpagar']) )
      {
         $extra = '&sinpagar=TRUE';
      }
      else if( isset($_GET['codagente']) )
      {
         $extra = '&codagente='.$_GET['codagente'];
      }
      else if( isset($_GET['codproveedor']) )
      {
         $extra = '&codproveedor='.$_GET['codproveedor'];
      }
      else if( isset($_GET['ref']) )
      {
         $extra = '&ref='.$_GET['ref'];
      }
      
      if($this->query!='' AND $this->offset>'0')
      {
         $url = $this->url()."&query=".$this->query."&offset=".($this->offset-FS_ITEM_LIMIT).$extra;
      }
      else if($this->query=='' AND $this->offset>'0')
      {
         $url = $this->url()."&offset=".($this->offset-FS_ITEM_LIMIT).$extra;
      }
      
      return $url;
   }
   
   public function siguiente_url()
   {
      $url = '';
      $extra = '';
      
      if( isset($_GET['sinpagar']) )
      {
         $extra = '&sinpagar=TRUE';
      }
      else if( isset($_GET['codagente']) )
      {
         $extra = '&codagente='.$_GET['codagente'];
      }
      else if( isset($_GET['codproveedor']) )
      {
         $extra = '&codproveedor='.$_GET['codproveedor'];
      }
      else if( isset($_GET['ref']) )
      {
         $extra = '&ref='.$_GET['ref'];
      }
      
      if($this->query!='' AND count($this->resultados)==FS_ITEM_LIMIT)
      {
         $url = $this->url()."&query=".$this->query."&offset=".($this->offset+FS_ITEM_LIMIT).$extra;
      }
      else if($this->query=='' AND count($this->resultados)==FS_ITEM_LIMIT)
      {
         $url = $this->url()."&offset=".($this->offset+FS_ITEM_LIMIT).$extra;
      }
      
      return $url;
   }
   
   public function buscar_lineas()
   {
      /// cambiamos la plantilla HTML
      $this->template = 'ajax/compras_lineas_facturas';

      $this->buscar_lineas = $_POST['buscar_lineas'];
      $linea = new linea_factura_proveedor();
      
      $this->lineas = $linea->search($this->buscar_lineas);
   }
   
   private function share_extension()
   {
      /// añadimos las extensiones para proveedores, agentes y artículos
      $extensiones = array(
          array(
              'name' => 'facturas_proveedor',
              'page_from' => __CLASS__,
              'page_to' => 'compras_proveedor',
              'type' => 'button',
              'text' => 'Facturas',
              'params' => ''
          ),
          array(
              'name' => 'facturas_agente',
              'page_from' => __CLASS__,
              'page_to' => 'admin_agente',
              'type' => 'button',
              'text' => 'Facturas de proveedor',
              'params' => ''
          ),
          array(
              'name' => 'facturas_articulo',
              'page_from' => __CLASS__,
              'page_to' => 'ventas_articulo',
              'type' => 'tab_button',
              'text' => 'Facturas de proveedor',
              'params' => ''
          )
      );
      foreach($extensiones as $ext)
      {
         $fsext0 = new fs_extension($ext);
         if( !$fsext0->save() )
         {
            $this->new_error_msg('Imposible guardar los datos de la extensión '.$ext['name'].'.');
         }
      }
   }
}
