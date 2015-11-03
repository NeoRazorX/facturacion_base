<?php
/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2013-2015  Carlos Garcia Gomez  neorazorx@gmail.com
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

require_model('articulo.php');
require_model('familia.php');
require_model('fabricante.php');
require_model('impuesto.php');
require_model('tarifa.php');

class ventas_articulos extends fs_controller
{
   public $allow_delete;
   public $bloqueados;
   public $buscar;
   public $codfamilia;
   public $con_stock;
   public $familia;
   public $fabricante; 
   public $codfabricante;   
   public $impuesto;
   public $mostrar_tab_tarifas;
   public $offset;
   public $resultados;
   public $tarifa;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Artículos', 'ventas', FALSE, TRUE);
   }
   
   protected function private_core()
   {
      /// ¿El usuario tiene permiso para eliminar en esta página?
      $this->allow_delete = $this->user->allow_delete_on(__CLASS__);
      
      $articulo = new articulo();
      $this->familia = new familia();
      $this->fabricante = new fabricante();
      $this->impuesto = new impuesto();
      $this->tarifa = new tarifa();
      
      /**
       * Si hay alguna extensión de tipo config y texto no_tab_tarifas,
       * desactivamos la pestaña tarifas.
       */
      $this->mostrar_tab_tarifas = TRUE;
      foreach($this->extensions as $ext)
      {
         if($ext->type == 'config' AND $ext->text == 'no_tab_tarifas')
         {
            $this->mostrar_tab_tarifas = FALSE;
            break;
         }
      }
      
      if( isset($_POST['codtarifa']) )
      {
         /// crear/editar tarifa
         $tar0 = $this->tarifa->get($_POST['codtarifa']);
         if( !$tar0 )
         {
            $tar0 = new tarifa();
            $tar0->codtarifa = $_POST['codtarifa'];
         }
         $tar0->nombre = $_POST['nombre'];
         $tar0->aplicar_a = $_POST['aplicar_a'];
         $tar0->set_x( floatval($_POST['dtopor']) );
         $tar0->set_y( floatval($_POST['inclineal']) );
         
         if( $tar0->save() )
         {
            $this->new_message("Tarifa guardada correctamente.");
         }
         else
            $this->new_error_msg("¡Imposible guardar la tarifa!");
      }
      else if( isset($_GET['delete_tarifa']) )
      {
         /// eliminar tarifa
         $tar0 = $this->tarifa->get($_GET['delete_tarifa']);
         if($tar0)
         {
            if( $tar0->delete() )
            {
               $this->new_message("Tarifa borrada correctamente.");
            }
            else
               $this->new_error_msg("¡Imposible borrar la tarifa!");
         }
         else
            $this->new_error_msg("¡La tarifa no existe!");
      }
      else if( isset($_POST['referencia']) AND isset($_POST['codfamilia']) AND isset($_POST['codimpuesto']) )
      {
         /// nuevo artículo
         $this->save_codfamilia( $_POST['codfamilia'] );
         $this->save_codimpuesto( $_POST['codimpuesto'] );
         
         $art0 = $articulo->get($_POST['referencia']);
         if($art0)
         {
            $this->new_error_msg('Ya existe el artículo <a href="'.$art0->url().'">'.$art0->referencia.'</a>');
         }
         else
         {
            $articulo->referencia = $_POST['referencia'];
            $articulo->descripcion = $_POST['referencia'];
            $articulo->nostock = isset($_POST['nostock']);
            
            if($_POST['codfamilia'] != '')
            {
               $articulo->codfamilia = $_POST['codfamilia'];
            }
            
            if($_POST['codfabricante'] != '')
            {
               $articulo->codfabricante = $_POST['codfabricante'];
            }
            
            $articulo->set_pvp( floatval($_POST['pvp']) );
            $articulo->set_impuesto($_POST['codimpuesto']);
            if( $articulo->save() )
            {
               header('location: '.$articulo->url());
            }
            else
               $this->new_error_msg("¡Error al crear el articulo!");
         }
      }
      else if( isset($_GET['delete']) )
      {
         /// eliminar artículo
         $art = $articulo->get($_GET['delete']);
         if($art)
         {
            if( $art->delete() )
            {
               $this->new_message("Articulo ".$art->referencia." eliminado correctamente.");
            }
            else
               $this->new_error_msg("¡Error al eliminarl el articulo!");
         }
      }
      
      /// recogemos los datos necesarios para la búsqueda
      $this->buscar = FALSE;
      $this->codfamilia = '';
      if( isset($_REQUEST['codfamilia']) )
      {
         $this->codfamilia = $_REQUEST['codfamilia'];
         $this->buscar = TRUE;
      }
      
      $this->codfabricante = '';
      if( isset($_REQUEST['codfabricante']) )
      {
         $this->codfabricante = $_REQUEST['codfabricante'];
         $this->buscar = TRUE;
      }
      
      $this->con_stock = isset($_REQUEST['con_stock']);
      $this->bloqueados = isset($_REQUEST['bloqueados']);
      
      if( isset($_REQUEST['query']) OR $this->con_stock OR $this->bloqueados )
      {
         $this->buscar = TRUE;
      }
      
      $this->offset = 0;
      if( isset($_GET['offset']) )
      {
         $this->offset = intval($_GET['offset']);
      }
      
      if( isset($_GET['solo_stock']) )
      {
         $this->resultados = $articulo->search('', $this->offset, '', TRUE);
      }
      else if( isset($_GET['public']) )
      {
         $this->resultados = $articulo->all_publico($this->offset);
      }
      else
      {
         $this->resultados = $articulo->search(
                 $this->query,
                 $this->offset,
                 $this->codfamilia,
                 $this->con_stock,
                 $this->codfabricante,
                 $this->bloqueados
         );
      }
   }
   
   public function anterior_url()
   {
      $url = '';
      $extra = '';
      
      if( isset($_GET['public']) )
      {
         $extra .= '&public=TRUE';
      }
      else if( isset($_GET['solo_stock']) )
      {
         $extra .= '&solo_stock=TRUE';
      }
      else if($this->buscar)
      {
         if($this->query != '')
         {
            $extra .= '&query='.$this->query;
         }
         
         if($this->codfamilia != '')
         {
            $extra .= '&codfamilia='.$this->codfamilia;
         }
         
         if($this->codfabricante != '')
         {
            $extra .= '&codfabricante='.$this->codfabricante;
         }
         
         if($this->con_stock)
         {
            $extra .= '&con_stock=TRUE';
         }
         
         if($this->bloqueados)
         {
            $extra .= '&bloqueados=TRUE';
         }
      }
      
      if($this->offset > 0)
      {
         $url = $this->url()."&offset=".($this->offset-FS_ITEM_LIMIT).$extra;
      }
      
      return $url;
   }
   
   public function siguiente_url()
   {
      $url = '';
      $extra = '';
      
      if( isset($_GET['public']) )
      {
         $extra .= '&public=TRUE';
      }
      else if( isset($_GET['solo_stock']) )
      {
         $extra .= '&solo_stock=TRUE';
      }
      else if($this->buscar)
      {
         if($this->query != '')
         {
            $extra .= '&query='.$this->query;
         }
         
         if($this->codfamilia != '')
         {
            $extra .= '&codfamilia='.$this->codfamilia;
         }
         
         if($this->codfabricante != '')
         {
            $extra .= '&codfabricante='.$this->codfabricante;
         }
         
         if($this->con_stock)
         {
            $extra .= '&con_stock=TRUE';
         }
         
         if($this->bloqueados)
         {
            $extra .= '&bloqueados=TRUE';
         }
      }
      
      if( count($this->resultados) == FS_ITEM_LIMIT )
      {
         $url = $this->url()."&offset=".($this->offset+FS_ITEM_LIMIT).$extra;
      }
      
      return $url;
   }
   
   public function total_articulos()
   {
      $data = $this->db->select("SELECT COUNT(referencia) as total FROM articulos;");
      if($data)
      {
         return intval($data[0]['total']);
      }
      else
         return 0;
   }
}
