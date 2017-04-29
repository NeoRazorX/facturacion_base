<?php

/*
 * This file is part of facturacion_base
 * Copyright (C) 2013-2017  Carlos Garcia Gomez  neorazorx@gmail.com
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

require_model('agente.php');
require_model('cliente.php');
require_model('factura_cliente.php');
require_model('grupo_clientes.php');
require_model('forma_pago.php');
require_model('tarifa.php');

/**
 * Description of ventas_grupo
 *
 * @author Carlos Garcia Gomez
 */
class ventas_grupo extends fs_controller
{
   public $agente;
   public $cliente;
   public $grupo;
   public $grupo_clientes;
   public $forma_pago;
   public $mostrar;
   public $offset;
   public $resultados;
   public $tarifa;
   public $total_clientes;
   public $total_clientes_agregar;
   public $total_facturas;
   public $codgrupo;
   public $codpago;
   public $codagente;
   public $direccion;
   public $nombre;
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Grupo', 'ventas', FALSE, FALSE);
   }

   protected function private_core()
   {
      $this->agente = new agente();
      $this->cliente = new cliente();
      $this->grupo_clientes = new grupo_clientes();
      $this->forma_pago = new forma_pago();
      $this->mostrar = 'clientes';
      if( isset($_REQUEST['mostrar']) )
      {
         $this->mostrar = $_REQUEST['mostrar'];
      }

      $this->offset = 0;
      if( isset($_REQUEST['offset']) )
      {
         $this->offset = intval($_REQUEST['offset']);
      }

      $this->grupo = FALSE;
      $this->tarifa = FALSE;
      if( isset($_REQUEST['cod']) )
      {
         $grupo = new grupo_clientes();
         $this->grupo = $grupo->get($_REQUEST['cod']);
         $accion = \filter_input(INPUT_POST, 'accion');
         switch($accion)
         {
            case "agregar_clientes":
               $this->agregar_clientes();
               break;
            default:
               break;
         }
      }

      if($this->grupo)
      {
         $tar0 = new tarifa();
         $this->tarifa = $tar0->get($this->grupo->codtarifa);
         $this->total_clientes = 0;
         $this->total_facturas = 0;

         if($this->mostrar == 'clientes')
         {
            $this->resultados = $this->clientes_from_grupo($this->grupo->codgrupo, $this->offset);
         }
         elseif($this->mostrar == 'agregar_clientes')
         {
            $this->resultados = $this->buscar_clientes();
         }
         else
         {
            $this->resultados = $this->facturas_from_grupo($this->grupo->codgrupo, $this->offset);
         }
      }
      else
      {
         $this->new_error_msg('Grupo no encontrado.', 'error', FALSE, FALSE);
      }
   }

   public function url()
   {
      if($this->grupo)
      {
         return $this->grupo->url();
      }
      else
      {
         return parent::url();
      }
   }

   public function agregar_clientes()
   {
      $codigos_clientes = \filter_input(INPUT_POST, 'clientes');
      $array_clientes = explode(",", $codigos_clientes);
      $correcto = 0;
      $error = 0;
      foreach($array_clientes as $cod)
      {
         $cliente = $this->cliente->get($cod);
         if($cliente)
         {
            $cliente->codgrupo = $this->grupo->codgrupo;
            if($cliente->save())
            {
               $correcto++;
            }
            else
            {
               $error++;
            }
         }
      }
      $mensaje = "De ".count($array_clientes)." recibidos, se agregaron: ".$correcto." y se tuvieron problemas con ".$error;
      $this->template = FALSE;
      header('Content-Type: application/json');
      $data['mensaje']=$mensaje;
      echo json_encode($data);
   }

   /**
    * Buscamos todos los clientes, por defecto si no se busca por ningún criterio
    * mostramos los clientes que no tienen asignado un grupo
    * @return \cliente
    */
   private function buscar_clientes()
   {
      /**
       * Verificamos las variables si vienen por POST o GET
       * ya que REQUEST no está recomendado por el consumo de recursos
       */
      $p_codgrupo = \filter_input(INPUT_POST, 'b_codgrupo');
      $g_codgrupo = \filter_input(INPUT_GET, 'b_codgrupo');
      $this->codgrupo = ($p_codgrupo)?$p_codgrupo:$g_codgrupo;
      $p_codpago = \filter_input(INPUT_POST, 'b_codpago');
      $g_codpago = \filter_input(INPUT_GET, 'b_codpago');
      $this->codpago = ($p_codpago)?$p_codpago:$g_codpago;
      $p_codagente = \filter_input(INPUT_POST, 'b_codagente');
      $g_codagente = \filter_input(INPUT_GET, 'b_codagente');
      $this->codagente = ($p_codagente)?$p_codagente:$g_codagente;
      $p_direccion = \filter_input(INPUT_POST, 'b_direccion');
      $g_direccion = \filter_input(INPUT_GET, 'b_direccion');
      $this->direccion = ($p_direccion)?$p_direccion:$g_direccion;
      $p_nombre = \filter_input(INPUT_POST, 'b_nombre');
      $g_nombre = \filter_input(INPUT_GET, 'b_nombre');
      $this->nombre = ($p_nombre)?$p_nombre:$g_nombre;
      $query = "clientes.codcliente = dirclientes.codcliente";
      $query.= " AND (clientes.codgrupo != ".$this->grupo_clientes->var2str($this->grupo->codgrupo)." OR clientes.codgrupo IS NULL) ";
      if($this->codgrupo)
      {
         $query.= " AND ";
         $query.= "codgrupo = ".$this->cliente->var2str($this->codgrupo);
      }
      if($this->codpago)
      {
         $query.= (!empty($query))?" AND ":"";
         $query.= "codpago = ".$this->cliente->var2str($this->codpago);
      }
      if($this->codagente)
      {
         $query.= " AND ";
         $query.= "codagente = ".$this->cliente->var2str($this->codagente);
      }
      if($this->direccion)
      {
         $query.= " AND ";
         $query.= "lower(direccion) like '%". strtolower($this->direccion)."%'";
      }
      if($this->nombre)
      {
         $query.= " AND ";
         $query.= "(lower(nombre) like '%". strtolower($this->nombre)."%' OR ";
         $query.= "lower(razonsocial) like '%". strtolower($this->nombre)."%')";
      }

      $sql_cantidad = "SELECT count(clientes.codcliente) as total FROM clientes, dirclientes ".
              " WHERE ".$query.";";
      $cantidad = $this->db->select($sql_cantidad);
      $this->total_clientes_agregar = $cantidad[0]['total'];

      $sql_datos = "SELECT clientes.*, dirclientes.direccion FROM clientes, dirclientes WHERE ".$query.
            " ORDER BY razonsocial,nombre,clientes.codcliente";

      $data = $this->db->select_limit($sql_datos, FS_ITEM_LIMIT, $this->offset);
      $lista = array();
      if($data){
         foreach($data as $d){
            $item = new cliente($d);
            $item->direccion = $d['direccion'];
            $lista[] = $item;
         }
      }

      return $lista;
   }

   private function clientes_from_grupo($cod, $offset)
   {
      $clist = array();
      $sql = "FROM clientes WHERE codgrupo = ".$this->grupo->var2str($cod);

      $data = $this->db->select('SELECT COUNT(*) as total '.$sql);
      if($data)
      {
         $this->total_clientes = intval($data[0]['total']);

         $data = $this->db->select_limit('SELECT * '.$sql." ORDER BY nombre ASC", FS_ITEM_LIMIT, $offset);
         if($data)
         {
            foreach($data as $d)
            {
               $clist[] = new cliente($d);
            }
         }
      }

      return $clist;
   }

   private function facturas_from_grupo($cod, $offset)
   {
      $clist = array();
      $sql = "FROM facturascli WHERE codcliente IN (SELECT codcliente FROM clientes WHERE codgrupo = ".$this->grupo->var2str($cod).")";

      $data = $this->db->select('SELECT COUNT(*) as total '.$sql);
      if($data)
      {
         $this->total_facturas = intval($data[0]['total']);

         $data = $this->db->select_limit('SELECT * '.$sql.' ORDER BY fecha DESC, idfactura DESC', FS_ITEM_LIMIT, $offset);
         if($data)
         {
            foreach($data as $d)
            {
               $clist[] = new factura_cliente($d);
            }
         }
      }

      return $clist;
   }

   public function paginas()
   {
      $url = $this->url()."&mostrar=".$this->mostrar;

      $paginas = array();
      $i = 0;
      $num = 0;
      $actual = 1;

      if($this->mostrar == 'clientes')
      {
         $total = $this->total_clientes;
      }
      elseif($this->mostrar == 'agregar_clientes')
      {
         $total = $this->total_clientes_agregar;
         $url.="&b_codgrupo=".$this->codgrupo
                 ."&b_codpago=".$this->codpago
                 ."&b_codagente=".$this->codagente
                 ."&b_direccion=".$this->direccion
                 ."&b_nombre=".$this->nombre;
      }
      else
      {
         $total = $this->total_facturas;
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
}
