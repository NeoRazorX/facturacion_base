<?php

/*
 * This file is part of FacturaScripts
 * Copyright (C) 2016  Carlos Garcia Gomez  neorazorx@gmail.com
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

require_model('almacen.php');
require_model('articulo.php');
require_model('fabricante.php');
require_model('familia.php');
require_model('linea_transferencia_stock.php');
require_model('transferencia_stock.php');

/**
 * Description of editar_transferencia_stock
 *
 * @author carlos
 */
class editar_transferencia_stock extends fs_controller
{
   public $allow_delete;
   public $almacen;
   public $fabricante;
   public $familia;
   public $lineas;
   public $transferencia;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Editar transferencia', 'ventas', FALSE, FALSE);
   }
   
   protected function private_core()
   {
      $this->allow_delete = $this->user->allow_delete_on(__CLASS__);
      $this->almacen = new almacen();
      $this->fabricante = new fabricante();
      $this->familia = new familia();
      $this->transferencia = FALSE;
      
      if( isset($_REQUEST['id']) )
      {
         $ts = new transferencia_stock();
         $this->transferencia = $ts->get($_REQUEST['id']);
      }
      
      if($this->query != '')
      {
         $this->new_search();
      }
      else if($this->transferencia)
      {
         if( isset($_POST['numlineas']) )
         {
            $this->modificar();
         }
         
         $lin = new linea_transferencia_stock();
         $this->lineas = $lin->all_from_transferencia($this->transferencia->idtrans);
      }
      else
      {
         $this->new_error_msg('Transferencia no encontrada.', 'error', FALSE, FALSE);
      }
   }
   
   private function new_search()
   {
      /// desactivamos la plantilla HTML
      $this->template = FALSE;
      
      $stock = new stock();
      $articulo = new articulo();
      $codfamilia = '';
      if( isset($_REQUEST['codfamilia']) )
      {
         $codfamilia = $_REQUEST['codfamilia'];
      }
      $codfabricante = '';
      if( isset($_REQUEST['codfabricante']) )
      {
         $codfabricante = $_REQUEST['codfabricante'];
      }
      $con_stock = isset($_REQUEST['con_stock']);
      $this->results = $articulo->search($this->query, 0, $codfamilia, $con_stock, $codfabricante);
      
      /// añadimos la busqueda, el descuento, la cantidad, etc...
      foreach($this->results as $i => $value)
      {
         $this->results[$i]->query = $this->query;
         $this->results[$i]->cantidad = 1;
         
         /// añadimos el stock de cada almacén
         $this->results[$i]->origen = 0;
         if( isset($_REQUEST['origen']) )
         {
            $this->results[$i]->origen = $stock->total_from_articulo($this->results[$i]->referencia, $_REQUEST['origen']);
         }
         
         $this->results[$i]->destino = 0;
         if( isset($_REQUEST['destino']) )
         {
            $this->results[$i]->destino = $stock->total_from_articulo($this->results[$i]->referencia, $_REQUEST['destino']);
         }
      }
      
      header('Content-Type: application/json');
      echo json_encode($this->results);
   }
   
   private function modificar()
   {
      $this->transferencia->codalmaorigen = $_POST['codalmaorigen'];
      $this->transferencia->codalmadestino = $_POST['codalmadestino'];
      $this->transferencia->fecha = $_POST['fecha'];
      $this->transferencia->hora = $_POST['hora'];
      
      if( $this->transferencia->save() )
      {
         $ok = TRUE;
         
         $art0 = new articulo();
         $lin = new linea_transferencia_stock();
         $lineas = $lin->all_from_transferencia($this->transferencia->idtrans);
         $numlineas = floatval($_POST['numlineas']);
         
         /// eliminamos las líneas que ya no estén
         foreach($lineas as $l)
         {
            $encontrada = FALSE;
            for($num = 0; $num <= $numlineas; $num++)
            {
               if( isset($_POST['idlinea_'.$num]) )
               {
                  if($l->idlinea == intval($_POST['idlinea_'.$num]))
                  {
                     $encontrada = TRUE;
                     break;
                  }
               }
            }
            if(!$encontrada)
            {
               if( $l->delete() )
               {
                  /// movemos el stock
                  $articulo = $art0->get($l->referencia);
                  if($articulo)
                  {
                     $articulo->sum_stock($this->transferencia->codalmadestino, 0 - $l->cantidad);
                     $articulo->sum_stock($this->transferencia->codalmaorigen, $l->cantidad);
                  }
               }
               else
               {
                  $this->new_error_msg("¡Imposible eliminar la línea del artículo ".$l->referencia."!");
               }
            }
         }
         
         /// añadimos las nuevas / modificamos las demás
         for($i = 0; $i <= $numlineas; $i++)
         {
            if( isset($_POST['idlinea_'.$i]) )
            {
               $nlin = new linea_transferencia_stock();
               $nlin->idtrans = $this->transferencia->idtrans;
               
               foreach($lineas as $l)
               {
                  if($l->idlinea == intval($_POST['idlinea_'.$i]))
                  {
                     $nlin = $l;
                     break;
                  }
               }
               
               $nlin->referencia = $_POST['referencia_'.$i];
               $nlin->descripcion = $_POST['desc_'.$i];
               
               $cantidad0 = $nlin->cantidad;
               $nlin->cantidad = floatval($_POST['cantidad_'.$i]);
               
               if( $nlin->save() )
               {
                  /// movemos el stock
                  $articulo = $art0->get($nlin->referencia);
                  if($articulo)
                  {
                     $articulo->sum_stock($this->transferencia->codalmaorigen, $cantidad0 - $nlin->cantidad);
                     $articulo->sum_stock($this->transferencia->codalmadestino, $nlin->cantidad - $cantidad0);
                  }
               }
               else
               {
                  $this->new_error_msg('Error al guardar la línea para la referencia '.$nlin->referencia);
                  $ok = FALSE;
               }
            }
         }
         
         if($ok)
         {
            $this->new_message('Datos guardados correctamente.');
         }
      }
      else
      {
         $this->new_error_msg('Error al guardar los datos.');
      }
   }
}
