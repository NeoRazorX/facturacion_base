<?php
/*
 * This file is part of facturacion_base
 * Copyright (C) 2014-2017  Carlos Garcia Gomez  neorazorx@gmail.com
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

require_once 'plugins/facturacion_base/extras/fbase_controller.php';
require_model('almacen.php');
require_model('articulo.php');
require_model('cliente.php');
require_model('familia.php');
require_model('impuesto.php');
require_model('linea_factura_cliente.php');
require_model('linea_factura_proveedor.php');
require_model('regularizacion_stock.php');
require_model('stock.php');
require_model('inventario.php');

class informe_articulos extends fbase_controller
{
   public $agente;
   public $almacen;
   public $articulo;
   public $cantidades;
   public $codagente;
   public $codalmacen;
   public $codfamilia;
   public $codimpuesto;
   public $desde;
   public $documento;
   public $familia;
   public $hasta;
   public $impuesto;
   public $minimo;
   public $offset;
   public $pestanya;
   public $referencia;
   public $resultados;
   public $sin_vender;
   public $stats;
   public $stock;
   public $tipo_stock;
   public $top_ventas;
   public $top_compras;
   public $url_recarga;
   
   /**
    * para buscar el inventario
    * @var type date
    */
   public $ihasta;
   /**
    * para buscar el inventario
    * @var type date
    */
   public $idesde;
   /**
    * para buscar por almacen el inventario
    * @var type string or array
    */
   public $icodalmacen;

   public $inventario;
   public $inventario_setup;
   public $inventario_resultado;

   private $tablas;
   public $loop_horas;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Artículos', 'informes');
   }

   protected function private_core()
   {
      parent::private_core();

      $this->agente = new agente();
      $this->almacen = new almacen();
      $this->documento = 'facturascli';
      $this->familia = new familia();
      $this->stock = new stock();
      $this->tablas = $this->db->list_tables();
      $this->url_recarga = FALSE;

      $this->inventario = new inventario();

      $tab = \filter_input(INPUT_GET, 'tab');
      $this->pestanya = ($tab)?$tab:'stats';

      $this->codalmacen = FALSE;
      $codalmacen_p = \filter_input(INPUT_POST, 'codalmacen');
      $codalmacen_g = \filter_input(INPUT_GET, 'codalmacen');
      $codalmacen = ($codalmacen_p)?$codalmacen_p:$codalmacen_g;
      $this->codalmacen = ($codalmacen)?$codalmacen:$this->codalmacen;

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

      //Creamos un array para el selector de horas para cron
      for ($x = 0; $x < 25; $x++) {
          $this->loop_horas[] = str_pad($x, 2, "0", STR_PAD_LEFT);
      }
      
      $this->offset = 0;
      if( isset($_GET['offset']) )
      {
         $this->offset = intval($_GET['offset']);
      }

      if( isset($_REQUEST['buscar_referencia']) )
      {
         $this->buscar_referencia();
      }
      else if($this->pestanya == 'stats')
      {
         $this->articulo = new articulo();
         $this->stats = $this->stats();

         $linea_fac_cli = new linea_factura_cliente();
         $linea_fac_pro = new linea_factura_proveedor();

         $this->top_ventas = $this->top_articulo_faccli();
         $this->sin_vender = $this->sin_vender();
         $this->top_compras = $this->top_articulo_facpro();
      }
      else if($this->pestanya == 'stock')
      {
         $this->stocks();
      }
      else if($this->pestanya == 'impuestos')
      {
         $this->impuestos();
      }
      else if($this->pestanya == 'varios')
      {
         $this->varios();
      }
   }

   public function stocks()
   {
      /// forzamos la comprobación de la tabla de inventario
      $this->inventario->ultima_fecha();
      
      $this->opciones_inventario();
      
      $actualizar_informacion = \filter_input(INPUT_GET, 'opciones-inventario');
      if($actualizar_informacion)
      {
         $fsvar = new fs_var();
         $op_inventario_cron = \filter_input(INPUT_POST, 'inventario_cron');
         $op_inventario_programado = \filter_input(INPUT_POST, 'inventario_programado');
         $inventario_cron = ($op_inventario_cron == 'TRUE') ? "TRUE" : "FALSE";
         $inventario_programado = $op_inventario_programado;
         $inventario_config = array(
            'inventario_cron' => $inventario_cron,
            'inventario_programado' => $inventario_programado
         );
         if ($fsvar->array_save($inventario_config))
         {
            $this->new_message('Cambios guardados correctamente.');
         } 
         else
         {
            $this->new_error_message('No se pudieron grabar las opciones de calculo, por favor confirme que eligió una hora correcta.');
         }
         $this->opciones_inventario();
      }
      
      $this->tipo_stock = 'todo';
      $tipo_stock = \filter_input(INPUT_GET, 'tipo');
      $recalcular = \filter_input(INPUT_GET, 'recalcular');
      if($tipo_stock)
      {
         $this->tipo_stock = $tipo_stock;
      }
      else if($recalcular)
      {
         $this->recalcular_stock();
      }

      if($this->tipo_stock == 'reg')
      {
         /// forzamos la comprobación de la tabla stocks
         $reg = new regularizacion_stock();

         $this->resultados = $this->regularizaciones_stock($this->offset);
      }
      else if( isset($_GET['download']) )
      {
         $this->download_stock();
      }
      else
      {
         $this->resultados = $this->stock($this->offset, $this->tipo_stock);
      }
   }

   public function impuestos()
   {
      $this->impuesto = new impuesto();
      $this->codimpuesto = '';
      if( isset($_REQUEST['codimpuesto']) )
      {
         $this->codimpuesto = $_REQUEST['codimpuesto'];
      }

      /// ¿Hacemos cambio?
      $this->cambia_impuesto();
   }

   public function varios()
   {
      $this->cantidades = FALSE;
      $this->codfamilia = '';
      $this->minimo = '';

      $this->referencia = '';
      if( isset($_GET['ref']) )
      {
         $this->referencia = $_GET['ref'];
      }

      $this->resultados = array();
      if( isset($_POST['informe']) )
      {
         if($_POST['informe'] == 'listadomov')
         {
            $this->referencia = $_POST['referencia'];
            $this->codfamilia = $_POST['codfamilia'];
            $this->codagente = $_POST['codagente'];

            $this->informe_movimientos();
         }
         else if($_POST['informe'] == 'facturacion')
         {
            $this->documento = $_POST['documento'];
            $this->codfamilia = $_POST['codfamilia'];
            $this->cantidades = ($_POST['cantidades'] == 'TRUE');
            $this->minimo = $_POST['minimo'];

            $this->informe_facturacion();
         }
         else if($_POST['informe'] == 'ventascli')
         {
            $this->referencia = $_POST['referencia'];
            $this->codfamilia = $_POST['codfamilia'];
            $this->minimo = $_POST['minimo'];

            $this->informe_ventascli();
         }
      }
   }
   
   public function opciones_inventario()
   {
      $fsvar = new fs_var();
      $this->inventario_setup = $fsvar->array_get(
         array(
            'inventario_ultimo_proceso' => '',
            'inventario_cron' => '',
            'inventario_programado' => '',
            'inventario_procesandose' => 'FALSE',
            'inventario_usuario_procesando' => ''
         ), FALSE
      );
      $this->inventario_procesandose = ($this->inventario_setup['inventario_procesandose'] == 'TRUE') ? TRUE : FALSE;
      $this->inventario_usuario_procesando = ($this->inventario_setup['inventario_usuario_procesando']) ? $this->inventario_setup['inventario_usuario_procesando'] : FALSE;
      $this->inventario_cron = $this->inventario_setup['inventario_cron'];
      $this->inventario_programado = $this->inventario_setup['inventario_programado'];
   }

   private function recalcular_stock()
   {
      $lista_almacenes = (!$this->codalmacen)?$this->almacen->all():array($this->almacen->get($this->codalmacen));
      $fecha_desde = \filter_input(INPUT_GET, 'inv_desde');
      $this->inventario->fecha_inicio = ($fecha_desde)?\date('Y-m-d',strtotime($fecha_desde)):$this->inventario->fecha_inicio;
      $fechaFin = \date("Y-m-t", strtotime($this->inventario->fecha_inicio));
      $mesProceso = \date("m", strtotime($this->inventario->fecha_inicio));
      $anhoProceso = \date("Y", strtotime($this->inventario->fecha_inicio));
      $periodoProceso = \date("Y-m", strtotime($this->inventario->fecha_inicio));
      $fechaHoy = \date('Y-m-d');
      $periodoActual = \date('Y-m');
      $fechaSiguienteMes = \date('Y-m-d', mktime(0, 0, 0, $mesProceso+1, 1, $anhoProceso));
      $this->inventario->fecha_fin = ($periodoProceso == $periodoActual)?$fechaHoy:$fechaFin;
      $this->inventario->almacenes = $lista_almacenes;
      $this->inventario->procesar_inventario($this->user->nick);
      if($periodoProceso != $periodoActual)
      {
         $this->new_message('Recalculando stock de artículos, procesado el periodo del '
            .$this->inventario->fecha_inicio.' al '.$this->inventario->fecha_fin
            .'... recalculando el siguiente mes&nbsp;<i class="fa fa-spinner fa-pulse fa-fw"></i>...');
         $this->url_recarga = $this->url().'&tab=stock&recalcular=TRUE&codalmacen='.$this->codalmacen.'&inv_desde='.$fechaSiguienteMes;
      }
      else
      {
         $this->new_advice('Finalizado &nbsp; <span class="fa fa-ok" aria-hidden="true"></span>');
      }
      
   }

   private function recalcular_stock_old()
   {
      $articulo = new articulo();
      $continuar = FALSE;
      $offset = intval($_GET['offset']);

      if($this->codalmacen)
      {
         $this->new_message('Recalculando stock de artículos del almacen '.$this->codalmacen.'... '.$offset);
      }
      else
      {
         $this->new_message('Recalculando stock de artículos... '.$offset);
      }

      foreach($articulo->all($offset, 30) as $art)
      {
         $this->calcular_stock_real($art);
         $continuar = TRUE;
         $offset++;
      }

      if($continuar)
      {
         $this->url_recarga = $this->url().'&tab=stock&recalcular=TRUE&offset='.$offset.'&codalmacen='.$this->codalmacen;
      }
      else
      {
         $this->new_advice('Finalizado &nbsp; <span class="glyphicon glyphicon-ok" aria-hidden="true"></span>');
      }
   }

   private function download_stock()
   {
      $this->template = FALSE;

      header("content-type:application/csv;charset=UTF-8");
      header("Content-Disposition: attachment; filename=\"stock.csv\"");
      echo "almacen,referencia,descripcion,stock,stockmin,stockmax\n";

      $offset = 0;
      $resultados = $this->stock($offset, $this->tipo_stock);
      while( count($resultados) > 0 )
      {
         foreach($resultados as $res)
         {
            echo '"'.$res['codalmacen'].'","'.$res['referencia'].'","'.$res['descripcion'].'","'.
                    $res['cantidad'].'","'.$res['stockmin'].'","'.$res['stockmax']."\"\n";
            $offset++;
         }

         $resultados = $this->stock($offset, $this->tipo_stock);
      }
   }

   private function cambia_impuesto()
   {
      if( isset($_POST['new_codimpuesto']) )
      {
         if($_POST['new_codimpuesto'] != '')
         {
            $sql = "UPDATE articulos SET codimpuesto = ".$this->impuesto->var2str($_POST['new_codimpuesto']);
            if($this->codimpuesto == '')
            {
               $sql .= " WHERE codimpuesto IS NULL";
            }
            else
            {
               $sql .= " WHERE codimpuesto = ".$this->impuesto->var2str($this->codimpuesto);
            }

            if( $this->db->exec($sql) )
            {
               $this->new_message('cambios aplicados correctamente.');
            }
            else
            {
               $this->new_error_msg('Error al aplicar los cambios.');
            }
         }
      }

      /// buscamos en la tabla
      $sql = "SELECT * FROM articulos";
      if($this->codimpuesto == '')
      {
         $sql .= " WHERE codimpuesto IS NULL";
      }
      else
      {
         $sql .= " WHERE codimpuesto = ".$this->impuesto->var2str($this->codimpuesto);
      }

      $this->resultados = array();
      $data = $this->db->select_limit($sql.' ORDER BY referencia ASC', 1000, 0);
      if($data)
      {
         foreach($data as $d)
         {
            $this->resultados[] = new articulo($d);
         }
      }
   }

   private function stats()
   {
      $stats = array(
          'total' => 0,
          'con_stock' => 0,
          'bloqueados' => 0,
          'publicos' => 0,
          'factualizado' => Date('d-m-Y', strtotime(0) )
      );

      $sql = "SELECT GREATEST( COUNT(referencia), 0) as art,"
              ." GREATEST( SUM(case when stockfis > 0 then 1 else 0 end), 0) as stock,"
              ." GREATEST( SUM(case when bloqueado then 1 else 0 end), 0) as bloq,"
              ." GREATEST( SUM(case when publico then 1 else 0 end), 0) as publi,"
              ." MAX(factualizado) as factualizado FROM articulos;";

      $aux = $this->db->select($sql);
      if($aux)
      {
         $stats['total'] = intval($aux[0]['art']);
         $stats['con_stock'] = intval($aux[0]['stock']);
         $stats['bloqueados'] = intval($aux[0]['bloq']);
         $stats['publicos'] = intval($aux[0]['publi']);
         $stats['factualizado'] = Date('d-m-Y', strtotime($aux[0]['factualizado']) );
      }

      return $stats;
   }

   private function top_articulo_faccli()
   {
      /// buscamos el resultado en caché
      $toplist = $this->cache->get_array('faccli_top_articulos');
      if( !$toplist OR isset($_POST['desde']) )
      {
         $toplist = array();
         $articulo = new articulo();
         $sql = "SELECT l.referencia, SUM(l.cantidad) as unidades, SUM(l.pvptotal/f.tasaconv) as total"
                 . " FROM lineasfacturascli l, facturascli f"
                 . " WHERE l.idfactura = f.idfactura AND l.referencia IS NOT NULL"
                 . " AND f.fecha >= ". $articulo->var2str($this->desde)
                 . " AND f.fecha <= ". $articulo->var2str($this->hasta)
                 . " GROUP BY referencia"
                 . " ORDER BY unidades DESC";

         $lineas = $this->db->select_limit($sql, FS_ITEM_LIMIT, 0);
         if($lineas)
         {
            foreach($lineas as $l)
            {
               $art0 = $articulo->get($l['referencia']);
               if($art0)
               {
                  $toplist[] = array(
                      'articulo' => $art0,
                      'unidades' => floatval($l['unidades']),
                      'total' => $this->euro_convert( floatval($l['total']) ),
                      'beneficio' => $this->euro_convert( floatval($l['total']) ) - ( floatval($l['unidades']) * $art0->preciocoste() )
                  );
               }
            }
         }

         /// guardamos los resultados en caché
         $this->cache->set('faccli_top_articulos', $toplist, 300);
      }

      return $toplist;
   }

   private function sin_vender()
   {
      $toplist = $this->cache->get_array('top_articulos_sin_vender');
      if(!$toplist)
      {
         $articulo = new articulo();
         $sql = "SELECT * FROM articulos WHERE sevende = true"
                 . " AND bloqueado = false AND stockfis > 0 AND referencia NOT IN "
                 . "(SELECT DISTINCT(referencia) FROM lineasfacturascli WHERE referencia IS NOT NULL"
                 . " AND idfactura IN (SELECT idfactura FROM facturascli"
                 . " WHERE fecha >= ".$articulo->var2str(Date('1-1-Y'))."))"
                 . " ORDER BY stockfis DESC";

         $lineas = $this->db->select_limit($sql, FS_ITEM_LIMIT, 0);
         if($lineas)
         {
            foreach($lineas as $l)
            {
               $toplist[] = new articulo($l);
            }
         }

         /// guardamos los resultados en caché
         $this->cache->set('top_articulos_sin_vender', $toplist);
      }

      return $toplist;
   }

   private function top_articulo_facpro()
   {
      $toplist = $this->cache->get('facpro_top_articulos');
      if( !$toplist OR isset($_POST['desde']) )
      {
         $articulo = new articulo();
         $sql = "SELECT l.referencia, SUM(l.cantidad) as compras FROM lineasfacturasprov l, facturasprov f"
                 . " WHERE l.idfactura = f.idfactura AND l.referencia IS NOT NULL"
                 . " AND f.fecha >= ". $articulo->var2str($this->desde)
                 . " AND f.fecha <= ". $articulo->var2str($this->hasta)
                 . " GROUP BY referencia"
                 . " ORDER BY compras DESC";

         $lineas = $this->db->select_limit($sql, FS_ITEM_LIMIT, 0);
         if($lineas)
         {
            foreach($lineas as $l)
            {
               $art0 = $articulo->get($l['referencia']);
               if($art0)
               {
                  $toplist[] = array($art0, intval($l['compras']));
               }
            }
         }

         /// guardamos los resultados en caché
         $this->cache->set('facpro_top_articulos', $toplist);
      }

      return $toplist;
   }

   private function stock($offset = 0, $tipo = 'todo')
   {
      $slist = array();

      $sql = "SELECT codalmacen,s.referencia,a.descripcion,s.cantidad,a.stockmin,a.stockmax"
              . " FROM stocks s, articulos a WHERE s.referencia = a.referencia";

      if($tipo == 'min')
      {
         $sql .= " AND s.cantidad < a.stockmin";
      }
      else if($tipo == 'max')
      {
         $sql .= " AND a.stockmax > 0 AND s.cantidad > a.stockmax";
      }
      else if($tipo == 'constock')
      {
         $sql .= " AND s.cantidad != 0 ";
      }
      else if($tipo == 'sinstock')
      {
         $sql .= " AND s.cantidad = 0 ";
      }

      if($this->codalmacen)
      {
         $sql .= " AND s.codalmacen = " . $this->empresa->var2str($this->codalmacen);
      }

      $sql .= " ORDER BY referencia ASC";

      $data = $this->db->select_limit($sql, FS_ITEM_LIMIT, $offset);
      if($data)
      {
         foreach($data as $d)
         {
            $slist[] = $d;
         }
      }

      return $slist;
   }

   private function regularizaciones_stock($offset = 0)
   {
      $slist = array();

      $sql = "SELECT s.codalmacen,s.referencia,a.descripcion,r.cantidadini,r.cantidadfin,r.nick,r.motivo,r.fecha,r.hora "
              . "FROM stocks s, articulos a, lineasregstocks r WHERE r.idstock = s.idstock AND s.referencia = a.referencia";
      if($this->codalmacen)
      {
         $sql.=" AND codalmacen = ".$this->empresa->var2str($this->codalmacen);
      }
      $sql .= " ORDER BY fecha DESC, hora DESC";

      $data = $this->db->select_limit($sql, FS_ITEM_LIMIT, $offset);
      if($data)
      {
         foreach($data as $d)
         {
            $slist[] = $d;
         }
      }

      return $slist;
   }

   public function anterior_url()
   {
      $url = '';
      $extra = '&tab=stock&tipo='.$this->tipo_stock.'&codalmacen='.$this->codalmacen;

      if($this->offset > 0)
      {
         $url = $this->url()."&offset=".($this->offset-FS_ITEM_LIMIT).$extra;
      }

      return $url;
   }

   public function siguiente_url()
   {
      $url = '';
      $extra = '&tab=stock&tipo='.$this->tipo_stock.'&codalmacen='.$this->codalmacen;

      if(count($this->resultados) == FS_ITEM_LIMIT)
      {
         $url = $this->url()."&offset=".($this->offset+FS_ITEM_LIMIT).$extra;
      }

      return $url;
   }

   private function buscar_referencia()
   {
      /// desactivamos la plantilla HTML
      $this->template = FALSE;

      $articulo = new articulo();
      $json = array();
      foreach($articulo->search($_REQUEST['buscar_referencia']) as $art)
      {
         $json[] = array('value' => $art->referencia.' '.$art->descripcion(60), 'data' => $art->referencia);
      }

      header('Content-Type: application/json');
      echo json_encode( array('query' => $_REQUEST['buscar_referencia'], 'suggestions' => $json) );
   }

   private function informe_facturacion()
   {
      $sumar = 'pvptotal';
      if($this->cantidades)
      {
         $sumar = 'cantidad';
      }

      $sql = "SELECT l.referencia,f.fecha,SUM(".$sumar.") as total"
              . " FROM ".$this->documento." f, lineas".$this->documento." l"
              . " WHERE f.idfactura = l.idfactura"
              . " AND referencia IS NOT NULL AND referencia != ''"
              . " AND fecha >= ".$this->empresa->var2str($this->desde)
              . " AND fecha <= ".$this->empresa->var2str($this->hasta);

      if( is_numeric($this->minimo) )
      {
         $sql .= " AND ".$sumar." >= ".$this->empresa->var2str($this->minimo);
      }

      if($this->codfamilia != '')
      {
         $sql .= " AND referencia IN (SELECT referencia FROM articulos"
                 . " WHERE codfamilia IN (";
         $coma = '';
         foreach($this->get_subfamilias($this->codfamilia) as $fam)
         {
            $sql .= $coma.$this->empresa->var2str($fam);
            $coma = ',';
         }
         $sql .= "))";
      }

      $sql .= " GROUP BY referencia,fecha ORDER BY fecha DESC";

      $data = $this->db->select($sql);
      if($data)
      {
         $this->template = FALSE;

         header("content-type:application/csv;charset=UTF-8");
         header("Content-Disposition: attachment; filename=\"informe_facturacion.csv\"");
         echo "referencia;descripcion;año;ene;feb;mar;abr;may;jun;jul;ago;sep;oct;nov;dic;total;%VAR\n";

         $stats = array();
         foreach($data as $d)
         {
            $anyo = date('Y', strtotime($d['fecha']));
            $mes = date('n', strtotime($d['fecha']));
            if( !isset($stats[ $d['referencia'] ][ $anyo ]) )
            {
               $stats[ $d['referencia'] ][ $anyo ] = array(
                   1 => 0,
                   2 => 0,
                   3 => 0,
                   4 => 0,
                   5 => 0,
                   6 => 0,
                   7 => 0,
                   8 => 0,
                   9 => 0,
                   10 => 0,
                   11 => 0,
                   12 => 0,
                   13 => 0,
                   14 => 0
               );
            }

            $stats[ $d['referencia'] ][ $anyo ][ $mes ] += floatval($d['total']);
            $stats[ $d['referencia'] ][ $anyo ][13] += floatval($d['total']);
         }

         $art0 = new articulo();
         foreach($stats as $i => $value)
         {
            /// calculamos la variación
            $anterior = 0;
            foreach( array_reverse($value, TRUE) as $j => $value2 )
            {
               if($anterior > 0)
               {
                  $value[$j][14] = ($value2[13]*100/$anterior) - 100;
               }

               $anterior = $value2[13];
            }

            foreach($value as $j => $value2)
            {
               $articulo = $art0->get($i);
               if($articulo)
               {
                  echo '"'.$i.'";"'.fs_fix_html($articulo->descripcion()).'";'.$j;
               }
               else
               {
                  echo '"'.$i.'";"";'.$j;
               }

               foreach($value2 as $value3)
               {
                  echo ';'.number_format($value3, FS_NF0, ',', '');
               }

               echo "\n";
            }
            echo ";;;;;;;;;;;;;;;;\n";
         }
      }
      else
      {
         $this->new_message('Sin resultados.');
      }
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

   private function calcular_stock_real(&$articulo)
   {
      $total = 0;

      if($this->codalmacen)
      {
         foreach($this->get_movimientos($articulo->referencia) as $mov)
         {
            if($mov['codalmacen'] == $this->codalmacen)
            {
               $total = $mov['final'];
            }
         }

         if( !$articulo->set_stock($this->codalmacen, $total) )
         {
            $this->new_error_msg('Error al recarcular el stock del almacén '.$this->codalmacen.'.');
         }
      }
      else
      {
         foreach($this->almacen->all() as $alm)
         {
            $this->codalmacen = $alm->codalmacen;
            foreach($this->get_movimientos($articulo->referencia) as $mov)
            {
               if($mov['codalmacen'] == $alm->codalmacen)
               {
                  $total = $mov['final'];
               }
            }

            if( !$articulo->set_stock($alm->codalmacen, $total) )
            {
               $this->new_error_msg('Error al recarcular el stock del almacén '.$alm->codalmacen.'.');
            }
         }
      }
   }

   private function get_movimientos($ref, $desde = '', $hasta = '', $codagente = '')
   {
      $mlist = array();
      $regularizacion = new regularizacion_stock();

      foreach($regularizacion->all_from_articulo($ref) as $reg)
      {
         $anyadir = TRUE;
         if($desde)
         {
            if( strtotime($desde) > strtotime($reg->fecha) )
            {
               $anyadir = FALSE;
            }
         }

         if($hasta)
         {
            if( strtotime($hasta) < strtotime($reg->fecha) )
            {
               $anyadir = FALSE;
            }
         }

         if($anyadir)
         {
            $mlist[] = array(
                'referencia' => $ref,
                'codalmacen' => $reg->codalmacendest,
                'origen' => 'Regularización',
                'url' => 'index.php?page=ventas_articulo&ref='.$ref,
                'clipro' => '-',
                'movimiento' => '-',
                'precio' => 0,
                'dto' => 0,
                'inicial' => $reg->cantidadini,
                'final' => $reg->cantidadfin,
                'fecha' => $reg->fecha,
                'hora' => $reg->hora
            );
         }
      }

      /// forzamos la comprobación de las tablas de albaranes
      $albc = new albaran_cliente();
      $lin1 = new linea_albaran_cliente();
      $albp = new albaran_proveedor();
      $lin2 = new linea_albaran_proveedor();

      $sql_extra = '';
      $rango_fecha = '';
      if($desde)
      {
         $sql_extra .= " AND fecha >= ".$this->empresa->var2str($desde);
         $rango_fecha .= " AND fecha >= ".$this->empresa->var2str($desde);
      }

      if($hasta)
      {
         $sql_extra .= " AND fecha <= ".$this->empresa->var2str($hasta);
         $rango_fecha .= " AND fecha <= ".$this->empresa->var2str($hasta);
      }

      if($codagente)
      {
         $sql_extra .= " AND codagente = ".$this->empresa->var2str($codagente);
      }

      if($this->codalmacen)
      {
         $sql_extra .= " AND codalmacen = ".$this->empresa->var2str($this->codalmacen);
      }

      /// Si existen estas tablas se genera la información de las transferencias de stock
      if( $this->db->table_exists('transstock', $this->tablas) AND $this->db->table_exists('lineastransstock', $this->tablas) )
      {
         /*
          * Generamos la informacion de las transferencias por ingresos entre almacenes que se hayan hecho a los stocks
          */
         $sql_regstocks = "select l.idtrans, fecha, hora, referencia, sum(cantidad) as cantidad "
                 ." FROM lineastransstock AS ls "
                 ." JOIN transstock as l ON (ls.idtrans = l.idtrans) "
                 ." WHERE codalmadestino = " . $this->empresa->var2str($this->codalmacen) . $rango_fecha
                 ." GROUP by l.idtrans, fecha, hora, referencia "
                 ." ORDER by l.idtrans;";
         $data = $this->db->select($sql_regstocks);
         if($data)
         {
            foreach($data as $d)
            {
               $mlist[] = array(
                   'referencia' => $d['referencia'],
                   'codalmacen' => $this->codalmacen,
                   'origen' => 'Ingreso por transferencia '.$d['idtrans'],
                   'url' => 'index.php?page=editar_transferencia_stock&id='.$d['idtrans'],
                   'clipro' => '',
                   'movimiento' => floatval($d['cantidad']),
                   'precio' => 0,
                   'dto' => 0,
                   'inicial' => 0,
                   'final' => 0,
                   'fecha' => date('d-m-Y', strtotime($d['fecha'])),
                   'hora' => $d['hora']
               );
            }
         }

         /*
          * Generamos la informacion de las transferencias por salidas entre almacenes que se hayan hecho a los stocks
          */
         $sql_regstocks = "select l.idtrans, fecha, hora, referencia, sum(cantidad) as cantidad "
                 ." from lineastransstock AS ls "
                 ." JOIN transstock as l ON(ls.idtrans = l.idtrans) "
                 ." where codalmaorigen = " . $this->empresa->var2str($this->codalmacen) . $rango_fecha
                 ." group by l.idtrans, fecha, hora, referencia "
                 ." order by l.idtrans;";
         $data = $this->db->select($sql_regstocks);
         if($data)
         {
            foreach($data as $d)
            {
               $mlist[] = array(
                   'referencia' => $d['referencia'],
                   'codalmacen' => $this->codalmacen,
                   'origen' => 'Salida por transferencia '.$d['idtrans'],
                   'url' => 'index.php?page=editar_transferencia_stock&id='.$d['idtrans'],
                   'clipro' => '',
                   'movimiento' => 0 - floatval($d['cantidad']),
                   'precio' => 0,
                   'dto' => 0,
                   'inicial' => 0,
                   'final' => 0,
                   'fecha' => date('d-m-Y', strtotime($d['fecha'])),
                   'hora' => $d['hora']
               );
            }
         }
      }

      /// buscamos el artículo en albaranes de compra
      $sql = "SELECT a.codigo,l.cantidad,l.pvpunitario,l.dtopor,a.fecha,a.hora"
              .",a.codalmacen,a.idalbaran,a.codproveedor,a.nombre"
              ." FROM albaranesprov a, lineasalbaranesprov l"
              ." WHERE a.idalbaran = l.idalbaran AND l.referencia = ".$albc->var2str($ref).$sql_extra;

      $data = $this->db->select($sql);
      if($data)
      {
         foreach($data as $d)
         {
            $mlist[] = array(
                'referencia' => $ref,
                'codalmacen' => $d['codalmacen'],
                'origen' => 'Albaran compra '.$d['codigo'],
                'url' => 'index.php?page=compras_albaran&id='.$d['idalbaran'],
                'clipro' => $d['codproveedor'].' - '.$d['nombre'],
                'movimiento' => floatval($d['cantidad']),
                'precio' => floatval($d['pvpunitario']),
                'dto' => floatval($d['dtopor']),
                'inicial' => 0,
                'final' => 0,
                'fecha' => date('d-m-Y', strtotime($d['fecha'])),
                'hora' => $d['hora']
            );
         }
      }

      /// buscamos el artículo en facturas de compra
      $sql = "SELECT f.codigo,l.cantidad,l.pvpunitario,l.dtopor,f.fecha,f.hora"
              .",f.codalmacen,f.idfactura,f.codproveedor,f.nombre"
              ." FROM facturasprov f, lineasfacturasprov l"
              ." WHERE f.idfactura = l.idfactura AND l.idalbaran IS NULL"
              ." AND l.referencia = ".$albc->var2str($ref).$sql_extra;

      $data = $this->db->select($sql);
      if($data)
      {
         foreach($data as $d)
         {
            $mlist[] = array(
                'referencia' => $ref,
                'codalmacen' => $d['codalmacen'],
                'origen' => 'Factura compra '.$d['codigo'],
                'url' => 'index.php?page=compras_factura&id='.$d['idfactura'],
                'clipro' => $d['codproveedor'].' - '.$d['nombre'],
                'movimiento' => floatval($d['cantidad']),
                'precio' => floatval($d['pvpunitario']),
                'dto' => floatval($d['dtopor']),
                'inicial' => 0,
                'final' => 0,
                'fecha' => date('d-m-Y', strtotime($d['fecha'])),
                'hora' => $d['hora']
            );
         }
      }

      /// buscamos el artículo en albaranes de venta
      $sql = "SELECT a.codigo,l.cantidad,l.pvpunitario,l.dtopor,a.fecha,a.hora"
              .",a.codalmacen,a.idalbaran,a.codcliente,a.nombrecliente"
              ." FROM albaranescli a, lineasalbaranescli l"
              ." WHERE a.idalbaran = l.idalbaran AND l.referencia = ".$albc->var2str($ref).$sql_extra;

      $data = $this->db->select($sql);
      if($data)
      {
         foreach($data as $d)
         {
            $mlist[] = array(
                'referencia' => $ref,
                'codalmacen' => $d['codalmacen'],
                'origen' => 'Albaran venta '.$d['codigo'],
                'url' => 'index.php?page=ventas_albaran&id='.$d['idalbaran'],
                'clipro' => $d['codcliente'].' - '.$d['nombrecliente'],
                'movimiento' => 0 - floatval($d['cantidad']),
                'precio' => floatval($d['pvpunitario']),
                'dto' => floatval($d['dtopor']),
                'inicial' => 0,
                'final' => 0,
                'fecha' => date('d-m-Y', strtotime($d['fecha'])),
                'hora' => $d['hora']
            );
         }
      }

      /// buscamos el artículo en facturas de venta
      $sql = "SELECT f.codigo,l.cantidad,l.pvpunitario,l.dtopor,f.fecha,f.hora"
              .",f.codalmacen,f.idfactura,f.codcliente,f.nombrecliente"
              ." FROM facturascli f, lineasfacturascli l"
              ." WHERE f.idfactura = l.idfactura AND l.idalbaran IS NULL"
              ." AND l.referencia = ".$albc->var2str($ref).$sql_extra;

      $data = $this->db->select($sql);
      if($data)
      {
         foreach($data as $d)
         {
            $mlist[] = array(
                'referencia' => $ref,
                'codalmacen' => $d['codalmacen'],
                'origen' => 'Factura venta '.$d['codigo'],
                'url' => 'index.php?page=ventas_factura&id='.$d['idfactura'],
                'clipro' => $d['codcliente'].' - '.$d['nombrecliente'],
                'movimiento' => 0 - floatval($d['cantidad']),
                'precio' => floatval($d['pvpunitario']),
                'dto' => floatval($d['dtopor']),
                'inicial' => 0,
                'final' => 0,
                'fecha' => date('d-m-Y', strtotime($d['fecha'])),
                'hora' => $d['hora']
            );
         }
      }

      /// ordenamos por fecha y hora
      usort($mlist, function($a,$b) {
         if( strtotime($a['fecha'].' '.$a['hora']) == strtotime($b['fecha'].' '.$b['hora']) )
         {
            return 0;
         }
         else if( strtotime($a['fecha'].' '.$a['hora']) < strtotime($b['fecha'].' '.$b['hora']) )
         {
            return -1;
         }
         else
            return 1;
      });

      /// recalculamos
      $inicial = 0;
      foreach($mlist as $i => $value)
      {
         if($value['movimiento'] == '-')
         {
            $inicial += $value['final'] - $value['inicial'];
         }
         else
         {
            $inicial += $value['movimiento'];
         }
         $mlist[$i]['final'] = $inicial;
      }

      return $mlist;
   }

   private function informe_movimientos()
   {
      if($this->codfamilia)
      {
         $familia = $this->familia->get($this->codfamilia);
         if($familia)
         {
            foreach($familia->get_articulos() as $art)
            {
               foreach( $this->get_movimientos($art->referencia, $this->desde, $this->hasta, $this->codagente) as $mov )
               {
                  $this->resultados[] = $mov;
               }
            }
         }
         else
         {
            $this->new_advice('Familia no encontrada.');
         }
      }
      else if($this->referencia == '')
      {
         $this->new_advice('Selecciona una referencia o una familia.');
      }
      else
      {
         $this->resultados = $this->get_movimientos($this->referencia, $this->desde, $this->hasta, $this->codagente);
      }

      if( isset($_POST['generar']) )
      {
         if($_POST['generar'] == 'csv')
         {
            $this->template = FALSE;

            header("content-type:application/csv;charset=UTF-8");
            header("Content-Disposition: attachment; filename=\"listado_movimientos.csv\"");
            echo "referencia;almacen;documento;cliente/proveedor;movimiento;precio;%dto;cantidad final;fecha\n";

            $ref = FALSE;
            foreach($this->resultados as $value)
            {
               if(!$ref)
               {
                  $ref = $value['referencia'];
               }
               else if($ref != $value['referencia'])
               {
                  $ref = $value['referencia'];

                  echo ";;;;;;;;\n";
               }

               echo $value['referencia'].';'
                       .$value['codalmacen'].';'
                       .$value['origen'].';'
                       .fs_fix_html($value['clipro']).';';

               if( is_numeric($value['movimiento']) )
               {
                  echo number_format($value['movimiento'], FS_NF0, ',', '').';';
               }
               else
               {
                  echo ';';
               }

               echo number_format($value['precio'], FS_NF0_ART, ',', '').';'
                       .number_format($value['dto'], FS_NF0, ',', '').';'
                       .number_format($value['final'], FS_NF0, ',', '').';'
                       .$value['fecha']."\n";
            }
         }
         else if(!$this->resultados)
         {
            $this->new_message('Sin resultados.');
         }
      }
   }

   private function informe_ventascli()
   {
      $sql = "SELECT l.referencia,f.codcliente,f.fecha,SUM(l.cantidad) as total"
              . " FROM facturascli f, lineasfacturascli l"
              . " WHERE f.idfactura = l.idfactura AND l.referencia IS NOT NULL"
              . " AND f.fecha >= ".$this->empresa->var2str($_POST['desde'])
              . " AND f.fecha <= ".$this->empresa->var2str($_POST['hasta']);

      if($this->referencia != '')
      {
         $sql .= " AND l.referencia = ".$this->empresa->var2str($this->referencia);
      }
      else if($this->codfamilia != '')
      {
         $sql .= " AND l.referencia IN (SELECT referencia FROM articulos"
                 . " WHERE codfamilia IN (";
         $coma = '';
         foreach($this->get_subfamilias($this->codfamilia) as $fam)
         {
            $sql .= $coma.$this->empresa->var2str($fam);
            $coma = ',';
         }
         $sql .= "))";
      }

      if($_POST['minimo'] != '')
      {
         $sql .= " AND l.cantidad > ".$this->empresa->var2str($_POST['minimo']);
      }

      $sql .= " GROUP BY l.referencia,f.codcliente,f.fecha ORDER BY l.referencia ASC, f.codcliente ASC, f.fecha DESC;";

      $data = $this->db->select($sql);
      if($data)
      {
         $this->template = FALSE;

         header("content-type:application/csv;charset=UTF-8");
         header("Content-Disposition: attachment; filename=\"informe_ventas_unidades.csv\"");
         echo "referencia;codcliente;nombre;año;ene;feb;mar;abr;may;jun;jul;ago;sep;oct;nov;dic;total;%VAR\n";

         $cliente = new cliente();
         $stats = array();
         foreach($data as $d)
         {
            $anyo = date('Y', strtotime($d['fecha']));
            $mes = date('n', strtotime($d['fecha']));
            if( !isset($stats[ $d['referencia'] ][ $d['codcliente'] ][ $anyo ]) )
            {
               $stats[ $d['referencia'] ][ $d['codcliente'] ][ $anyo ] = array(
                   1 => 0,
                   2 => 0,
                   3 => 0,
                   4 => 0,
                   5 => 0,
                   6 => 0,
                   7 => 0,
                   8 => 0,
                   9 => 0,
                   10 => 0,
                   11 => 0,
                   12 => 0,
                   13 => 0,
                   14 => 0
               );
            }

            $stats[ $d['referencia'] ][ $d['codcliente'] ][ $anyo ][ $mes ] += floatval($d['total']);
            $stats[ $d['referencia'] ][ $d['codcliente'] ][ $anyo ][13] += floatval($d['total']);
         }

         foreach($stats as $i => $value)
         {
            foreach($value as $j => $value2)
            {
               /// calculamos la variación
               $anterior = 0;
               foreach( array_reverse($value2, TRUE) as $k => $value3 )
               {
                  if($anterior > 0)
                  {
                     $value2[$k][14] = ($value3[13]*100/$anterior) - 100;
                  }

                  $anterior = $value3[13];
               }

               $cli = $cliente->get($j);
               foreach($value2 as $k => $value3)
               {
                  if($cli)
                  {
                     echo '"'.$i.'";"'.$j.'";'.fs_fix_html($cli->nombre).';'.$k;
                  }
                  else
                  {
                     echo '"'.$i.'";"'.$j.'";-;'.$k;
                  }

                  foreach($value3 as $value4)
                  {
                     echo ';'.number_format($value4, FS_NF0, ',', '');
                  }

                  echo "\n";
               }
               echo ";;;;;;;;;;;;;;;\n";
            }
            echo ";;;;;;;;;;;;;;;\n";
         }
      }
      else
      {
         $this->new_error_msg('Sin resultados.');
      }
   }
}
