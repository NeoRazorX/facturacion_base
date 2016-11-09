<?php
/*
 * This file is part of FacturaScripts
 * Copyright (C) 2013-2016  Carlos Garcia Gomez  neorazorx@gmail.com
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
require_model('familia.php');
require_model('fabricante.php');
require_model('impuesto.php');
require_model('linea_transferencia_stock.php');
require_model('tarifa.php');
require_model('transferencia_stock.php');

class ventas_articulos extends fs_controller
{
   public $allow_delete;
   public $almacenes;
   public $b_bloqueados;
   public $b_codalmacen;
   public $b_codfabricante;
   public $b_codfamilia;
   public $b_codtarifa;
   public $b_constock;
   public $b_orden;
   public $b_publicos;
   public $b_subfamilias;
   public $b_url;
   public $familia;
   public $fabricante;
   public $impuesto;
   public $mostrar_tab_tarifas;
   public $offset;
   public $resultados;
   public $total_resultados;
   public $tarifa;
   public $transferencia_stock;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Artículos', 'ventas', FALSE, TRUE);
   }
   
   protected function private_core()
   {
      /// ¿El usuario tiene permiso para eliminar en esta página?
      $this->allow_delete = $this->user->allow_delete_on(__CLASS__);
      
      $almacen = new almacen();
      $this->almacenes = $almacen->all();
      $articulo = new articulo();
      $this->familia = new familia();
      $this->fabricante = new fabricante();
      $this->impuesto = new impuesto();
      $this->tarifa = new tarifa();
      $this->transferencia_stock = new transferencia_stock();
      
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
         $tar0->mincoste = isset($_POST['mincoste']);
         $tar0->maxpvp = isset($_POST['maxpvp']);
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
               $this->new_message("Tarifa ".$tar0->codtarifa." eliminada correctamente.", TRUE);
            }
            else
               $this->new_error_msg("¡Imposible eliminar la tarifa!");
         }
         else
            $this->new_error_msg("¡La tarifa no existe!");
      }
      else if( isset($_POST['referencia']) AND isset($_POST['codfamilia']) AND isset($_POST['codimpuesto']) )
      {
         /// nuevo artículo
         $this->save_codimpuesto( $_POST['codimpuesto'] );
         
         $art0 = $articulo->get($_POST['referencia']);
         if($art0)
         {
            $this->new_error_msg('Ya existe el artículo <a href="'.$art0->url().'">'.$art0->referencia.'</a>');
         }
         else
         {
            if($_POST['referencia'] == '')
            {
               $articulo->referencia = $articulo->get_new_referencia();
            }
            else
            {
               $articulo->referencia = $_POST['referencia'];
            }
            $articulo->descripcion = $_POST['descripcion'];
            $articulo->nostock = isset($_POST['nostock']);
            
            if($_POST['codfamilia'] != '')
            {
               $articulo->codfamilia = $_POST['codfamilia'];
            }
            
            if($_POST['codfabricante'] != '')
            {
               $articulo->codfabricante = $_POST['codfabricante'];
            }
            
            $articulo->set_impuesto($_POST['codimpuesto']);
            if( isset($_POST['coniva']) )
            {
               $articulo->set_pvp_iva( floatval($_POST['pvp']) );
            }
            else
            {
               $articulo->set_pvp( floatval($_POST['pvp']) );
            }
            
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
               $this->new_message("Articulo ".$art->referencia." eliminado correctamente.", TRUE);
            }
            else
               $this->new_error_msg("¡Error al eliminarl el articulo!");
         }
      }
      else if( isset($_POST['origen']) )
      {
         /// nueva transferencia de stock
         $this->transferencia_stock->usuario = $this->user->nick;
         $this->transferencia_stock->codalmaorigen = $_POST['origen'];
         $this->transferencia_stock->codalmadestino = $_POST['destino'];
         
         if( $this->transferencia_stock->save() )
         {
            $this->new_message('Datos guardados correctamente.');
            header('Location: '.$this->transferencia_stock->url());
         }
         else
         {
            $this->new_error_msg('Error al guardar los datos.');
         }
      }
      else if( isset($_GET['delete_transf']) )
      {
         $transf = $this->transferencia_stock->get($_GET['delete_transf']);
         if($transf)
         {
            $ok = TRUE;
            
            /// eliminamos las líneas
            $ltf = new linea_transferencia_stock();
            foreach($ltf->all_from_transferencia($transf->idtrans) as $lin)
            {
               if( $lin->delete() )
               {
                  /// movemos el stock
                  $art = $articulo->get($lin->referencia);
                  if($art)
                  {
                     $art->sum_stock($transf->codalmadestino, 0 - $lin->cantidad);
                     $art->sum_stock($transf->codalmaorigen, $lin->cantidad);
                  }
               }
               else
               {
                  $this->new_error_msg('Error al eliminar la línea con referencia '.$lin->referencia);
                  $ok = FALSE;
               }
            }
            
            if($ok)
            {
               if( $transf->delete() )
               {
                  $this->new_message('Transferencia eliminada correctamente.');
               }
               else
               {
                  $this->new_error_msg('Error al eliminar la transferencia.');
               }
            }
         }
         else
         {
            $this->new_error_msg('Transferencia no encontrada.');
         }
      }
      
      
      /// obtenemos los datos para la búsqueda
      $this->offset = 0;
      if( isset($_REQUEST['offset']) )
      {
         $this->offset = intval($_REQUEST['offset']);
      }
      
      $this->b_codalmacen = '';
      if( isset($_REQUEST['b_codalmacen']) )
      {
         $this->b_codalmacen = $_REQUEST['b_codalmacen'];
      }
      
      $this->b_codfamilia = '';
      $this->b_subfamilias = FALSE;
      if( isset($_REQUEST['b_codfamilia']) )
      {
         $this->b_codfamilia = $_REQUEST['b_codfamilia'];
         $this->b_subfamilias = isset($_REQUEST['b_subfamilias']);
      }
      
      $this->b_codfabricante = '';
      if( isset($_REQUEST['b_codfabricante']) )
      {
         $this->b_codfabricante = $_REQUEST['b_codfabricante'];
      }
      
      $this->b_constock = isset($_REQUEST['b_constock']);
      $this->b_bloqueados = isset($_REQUEST['b_bloqueados']);
      $this->b_publicos = isset($_REQUEST['b_publicos']);
      
      $this->b_codtarifa = '';
      if( isset($_REQUEST['b_codtarifa']) )
      {
         $this->b_codtarifa = ($_REQUEST['b_codtarifa']);
         setcookie('b_codtarifa', $this->b_codtarifa, time()+FS_COOKIES_EXPIRE);
      }
      else if( isset($_COOKIE['b_codtarifa']) )
      {
         $this->b_codtarifa = $_COOKIE['b_codtarifa']; 
      }
      
      $this->b_orden = 'refmin';
      if( isset($_REQUEST['b_orden']) )
      {
         $this->b_orden = $_REQUEST['b_orden'];
         setcookie('ventas_articulos_orden', $this->b_orden, time()+FS_COOKIES_EXPIRE);
      }
      else if( isset($_COOKIE['ventas_articulos_orden']) )
      {
         $this->b_orden = $_COOKIE['ventas_articulos_orden'];
      }
      
      $this->b_url = $this->url()."&query=".$this->query
              ."&b_codfabricante=".$this->b_codfabricante
              ."&b_codalmacen=".$this->b_codalmacen
              ."&b_codfamilia=".$this->b_codfamilia
              ."&b_codtarifa=".$this->b_codtarifa;
      
      if($this->b_subfamilias)
      {
         $this->b_url .= '&b_subfamilias=TRUE';
      }
      
      if($this->b_constock)
      {
         $this->b_url .= '&b_constock=TRUE';
      }
      
      if($this->b_bloqueados)
      {
         $this->b_url .= '&b_bloqueados=TRUE';
      }
      
      if($this->b_publicos)
      {
         $this->b_url .= '&b_publicos=TRUE';
      }
      
      $this->search_articulos();
   }

   private function search_articulos()
   {
      $this->resultados = array();
      $this->num_resultados = 0;
      $query = $this->empresa->no_html( mb_strtolower($this->query, 'UTF8') );
      $sql = ' FROM articulos ';
      $where = ' WHERE ';
      
      if($this->query != '')
      {
         $sql .= $where;
         if( is_numeric($query) )
         {
            /// ¿La búsqueda son números?
            $sql .= "(referencia = ".$this->empresa->var2str($query)
                    . " OR referencia LIKE '%".$query."%'"
                    . " OR partnumber LIKE '%".$query."%'"
                    . " OR equivalencia LIKE '%".$query."%'"
                    . " OR descripcion LIKE '%".$query."%'"
                    . " OR codbarras = ".$this->empresa->var2str($query).")";
         }
         else
         {
            /// ¿La búsqueda son varias palabras?
            $palabras = explode(' ', $query);
            if( count($palabras) > 1 )
            {
               $sql .= "(lower(referencia) = ".$this->empresa->var2str($query)
                       . " OR lower(referencia) LIKE '%".$query."%'"
                       . " OR lower(partnumber) LIKE '%".$query."%'"
                       . " OR lower(equivalencia) LIKE '%".$query."%'"
                       . " OR (";
               
               foreach($palabras as $i => $pal)
               {
                  if($i == 0)
                  {
                     $sql .= "lower(descripcion) LIKE '%".$pal."%'";
                  }
                  else
                  {
                     $sql .= " AND lower(descripcion) LIKE '%".$pal."%'";
                  }
               }
               
               $sql .= "))";
            }
            else
            {
               $sql .= "(lower(referencia) = ".$this->empresa->var2str($query)
                       . " OR lower(referencia) LIKE '%".$query."%'"
                       . " OR lower(partnumber) LIKE '%".$query."%'"
                       . " OR lower(equivalencia) LIKE '%".$query."%'"
                       . " OR lower(codbarras) = ".$this->empresa->var2str($query)
                       . " OR lower(descripcion) LIKE '%".$query."%')";
            }
         }
         $where = ' AND ';
      }
      
      if($this->b_codfamilia != '')
      {
         if($this->b_subfamilias)
         {
            $sql .= $where."codfamilia IN (";
            $coma = '';
            foreach($this->get_subfamilias($this->b_codfamilia) as $fam)
            {
               $sql .= $coma.$this->empresa->var2str($fam);
               $coma = ',';
            }
            $sql .= ")";
         }
         else
         {
            $sql .= $where."codfamilia = ".$this->empresa->var2str($this->b_codfamilia);
         }
         $where = ' AND ';
      }
      
      if($this->b_codfabricante != '')
      {
         $sql .= $where."codfabricante = ".$this->empresa->var2str($this->b_codfabricante);
         $where = ' AND ';
      }
      
      if($this->b_constock)
      {
         if($this->b_codalmacen == '')
         {
            $sql .= $where."stockfis > 0";
         }
         else
         {
            $sql .= $where."referencia IN (SELECT referencia FROM stocks WHERE cantidad > 0"
                    . " AND codalmacen = ".$this->empresa->var2str($this->b_codalmacen).')';
         }
         $where = ' AND ';
      }
      
      if($this->b_publicos)
      {
         $sql .= $where."publico = TRUE";
         $where = ' AND ';
      }
      
      if($this->b_bloqueados)
      {
         $sql .= $where."bloqueado = TRUE";
         $where = ' AND ';
      }
      else
      {
         $sql .= $where."bloqueado = FALSE";
         $where = ' AND ';
      }
      
      $order = 'referencia DESC';
      switch($this->b_orden)
      {
         case 'stockmin':
            $order = 'stockfis ASC';
            break;
         
         case 'stockmax':
            $order = 'stockfis DESC';
            break;
         
         case 'refmax':
            if( strtolower(FS_DB_TYPE) == 'postgresql' )
            {
               $order = 'referencia DESC';
            }
            else
            {
                $order = 'lower(referencia) DESC';
            }
            break;
         
         case 'descmin':
            $order = 'descripcion ASC';
            break;
         
         case 'descmax':
            $order = 'descripcion DESC';
            break;
         
         case 'preciomin':
            $order = 'pvp ASC';
            break;
         
         case 'preciomax':
            $order = 'pvp DESC';
            break;
         
         default:
         case 'refmin':
            if( strtolower(FS_DB_TYPE) == 'postgresql' )
            {
               $order = 'referencia ASC';
            }
            else
            {
               $order = 'lower(referencia) ASC';
            }
            break;
      }
      
      $data = $this->db->select("SELECT COUNT(referencia) as total".$sql);
      if($data)
      {
         $this->total_resultados = intval($data[0]['total']);
         
         /// ¿Descargar o mostrar en pantalla?
         if( isset($_GET['download']) )
         {
            $this->download_resultados($sql, $order);
         }
         else
         {
            $data2 = $this->db->select_limit("SELECT *".$sql." ORDER BY ".$order, FS_ITEM_LIMIT, $this->offset);
            if($data2)
            {
               foreach($data2 as $i)
               {
                  $this->resultados[] = new articulo($i);
               }
               
               if($this->b_codalmacen != '')
               {
                  /// obtenemos el stock correcto
                  foreach($this->resultados as $i => $value)
                  {
                     $this->resultados[$i]->stockfis = 0;
                     foreach($value->get_stock() as $s)
                     {
                        if($s->codalmacen == $this->b_codalmacen)
                        {
                           $this->resultados[$i]->stockfis = $s->cantidad;
                        }
                     }
                  }
               }
               
               if($this->b_codtarifa != '')
               {
                  /// aplicamos la tarifa
                  $tarifa = $this->tarifa->get($this->b_codtarifa);
                  if($tarifa)
                  {
                     $tarifa->set_precios($this->resultados);
                     
                     /// si la tarifa añade descuento, lo aplicamos al precio
                     foreach($this->resultados as $i => $value)
                     {
                        $this->resultados[$i]->pvp -= $value->pvp*$value->dtopor/100;
                     }
                  }
               }
            }
         }
      }
   }
   
   private function download_resultados($sql, $order)
   {
      /// desactivamos el motor de plantillas
      $this->template = FALSE;
      
      header("content-type:application/csv;charset=UTF-8");
      header("Content-Disposition: attachment; filename=\"articulos.csv\"");
      echo "referencia;codfamilia;codfabricante;descripcion;pvp;iva;codbarras;stock;coste\n";
      
      $offset2 = 0;
      $data2 = $this->db->select_limit("SELECT *".$sql." ORDER BY ".$order, 1000, $offset2);
      while($data2)
      {
         $resultados = array();
         foreach($data2 as $i)
         {
            $resultados[] = new articulo($i);
         }
         
         if($this->b_codalmacen != '')
         {
            /// obtenemos el stock correcto
            foreach($resultados as $i => $value)
            {
               $resultados[$i]->stockfis = 0;
               foreach($value->get_stock() as $s)
               {
                  if($s->codalmacen == $this->b_codalmacen)
                  {
                     $resultados[$i]->stockfis = $s->cantidad;
                  }
               }
            }
         }
         
         if($this->b_codtarifa != '')
         {
            /// aplicamos la tarifa
            $tarifa = $this->tarifa->get($this->b_codtarifa);
            if($tarifa)
            {
               $tarifa->set_precios($resultados);
               
               /// si la tarifa añade descuento, lo aplicamos al precio
               foreach($resultados as $i => $value)
               {
                  $resultados[$i]->pvp -= $value->pvp*$value->dtopor/100;
               }
            }
         }
         
         /// escribimos los datos de los artículos
         foreach($resultados as $art)
         {
            echo $art->referencia.';';
            echo $art->codfamilia.';';
            echo $art->codfabricante.';';
            echo $this->fix_html( preg_replace('~[\r\n]+~', ' ', $art->descripcion) ).';';
            echo round($art->pvp, FS_NF0_ART).';';
            echo $art->get_iva().';';
            echo trim($art->codbarras).';';
            echo $art->stockfis.';';
            echo round($art->preciocoste(), FS_NF0_ART)."\n";
            
            $offset2++;
         }
         
         $data2 = $this->db->select_limit("SELECT *".$sql." ORDER BY ".$order, 1000, $offset2);
      }
   }
   
   public function paginas()
   {
      $url = $this->b_url.'&b_orden='.$this->b_orden;
      
      $paginas = array();
      $i = 0;
      $num = 0;
      $actual = 1;
      $total = $this->total_resultados;
      
      /// añadimos todas la página
      while($num < $total)
      {
         $paginas[$i] = array(
             'url' => $url."&offset=".($i*FS_ITEM_LIMIT),
             'num' => $i + 1,
             'actual' => ($num == $this->offset)
         );
         
         if($num == $this->offset)
         {
            $actual = $i;
         }
         
         $i++;
         $num += FS_ITEM_LIMIT;
      }
      
      /// ahora descartamos
      foreach($paginas as $j => $value)
      {
         $enmedio = intval($i/2);
         
         /**
          * descartamos todo excepto la primera, la última, la de enmedio,
          * la actual, las 5 anteriores y las 5 siguientes
          */
         if( ($j>1 AND $j<$actual-5 AND $j!=$enmedio) OR ($j>$actual+5 AND $j<$i-1 AND $j!=$enmedio) )
         {
            unset($paginas[$j]);
         }
      }
      
      if( count($paginas) > 1 )
      {
         return $paginas;
      }
      else
      {
         return array();
      }
   }
   
   private function fix_html($txt)
   {
      $newt = str_replace('&lt;', '<', $txt);
      $newt = str_replace('&gt;', '>', $newt);
      $newt = str_replace('&quot;', "'", $newt);
      $newt = str_replace('&#39;', "'", $newt);
      $newt = str_replace(';', '.', $newt);
      return trim($newt);
   }
   
   private function get_subfamilias($cod)
   {
      $familias = array($cod);
      
      $data = $this->db->select("SELECT codfamilia,madre FROM familias WHERE madre = ".$this->empresa->var2str($cod).";");
      if($data)
      {
         foreach($data as $d)
         {
            foreach($this->get_subfamilias($d['codfamilia']) as $subf)
            {
               $familias[] = $subf;
            }
         }
      }
      
      return $familias;
   }
}
