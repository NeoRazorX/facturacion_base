<?php

/*
 * Copyright (C) 2017 Joe Nilson <joenilson at gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
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
require_model('almacen.php');
require_model('inventario.php');
/**
 * Description of informe_articulo
 *
 * @author Joe Nilson <joenilson at gmail.com>
 */
class ver_articulo extends fs_controller
{
   public $ref;
   public $art;
   public $articulo;
   public $almacen;
   public $codalmacen;
   public $desde;
   public $hasta;
   public $inventario;
   public $resultados;
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Ver Articulo', 'informes', FALSE, FALSE, FALSE);
   }
   
   protected function private_core()
   {
      $this->art = new articulo();
      $this->almacen = new almacen();
      $this->inventario = new inventario();
      $ref_p = \filter_input(INPUT_POST, 'ref');
      $ref_g = \filter_input(INPUT_GET, 'ref');
      $ref = ($ref_p)?$ref_p:$ref_g;
      $this->ref = ($ref)?$ref:false;

      $this->desde = \date('01-m-Y');
      $desde_p = \filter_input(INPUT_POST, 'desde');
      $desde_g = \filter_input(INPUT_GET, 'desde');
      $desde = ($desde_p)?$desde_p:$desde_g;
      $this->desde = ($desde)?$desde:$this->desde;

      $this->hasta = \date('t-m-Y');
      $hasta_p = \filter_input(INPUT_POST, 'hasta');
      $hasta_g = \filter_input(INPUT_GET, 'hasta');
      $hasta = ($hasta_p)?$hasta_p:$hasta_g;
      $this->hasta = ($hasta)?$hasta:$this->hasta;
      
      $codalmacen_p = \filter_input(INPUT_POST, 'codalmacen');
      $codalmacen_g = \filter_input(INPUT_GET, 'codalmacen');
      $codalmacen = ($codalmacen_p)?$codalmacen_p:$codalmacen_g;
      $this->codalmacen = ($codalmacen)?$codalmacen:false;
      
      if($this->ref)
      {
         $this->articulo = $this->art->get($this->ref);
      }
      
      $this->resultados = $this->inventario->listar_movimientos($this->desde, $this->hasta, $this->ref, $this->codalmacen);
   }
   
   public function url()
   {
      if($this->ref)
      {
         $this->url = parent::url().'&ref='.$this->ref;
      }
      if($this->codalmacen)
      {
         $this->url .= '&codalmacen='.$this->codalmacen;
      }
   }
   
   public function paginas() {
      $conductor = ($this->conductor)?$this->conductor->licencia:'';
      $this->total_resultados = $this->distrib_transporte->total_transportes($this->empresa->id,$this->codalmacen,$this->desde,$this->hasta);
      $url = $this->url()."&mostrar=".$this->mostrar
         ."&query=".$this->query
         ."&desde=".$this->desde
         ."&hasta=".$this->hasta
         ."&conductor=".$conductor
         ."&codalmacen=".$this->codalmacen
         ."&offset=".$this->offset;

      $paginas = array();
      $i = 0;
      $num = 0;
      $actual = 1;

      if($this->mostrar == 'por_despachar')
      {
         $total = $this->total_pendientes('despachado');
      }
      elseif($this->mostrar == 'por_liquidar')
      {
         $total = $this->total_pendientes('liquidado');
      }
      elseif($this->mostrar == 'buscar')
      {
         $total = $this->num_resultados;
      }
      else
      {
         $total = $this->total_resultados;
      }

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

      return $paginas;
   }
}
