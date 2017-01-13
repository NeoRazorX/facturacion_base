<?php
/*
 * This file is part of FacturaScripts
 * Copyright (C) 2017  Carlos Garcia Gomez  neorazorx@gmail.com
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

require_model('albaran_cliente.php');
require_model('articulo_traza.php');
require_model('factura_cliente.php');

/**
 * Description of ventas_trazabilidad
 *
 * @author Carlos Garcia Gomez
 */
class ventas_trazabilidad extends fs_controller
{
   public $disponibles;
   public $documento;
   public $lineas;
   public $tipo;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Trazabilidad', 'ventas', FALSE, FALSE);
   }
   
   protected function private_core()
   {
      $this->documento = FALSE;
      if( isset($_GET['doc']) AND isset($_GET['id']) )
      {
         if($_GET['doc'] == 'albaran')
         {
            $alb = new albaran_cliente();
            $this->documento = $alb->get($_GET['id']);
            $this->tipo = FS_ALBARAN.' de venta';
         }
         else if($_GET['doc'] == 'factura')
         {
            $fac = new factura_cliente();
            $this->documento = $fac->get($_GET['id']);
            $this->tipo = 'factura de venta';
         }
      }
      
      if($this->documento)
      {
         if( isset($_POST['asignar']) )
         {
            $this->asignar();
         }
         
         $this->get_lineas();
      }
      else
      {
         $this->new_error_msg('Documento no encontrado.', 'error', FALSE, FALSE);
      }
   }
   
   public function url()
   {
      if($this->documento)
      {
         if( get_class_name($this->documento) == 'albaran_cliente' )
         {
            return parent::url().'&doc=albaran&id='.$this->documento->idalbaran;
         }
         else
            return parent::url().'&doc=factura&id='.$this->documento->idfactura;
      }
      else
         return parent::url();
   }
   
   private function asignar()
   {
      $art0 = new articulo();
      $at0 = new articulo_traza();
      
      $ok = TRUE;
      foreach($this->documento->get_lineas() as $lindoc)
      {
         if( isset($_POST['idtraza_'.$lindoc->idlinea]) )
         {
            foreach($_POST['idtraza_'.$lindoc->idlinea] as $id)
            {
               $traza = $at0->get($id);
               if($traza)
               {
                  $traza->fecha_salida = $this->documento->fecha;
                  
                  if( get_class_name($this->documento) == 'albaran_cliente' )
                  {
                     $traza->idlalbventa = $lindoc->idlinea;
                  }
                  else
                  {
                     $traza->idlfacventa = $lindoc->idlinea;
                  }
                  
                  if( !$traza->save() )
                  {
                     $this->new_error_msg('Error al asignar el lote o número de serie.');
                     $ok = FALSE;
                  }
               }
               else
               {
                  $this->new_error_msg('Traza no encontrada.');
                  $ok = FALSE;
               }
            }
         }
      }
      
      if($ok)
      {
         header('Location: '.$this->documento->url());
      }
   }
   
   private function get_lineas()
   {
      $art0 = new articulo();
      $at0 = new articulo_traza();
      $this->disponibles = array();
      $this->lineas = array();
      
      /// ¿Existen ya las lineas de trazabilidad para este documento?
      foreach($this->documento->get_lineas() as $lindoc)
      {
         if($lindoc->referencia)
         {
            $articulo = $art0->get($lindoc->referencia);
            if($articulo)
            {
               if($articulo->trazabilidad)
               {
                  $num = 0;
                  if( get_class_name($this->documento) == 'albaran_cliente' )
                  {
                     foreach($at0->all_from_linea('idlalbventa', $lindoc->idlinea) as $traza)
                     {
                        $this->lineas[] = $traza;
                        $num++;
                     }
                  }
                  else
                  {
                     foreach($at0->all_from_linea('idlfacventa', $lindoc->idlinea) as $traza)
                     {
                        $this->lineas[] = $traza;
                        $num++;
                     }
                  }
                  
                  if($num < $lindoc->cantidad)
                  {
                     $this->disponibles[$articulo->referencia] = array(
                         'id' => $lindoc->idlinea,
                         'cantidad' => $lindoc->cantidad - $num,
                         'trazas' => $at0->all_from_ref($articulo->referencia, TRUE)
                     );
                  }
               }
            }
         }
      }
   }
}
