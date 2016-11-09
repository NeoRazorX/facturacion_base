<?php

/**
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

require_model('articulo.php');
require_model('articulo_propiedad.php');
require_model('subcuenta.php');

/**
 * Description of articulo_subcuentas
 *
 * @author carlos
 */
class articulo_subcuentas extends fs_controller
{
   public $articulo;
   public $subcuentacom;
   public $subcuentairpfcom;
   public $subcuentaventa;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Subcuentas', 'ventas', FALSE, FALSE);
   }
   
   protected function private_core()
   {
      $this->share_extension();
      $art0 = new articulo();
      
      $this->articulo = FALSE;
      if( isset($_REQUEST['ref']) )
      {
         $this->articulo = $art0->get($_REQUEST['ref']);
      }
      
      if( isset($_REQUEST['buscar_subcuenta']) )
      {
         /// esto es para el autocompletar las subcuentas de la vista
         $this->buscar_subcuenta();
      }
      else if($this->articulo)
      {
         $ap = new articulo_propiedad();
         
         if( isset($_POST['codsubcuentacom']) )
         {
            $this->articulo->codsubcuentacom = $_POST['codsubcuentacom'];
            $this->articulo->codsubcuentairpfcom = $_POST['codsubcuentairpfcom'];
            $aprops = array('codsubcuentaventa' => $_POST['codsubcuentaventa']);
            
            if($this->articulo->save() AND $ap->array_save($this->articulo->referencia, $aprops) )
            {
               $this->new_message('Datos guardados correctamente.');
            }
            else
            {
               $this->new_error_msg('Error al guardar las subcuentas.');
            }
         }
         
         $eje0 = new ejercicio();
         $ejercicio = $eje0->get_by_fecha( $this->today() );
         $sc = new subcuenta();
         
         $this->subcuentacom = $sc->get_by_codigo($this->articulo->codsubcuentacom, $ejercicio->codejercicio);
         $this->subcuentairpfcom = $sc->get_by_codigo($this->articulo->codsubcuentairpfcom, $ejercicio->codejercicio);
         
         $propiedades = $ap->array_get($this->articulo->referencia);
         if( isset($propiedades['codsubcuentaventa']) )
         {
            $this->subcuentaventa = $sc->get_by_codigo($propiedades['codsubcuentaventa'], $ejercicio->codejercicio);
         }
         
         /**
          * si alguna subcuenta no se encontrase, devuelve un false,
          * pero necesitamos una subcuenta para la vista, aunque no esté en
          * blanco y no esté en la base de datos
          */
         if(!$this->subcuentacom)
         {
            $this->subcuentacom = $sc;
         }
         if(!$this->subcuentairpfcom)
         {
            $this->subcuentairpfcom = $sc;
         }
         if(!$this->subcuentaventa)
         {
            $this->subcuentaventa = $sc;
         }
      }
      else
      {
         $this->new_error_msg('Artículo no encontrado.', 'error', FALSE, FALSE);
      }
   }
   
   private function share_extension()
   {
      $fsext = new fs_extension();
      $fsext->name = 'articulo_subcuentas';
      $fsext->from = __CLASS__;
      $fsext->to = 'ventas_articulo';
      $fsext->type = 'tab';
      $fsext->text = '<span class="glyphicon glyphicon-book" aria-hidden="true">'
              . '</span><span class="hidden-xs">&nbsp; Subcuentas</span>';
      $fsext->save();
   }
   
   public function url()
   {
      if($this->articulo)
      {
         return 'index.php?page='.__CLASS__.'&ref='.$this->articulo->referencia;
      }
      else
         return parent::url();
   }
   
   private function buscar_subcuenta()
   {
      /// desactivamos la plantilla HTML
      $this->template = FALSE;
      
      $subcuenta = new subcuenta();
      $eje0 = new ejercicio();
      $ejercicio = $eje0->get_by_fecha( $this->today() );
      $json = array();
      foreach($subcuenta->search_by_ejercicio($ejercicio->codejercicio, $_REQUEST['buscar_subcuenta']) as $subc)
      {
         $json[] = array(
             'value' => $subc->codsubcuenta,
             'data' => $subc->descripcion,
             'saldo' => $subc->saldo,
             'link' => $subc->url()
         );
      }
      
      header('Content-Type: application/json');
      echo json_encode( array('query' => $_REQUEST['buscar_subcuenta'], 'suggestions' => $json) );
   }
}
