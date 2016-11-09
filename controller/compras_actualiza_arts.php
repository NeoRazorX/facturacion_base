<?php

/*
 * This file is part of FacturaScripts
 * Copyright (C) 2015-2016  Carlos Garcia Gomez  neorazorx@gmail.com
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

require_model('albaran_proveedor.php');
require_model('articulo.php');
require_model('articulo_proveedor.php');
require_model('pedido_proveedor.php');

/**
 * Description of articulos_documento
 *
 * @author carlos
 */
class compras_actualiza_arts extends fs_controller
{
   public $documento;
   public $lineas;
   public $tipodoc;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Artículos documento', 'compras', FALSE, FALSE);
   }
   
   protected function private_core()
   {
      $this->share_extensions();
      
      $this->documento = FALSE;
      $this->lineas = array();
      $this->tipodoc = 'Documento';
      if( isset($_REQUEST['doc']) AND isset($_REQUEST['id']) )
      {
         if($_REQUEST['doc'] == 'pedido')
         {
            $pedido0 = new pedido_proveedor();
            $this->documento = $pedido0->get($_REQUEST['id']);
            $this->tipodoc = FS_PEDIDO;
         }
         else
         {
            $albaran0 = new albaran_proveedor();
            $this->documento = $albaran0->get($_REQUEST['id']);
            $this->tipodoc = FS_ALBARAN;
         }
         
         if($this->documento)
         {
            $this->lineas = $this->documento->get_lineas();
            
            $art0 = new articulo();
            $ap0 = new articulo_proveedor();
            
            $cambios = 0;
            foreach($this->lineas as $i => $value)
            {
               $this->lineas[$i]->refproveedor = $value->referencia;
               $this->lineas[$i]->codbarras = '';
               $this->lineas[$i]->precio_compra = 0;
               $this->lineas[$i]->dto_compra = 0;
               $this->lineas[$i]->precio_venta = 0;
               
               $ap = $ap0->get_by($value->referencia, $this->documento->codproveedor);
               if($ap)
               {
                  $this->lineas[$i]->refproveedor = $ap->refproveedor;
                  $this->lineas[$i]->precio_compra = $ap->precio;
                  $this->lineas[$i]->dto_compra = $ap->dto;
               }
               
               $articulo = $art0->get($value->referencia);
               if($articulo)
               {
                  $this->lineas[$i]->codbarras = $articulo->codbarras;
                  $this->lineas[$i]->precio_venta = $articulo->pvp;
               }
               
               /// ¿Tenemos los datos del form?
               if( isset($_POST['update_'.$value->idlinea]) )
               {
                  /**
                   * Volvemos a buscar el artículos del proveedor, pero esta vez
                   * buscamos también con la referencia del proveedor.
                   */
                  $ap = $ap0->get_by($value->referencia, $this->documento->codproveedor, $_POST['refproveedor_'.$value->idlinea]);
                  if(!$ap)
                  {
                     $ap = new articulo_proveedor();
                     $ap->codproveedor = $this->documento->codproveedor;
                  }
                  
                  $ap->referencia = $value->referencia;
                  $ap->refproveedor = $_POST['refproveedor_'.$value->idlinea];
                  $this->lineas[$i]->refproveedor = $ap->refproveedor;
                  
                  $ap->precio = floatval($_POST['coste_'.$value->idlinea]);
                  $ap->dto = floatval($_POST['dto_'.$value->idlinea]);
                  $ap->save();
                  
                  if($articulo)
                  {
                     if( isset($_POST['descripciones']) )
                     {
                        $articulo->descripcion = $_POST['descripcion_'.$value->idlinea];
                     }
                     
                     if( isset($_POST['codbarras']) )
                     {
                        $articulo->codbarras = $_POST['codbarras_'.$value->idlinea];
                        $this->lineas[$i]->codbarras = $articulo->codbarras;
                     }
                     
                     if( isset($_POST['pvps']) )
                     {
                        $articulo->set_pvp( floatval($_POST['pvp_'.$value->idlinea]) );
                     }
                     
                     /// ¿usamos la referencia de proveedor como equivalencia?
                     if($_POST['refproveedor_'.$value->idlinea] != '' AND $_POST['refproveedor_'.$value->idlinea] != $articulo->referencia)
                     {
                        if( is_null($articulo->equivalencia) )
                        {
                           $articulo->equivalencia = $_POST['refproveedor_'.$value->idlinea];
                        }
                     }
                     
                     $articulo->save();
                  }
                  
                  $cambios++;
               }
            }
            
            if($cambios > 0)
            {
               $this->new_message($cambios.' cambios realizados.');
            }
         }
         else
            $this->new_error_msg('Documento no encontrado.');
      }
      else
      {
         $this->new_error_msg('Faltan datos.', 'error', FALSE, FALSE);
      }
   }
   
   private function share_extensions()
   {
      /// añadimos las extensiones para pedidos y albaranes
      $extensiones = array(
          array(
              'name' => 'btn_pedido',
              'page_from' => __CLASS__,
              'page_to' => 'compras_pedido',
              'type' => 'tab',
              'text' => '<span class="glyphicon glyphicon-share" aria-hidden="true"></span>'
              . '<span class="hidden-xs">&nbsp; Actualizar</span>',
              'params' => '&doc=pedido'
          ),
          array(
              'name' => 'btn_albaran',
              'page_from' => __CLASS__,
              'page_to' => 'compras_albaran',
              'type' => 'tab',
              'text' => '<span class="glyphicon glyphicon-share" aria-hidden="true"></span>'
              . '<span class="hidden-xs">&nbsp; Actualizar</span>',
              'params' => '&doc=albaran'
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
   
   public function url()
   {
      $url = parent::url();
      
      if( isset($_REQUEST['doc']) AND isset($_REQUEST['id']) )
      {
         $url .= '&doc='.$_REQUEST['doc'].'&id='.$_REQUEST['id'];
      }
      
      return $url;
   }
}
